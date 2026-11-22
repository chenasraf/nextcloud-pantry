<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Service\NameKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NameKeyTest extends TestCase {
	/**
	 * @return iterable<string, array{string, string}>
	 */
	public static function cases(): iterable {
		$json = file_get_contents(__DIR__ . '/../../fixtures/name-key-cases.json');
		/** @var list<array{name: string, key: string}> $cases */
		$cases = json_decode((string)$json, true, flags: JSON_THROW_ON_ERROR);
		foreach ($cases as $case) {
			yield json_encode($case['name'], JSON_UNESCAPED_UNICODE) => [$case['name'], $case['key']];
		}
	}

	#[DataProvider('cases')]
	public function testFoldsLikeTheClients(string $name, string $key): void {
		$this->assertSame($key, NameKey::of($name));
	}
}
