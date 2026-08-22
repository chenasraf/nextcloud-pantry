<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Migration;

use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

/**
 * Post-migration self-heal for drifted optional columns.
 *
 * Runs on every `occ upgrade` and `occ maintenance:repair`, after migrations.
 * If schema drift left an expected column missing, this adds it back; if it left
 * a legacy column behind, this drops it — so the app stops erroring on the next
 * request, with no manual `occ db:add-missing-columns` (which cannot drop)
 * needed. When the schema is already whole it is a silent no-op.
 */
class RepairSchemaColumnsStep implements IRepairStep {
	public function __construct(
		private SchemaRepairService $repair,
		private LoggerInterface $logger,
	) {
	}

	public function getName(): string {
		return 'Repair missing Pantry columns';
	}

	public function run(IOutput $output): void {
		$result = $this->repair->repair();

		if (!$result->madeChanges()) {
			return;
		}

		$parts = [];
		if ($result->added !== []) {
			$parts[] = 'restored missing column(s) ' . implode(', ', $result->added);
		}
		if ($result->dropped !== []) {
			$parts[] = 'dropped leftover column(s) ' . implode(', ', $result->dropped);
		}

		$message = 'Pantry: ' . implode('; ', $parts) . '. See "occ pantry:repair-schema".';
		$output->info($message);
		$this->logger->warning($message);
	}
}
