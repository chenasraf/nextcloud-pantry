<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

/**
 * Holds the app to the server API of the oldest version it claims to support.
 *
 * `appinfo/info.xml` promises a `min-version`, but nothing in the toolchain
 * reads that promise back: the OCP stubs track one server version, the local
 * test run uses whichever server the developer has checked out, and a symbol
 * that exists in either sails through review. The first sign that a symbol
 * arrived after `min-version` is then a red build on the oldest server in CI —
 * long after the code looked finished.
 *
 * So every `OCP\…` symbol `lib/` and `tests/` mention is checked here against
 * the stubs: it has to exist, and its `@since` has to be no newer than
 * `min-version`.
 *
 * A newer symbol is allowed where the code guards it — `class_exists()` around
 * the use, with a fallback for servers that lack it — since that is the
 * supported way to reach a newer API without dropping the older server.
 *
 * Limit worth knowing: `@since` is read off the type, not its methods, so a
 * method added to an older interface is not caught. Types carry the common
 * mistake; methods would mean parsing every signature in the stubs.
 *
 * Usage: php scripts/check-ocp-compat.php [--verbose]
 */

const ROOT = __DIR__ . '/..';
const STUBS = ROOT . '/vendor/nextcloud/ocp';

/** Scanned for OCP references. Stubs declare server internals, not app code. */
const SCAN_DIRS = ['lib', 'tests'];
const SKIP_DIRS = ['tests/stubs'];

$verbose = in_array('--verbose', $argv, true);

$minVersion = readMinVersion(ROOT . '/appinfo/info.xml');
if ($minVersion === null) {
	fwrite(STDERR, "Could not read min-version from appinfo/info.xml\n");
	exit(2);
}
if (!is_dir(STUBS)) {
	fwrite(STDERR, "OCP stubs not installed — run `composer install` first\n");
	exit(2);
}

/** @var array<string, list<string>> symbol => files that mention it */
$refs = [];
foreach (SCAN_DIRS as $dir) {
	foreach (phpFiles(ROOT . '/' . $dir) as $file) {
		$rel = relativePath($file);
		if (isSkipped($rel)) {
			continue;
		}
		foreach (ocpSymbols((string)file_get_contents($file)) as $symbol) {
			$refs[$symbol][] = $rel;
		}
	}
}
ksort($refs);

$missing = [];
$tooNew = [];
$guarded = [];
foreach ($refs as $symbol => $files) {
	$stub = stubPath($symbol);
	$since = $stub === null ? null : readSince($stub);
	$available = $stub !== null && ($since === null || $since <= $minVersion);
	if ($available) {
		continue;
	}

	// Reaching a newer API behind class_exists(), with a fallback, is the
	// supported way to support both servers at once.
	$unguarded = array_values(array_filter(
		array_unique($files),
		static fn (string $file): bool => !guardsSymbol($file, $symbol),
	));
	if ($unguarded === []) {
		$guarded[$symbol] = $since;
		continue;
	}

	if ($stub === null) {
		$missing[$symbol] = $unguarded;
	} else {
		$tooNew[$symbol] = ['since' => $since, 'files' => $unguarded];
	}
}

if ($verbose) {
	printf("min-version %d · %d OCP symbols referenced\n", $minVersion, count($refs));
	foreach ($guarded as $symbol => $since) {
		printf("  guarded: %s (@since %s)\n", $symbol, $since ?? 'unknown');
	}
}

if ($missing === [] && $tooNew === []) {
	printf("\x1b[32m✔ %d OCP symbols are available on Nextcloud %d\x1b[0m\n", count($refs), $minVersion);
	exit(0);
}

foreach ($missing as $symbol => $files) {
	printf("\x1b[31m✘ %s does not exist in the OCP stubs\x1b[0m\n", $symbol);
	foreach (array_unique($files) as $file) {
		printf("    %s\n", $file);
	}
}
foreach ($tooNew as $symbol => $info) {
	printf(
		"\x1b[31m✘ %s is @since %d, newer than min-version %d\x1b[0m\n",
		$symbol,
		$info['since'],
		$minVersion,
	);
	foreach (array_unique($info['files']) as $file) {
		printf("    %s\n", $file);
	}
}
printf(
	"\n\x1b[33mUse a symbol available on Nextcloud %d, or raise min-version in appinfo/info.xml.\x1b[0m\n",
	$minVersion,
);
exit(1);

