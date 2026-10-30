<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Service\StockRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StockRulesTest extends TestCase {
	/**
	 * @return array<string, mixed>
	 */
	private static function vectors(): array {
		$json = file_get_contents(__DIR__ . '/../../stock-vectors.json');
		return json_decode((string)$json, true, 512, JSON_THROW_ON_ERROR);
	}

	/**
	 * @return iterable<string, array{0: array<string, mixed>}>
	 */
	private static function cases(string $group): iterable {
		foreach (self::vectors()[$group] as $i => $case) {
			yield (string)($case['name'] ?? $case['text'] ?? "#$i") . " [$i]" => [$case];
		}
	}

	public static function firstNumberCases(): iterable {
		return self::cases('firstNumber');
	}

	public static function evaluateCases(): iterable {
		return self::cases('evaluate');
	}

	public static function startingOnHandCases(): iterable {
		return self::cases('startingOnHand');
	}

	public static function convertCases(): iterable {
		return self::cases('convert');
	}

	public static function expiryStatusCases(): iterable {
		return self::cases('expiryStatus');
	}

	#[DataProvider('firstNumberCases')]
	public function testFirstNumber(array $case): void {
		$expected = $case['value'] === null ? null : (float)$case['value'];
		$this->assertSame($expected, StockRules::firstNumber($case['text']));
	}

	#[DataProvider('evaluateCases')]
	public function testEvaluate(array $case): void {
		$this->assertSame(
			['stockStatus' => $case['stockStatus'], 'shortfall' => $case['shortfall']],
			StockRules::evaluate($case['item']),
		);
	}

	#[DataProvider('startingOnHandCases')]
	public function testStartingOnHand(array $case): void {
		$this->assertSame($case['result'], StockRules::startingOnHand($case['quantity'], $case['target']));
	}

	#[DataProvider('expiryStatusCases')]
	public function testExpiryStatus(array $case): void {
		$this->assertSame($case['expiryStatus'], StockRules::expiryStatus($case['item'], $case['leadDays'], $case['today']));
	}

	public function testAddDaysLandsOnMidnight(): void {
		$today = StockRules::today();
		$this->assertSame(7, StockRules::daysUntil(StockRules::addDays($today, 7), $today));
		$this->assertSame('00:00', date('H:i', StockRules::addDays($today, 200)));
	}

	#[DataProvider('convertCases')]
	public function testConvert(array $case): void {
		$this->assertSame($case['result'], StockRules::convert($case['item'], $case['toMode']));
	}
}
