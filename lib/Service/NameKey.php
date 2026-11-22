<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

/**
 * The key item memory files a name under, so "Milk", "milk " and "Milk (2%)"
 * share what the household picked for them.
 *
 * The web and mobile clients fold names the same way to look memory up, so
 * every rule here has a twin in each client, and
 * tests/fixtures/name-key-cases.json holds the cases all three run.
 */
final class NameKey {
	public static function of(string $name): string {
		if (class_exists(\Normalizer::class)) {
			$name = \Normalizer::normalize($name, \Normalizer::FORM_D);
			if ($name === false) {
				return '';
			}
		}
		$name = (string)preg_replace('/\p{Mn}/u', '', $name);

		// Innermost parentheses first, so nested ones go too.
		do {
			$before = $name;
			$name = (string)preg_replace('/\s*\([^()]*\)/u', '', $name);
		} while ($name !== $before);

		$comma = mb_strpos($name, ',');
		if ($comma !== false) {
			$name = mb_substr($name, 0, $comma);
		}

		return trim((string)preg_replace('/\s+/u', ' ', mb_strtolower($name)));
	}
}
