<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Migration;

use OCA\Pantry\Migration\RepairSchemaColumnsStep;
use OCA\Pantry\Migration\SchemaRepairResult;
use OCA\Pantry\Migration\SchemaRepairService;
use OCP\Migration\IOutput;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RepairSchemaColumnsStepTest extends TestCase {
	/** @var SchemaRepairService&MockObject */
	private SchemaRepairService $repair;
	/** @var LoggerInterface&MockObject */
	private LoggerInterface $logger;
	/** @var IOutput&MockObject */
	private IOutput $output;
	private RepairSchemaColumnsStep $step;

	protected function setUp(): void {
		$this->repair = $this->createMock(SchemaRepairService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->output = $this->createMock(IOutput::class);
		$this->step = new RepairSchemaColumnsStep($this->repair, $this->logger);
	}

	public function testStaysSilentWhenNothingWasRepaired(): void {
		$this->repair->method('repair')
			->willReturn(new SchemaRepairResult([], [], '', false));
		$this->output->expects($this->never())->method('info');
		$this->logger->expects($this->never())->method('warning');

		$this->step->run($this->output);
	}

	public function testReportsRestoredColumns(): void {
		$this->repair->method('repair')
			->willReturn(new SchemaRepairResult(['pantry_categories.list_id'], [], '', false));
		$this->output->expects($this->once())->method('info')
			->with($this->stringContains('pantry_categories.list_id'));
		$this->logger->expects($this->once())->method('warning');

		$this->step->run($this->output);
	}

	public function testReportsDroppedColumns(): void {
		$this->repair->method('repair')
			->willReturn(new SchemaRepairResult([], [], '', false, ['pantry_list_items.price_type']));
		$this->output->expects($this->once())->method('info')
			->with($this->logicalAnd(
				$this->stringContains('dropped'),
				$this->stringContains('pantry_list_items.price_type'),
			));
		$this->logger->expects($this->once())->method('warning');

		$this->step->run($this->output);
	}
}
