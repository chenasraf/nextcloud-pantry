<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Command;

use OCA\Pantry\Command\RepairSchema;
use OCA\Pantry\Migration\SchemaRepairResult;
use OCA\Pantry\Migration\SchemaRepairService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class RepairSchemaTest extends TestCase {
	/** @var SchemaRepairService&MockObject */
	private SchemaRepairService $repair;
	private CommandTester $tester;

	protected function setUp(): void {
		$this->repair = $this->createMock(SchemaRepairService::class);
		$this->tester = new CommandTester(new RepairSchema($this->repair));
	}

	public function testReportsNothingToRepair(): void {
		$this->repair->expects($this->once())->method('repair')->with(false)
			->willReturn(new SchemaRepairResult([], [], '', false));

		$this->assertSame(0, $this->tester->execute([]));
		$this->assertStringContainsString('up to date', $this->tester->getDisplay());
	}

	public function testReportsAddedColumns(): void {
		$this->repair->method('repair')
			->willReturn(new SchemaRepairResult(['pantry_list_items.price_type'], [], '', false));

		$this->assertSame(0, $this->tester->execute([]));
		$display = $this->tester->getDisplay();
		$this->assertStringContainsString('Added pantry_list_items.price_type', $display);
		$this->assertStringContainsString('Repaired 1 column', $display);
	}

	public function testDryRunPrintsSqlWithoutApplying(): void {
		$this->repair->expects($this->once())->method('repair')->with(true)
			->willReturn(new SchemaRepairResult(
				['pantry_list_items.price_type'],
				[],
				'ALTER TABLE oc_pantry_list_items ADD price_type VARCHAR(8);',
				true,
			));

		$this->assertSame(0, $this->tester->execute(['--dry-run' => true]));
		$display = $this->tester->getDisplay();
		$this->assertStringContainsString('Would add 1 column', $display);
		$this->assertStringContainsString('ALTER TABLE', $display);
	}

	public function testWarnsAboutMissingTable(): void {
		$this->repair->method('repair')
			->willReturn(new SchemaRepairResult([], ['pantry_shopsess_items'], '', false));

		$this->assertSame(0, $this->tester->execute([]));
		$this->assertStringContainsString('pantry_shopsess_items', $this->tester->getDisplay());
	}
}