// ---------------------------------------------------------------------------

function readMinVersion(string $infoXml): ?int {
	$xml = @file_get_contents($infoXml);
	if ($xml === false || !preg_match('/min-version="(\d+)"/', $xml, $m)) {
		return null;
	}

	return (int)$m[1];
}

/**
 * @return list<string>
 */
function phpFiles(string $dir): array {
	if (!is_dir($dir)) {
		return [];
	}
	$found = [];
	$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
	foreach ($it as $entry) {
		if ($entry instanceof SplFileInfo && $entry->getExtension() === 'php') {
			$found[] = $entry->getPathname();
		}
	}
	sort($found);

	return $found;
}

function relativePath(string $absolute): string {
	$root = realpath(ROOT);
	$real = realpath($absolute);

	return $root !== false && $real !== false && str_starts_with($real, $root)
		? ltrim(substr($real, strlen($root)), '/')
		: $absolute;
}

function isSkipped(string $relative): bool {
	foreach (SKIP_DIRS as $skip) {
		if (str_starts_with($relative, $skip . '/')) {
			return true;
		}
	}

	return false;
}

/**
 * Every OCP type a file names: `use` imports, and inline `\OCP\…` references
 * including the ones that only appear in psalm annotations.
 *
 * @return list<string>
 */
function ocpSymbols(string $source): array {
	$symbols = [];

	// use OCP\Files\Folder;  /  use OCP\Files\NotFoundException as FilesNot...;
	// `use function`/`use const` name callables, not types, so they are skipped.
	if (preg_match_all('/^\s*use\s+(OCP\\\\[A-Za-z0-9_\\\\]+)\s*(?:as\s+\w+)?\s*;/m', $source, $m)) {
		$symbols = array_merge($symbols, $m[1]);
	}

	// \OCP\Files\Folder, in code or in a docblock type.
	if (preg_match_all('/\\\\(OCP\\\\[A-Za-z0-9_\\\\]+)/', $source, $m)) {
		$symbols = array_merge($symbols, $m[1]);
	}

	return array_values(array_unique(array_map(
		static fn (string $s): string => trim($s, '\\'),
		$symbols,
	)));
}

/**
 * Whether a file reaches a symbol behind an existence check, which is what
 * makes using a newer API safe on an older server.
 */
function guardsSymbol(string $relativeFile, string $symbol): bool {
	$source = @file_get_contents(ROOT . '/' . $relativeFile);
	if ($source === false) {
		return false;
	}
	$parts = explode('\\', $symbol);
	$short = preg_quote(end($parts), '/');
	$full = preg_quote($symbol, '/');

	// class_exists(Foo::class) / interface_exists(\OCP\…\Foo::class) / class_exists('OCP\…\Foo')
	$pattern = sprintf(
		'/\b(?:class_exists|interface_exists)\s*\(\s*[\'"\\\\]*(?:%s|%s)(?:::class)?[\'"]?\s*[,)]/',
		$full,
		$short,
	);

	return (bool)preg_match($pattern, $source);
}

/**
 * The stub declaring a symbol, or null when nothing declares it.
 *
 * The match is exact. Resolving a missing symbol against its parent namespace
 * would quietly pass every symbol whose namespace shares a name with a real
 * type — `OCP\Files\IUserFolder` would be waved through by the legacy
 * `OCP\Files` class sitting next to the directory.
 */
function stubPath(string $symbol): ?string {
	$candidate = STUBS . '/' . str_replace('\\', '/', $symbol) . '.php';

	return is_file($candidate) ? $candidate : null;
}

/**
 * The major server version a stub's type says it arrived in.
 */
function readSince(string $stubFile): ?int {
	$source = (string)file_get_contents($stubFile);

	// The docblock attached to the type declaration, not to a method above it.
	if (!preg_match('/\/\*\*(?:(?!\*\/).)*\*\/\s*(?:#\[[^\]]*\]\s*)*(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|trait|enum)\s+\w/s', $source, $m)) {
		return null;
	}

	return preg_match('/@since\s+(\d+)/', $m[0], $s) ? (int)$s[1] : null;
}
