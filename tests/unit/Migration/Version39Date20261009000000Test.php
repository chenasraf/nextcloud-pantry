<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Migration;

use OCA\Pantry\Migration\Version39Date20261009000000;
use PHPUnit\Framework\TestCase;

class Version39Date20261009000000Test extends TestCase {
	public function testRememberKeepsWhatTheLastItemUsed(): void {
		$this->assertSame(
			['mode' => 'remember', 'value' => ['kind' => 'recurring', 'rrule' => 'FREQ=DAILY', 'repeatFromCompletion' => true]],
			Version39Date20261009000000::recurrenceEntry('remember', 'recurring', 'FREQ=DAILY', true),
		);
	}

	public function testAPinnedPolicyBecomesAFixedDefaultOfThatKind(): void {
		$this->assertSame(
			['mode' => 'fixed', 'value' => ['kind' => 'once', 'rrule' => null, 'repeatFromCompletion' => false]],
			Version39Date20261009000000::recurrenceEntry('once', 'none', 'FREQ=DAILY', true),
		);
	}

	public function testAPinnedRecurringPolicyWithoutARuleRepeatsWeekly(): void {
		$this->assertSame(
			['mode' => 'fixed', 'value' => ['kind' => 'recurring', 'rrule' => 'FREQ=WEEKLY;INTERVAL=1', 'repeatFromCompletion' => false]],
			Version39Date20261009000000::recurrenceEntry('recurring', 'recurring', null, false),
		);
	}

	public function testStaplesNeedNoDefault(): void {
		$this->assertNull(Version39Date20261009000000::recurrenceEntry('none', 'none', null, false));
	}
}
