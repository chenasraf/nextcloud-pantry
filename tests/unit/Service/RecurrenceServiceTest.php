<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Service\RecurrenceService;
use PHPUnit\Framework\TestCase;

class RecurrenceServiceTest extends TestCase {
	private RecurrenceService $svc;

	protected function setUp(): void {
		$this->svc = new RecurrenceService();
	}

	public function testValidateAcceptsBareRule(): void {
		$this->svc->validate('FREQ=WEEKLY;INTERVAL=1');
		$this->assertTrue(true);
	}

	public function testValidateAcceptsPrefixedRule(): void {
		$this->svc->validate('RRULE:FREQ=DAILY');
		$this->assertTrue(true);
	}

	public function testValidateRejectsGarbage(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->validate('not-an-rrule');
	}

	public function testWeeklyNextOccurrence(): void {
		$from = new \DateTimeImmutable('2026-04-05T12:00:00Z');
		$next = $this->svc->computeNextOccurrence('FREQ=WEEKLY;INTERVAL=1', $from);
		$this->assertNotNull($next);
		$this->assertSame('2026-04-12', $next->format('Y-m-d'));
	}

	public function testDailyNextOccurrence(): void {
		$from = new \DateTimeImmutable('2026-04-05T08:00:00Z');
		$next = $this->svc->computeNextOccurrence('FREQ=DAILY', $from);
		$this->assertNotNull($next);
		$this->assertSame('2026-04-06', $next->format('Y-m-d'));
	}

	public function testMonthlyNextOccurrence(): void {
		$from = new \DateTimeImmutable('2026-04-05T00:00:00Z');
		$next = $this->svc->computeNextOccurrence('FREQ=MONTHLY;INTERVAL=1', $from);
		$this->assertNotNull($next);
		$this->assertSame('2026-05-05', $next->format('Y-m-d'));
	}

	public function testBiweeklyNextOccurrence(): void {
		$from = new \DateTimeImmutable('2026-04-05T00:00:00Z');
		$next = $this->svc->computeNextOccurrence('FREQ=WEEKLY;INTERVAL=2', $from);
		$this->assertNotNull($next);
		$this->assertSame('2026-04-19', $next->format('Y-m-d'));
	}

	public function testMonthlyOrdinalWeekdayNextOccurrence(): void {
		$from = new \DateTimeImmutable('2026-04-05T00:00:00Z');
		$next = $this->svc->computeNextOccurrence('FREQ=MONTHLY;INTERVAL=1;BYDAY=+2MO', $from);
		$this->assertNotNull($next);
		$this->assertSame('2026-04-13', $next->format('Y-m-d'));
	}

	public function testMonthlyLastWeekdayNextOccurrence(): void {
		$from = new \DateTimeImmutable('2026-04-05T00:00:00Z');
		$next = $this->svc->computeNextOccurrence('FREQ=MONTHLY;INTERVAL=1;BYDAY=-1FR', $from);
		$this->assertNotNull($next);
		$this->assertSame('2026-04-24', $next->format('Y-m-d'));
	}

	public function testYearlyDateNextOccurrence(): void {
		$from = new \DateTimeImmutable('2026-04-05T00:00:00Z');
		$next = $this->svc->computeNextOccurrence('FREQ=YEARLY;INTERVAL=1;BYMONTH=11;BYMONTHDAY=29', $from);
		$this->assertNotNull($next);
		$this->assertSame('2026-11-29', $next->format('Y-m-d'));
	}

	public function testYearlyDateSkipsToNextYearOnceThePastIsBehind(): void {
		$from = new \DateTimeImmutable('2026-04-05T00:00:00Z');
		$next = $this->svc->computeNextOccurrence('FREQ=YEARLY;INTERVAL=1;BYMONTH=3;BYMONTHDAY=15', $from);
		$this->assertNotNull($next);
		$this->assertSame('2027-03-15', $next->format('Y-m-d'));
	}

	public function testYearlyLeapDayLandsOnALeapYear(): void {
		$from = new \DateTimeImmutable('2026-04-05T00:00:00Z');
		$next = $this->svc->computeNextOccurrence('FREQ=YEARLY;INTERVAL=1;BYMONTH=2;BYMONTHDAY=29', $from);
		$this->assertNotNull($next);
		$this->assertSame('2028-02-29', $next->format('Y-m-d'));
	}
}
