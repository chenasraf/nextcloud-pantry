<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Migration;

use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

/**
 * Post-migration self-heal for missing optional columns.
 *
 * Runs on every `occ upgrade` and `occ maintenance:repair`, after migrations.
 * If schema drift left an expected column behind, this adds
 * it back so the app stops erroring on the next request — no manual
 * `occ db:add-missing-columns` needed. When the schema is already whole it is a
 * silent no-op.
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

		$message = 'Pantry: restored missing column(s) ' . implode(', ', $result->added)
			. '. See "occ pantry:repair-schema".';
		$output->info($message);
		$this->logger->warning($message);
	}
}
