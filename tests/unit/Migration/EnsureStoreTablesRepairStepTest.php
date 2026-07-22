<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Migration;

use OCA\Pantry\Migration\EnsureStoreTablesRepairStep;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class EnsureStoreTablesRepairStepTest extends TestCase {
	/** @var IDBConnection&MockObject */
	private IDBConnection $db;
	/** @var LoggerInterface&MockObject */
	private LoggerInterface $logger;
	/** @var IOutput&MockObject */
	private IOutput $output;
	private EnsureStoreTablesRepairStep $step;

	protected function setUp(): void {
		$this->db = $this->createMock(IDBConnection::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->output = $this->createMock(IOutput::class);
		$this->step = new EnsureStoreTablesRepairStep($this->db, $this->logger);
	}

	public function testStaysSilentWhenBothTablesExist(): void {
		$this->db->method('tableExists')->willReturn(true);
		$this->output->expects($this->never())->method('warning');
		$this->logger->expects($this->never())->method('error');

		$this->step->run($this->output);
	}

	public function testWarnsWhenATableIsMissing(): void {
		$this->db->method('tableExists')->willReturnCallback(
			static fn (string $table): bool => $table !== 'pantry_item_stores',
		);
		$this->output->expects($this->once())->method('warning')
			->with($this->stringContains('pantry_item_stores'));
		$this->logger->expects($this->once())->method('error');

		$this->step->run($this->output);
	}

	public function testWarnsWhenBothTablesMissing(): void {
		$this->db->method('tableExists')->willReturn(false);
		$this->output->expects($this->once())->method('warning')
			->with($this->logicalAnd(
				$this->stringContains('pantry_stores'),
				$this->stringContains('pantry_item_stores'),
			));
		$this->logger->expects($this->once())->method('error');

		$this->step->run($this->output);
	}
}
