<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Migration;

use OCA\Pantry\AppInfo\Application;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

/**
 * Post-migration health check that flags a missing stores table.
 *
 * Runs on every `occ upgrade` and `occ maintenance:repair`, after migrations.
 * The item-list endpoints query pantry_item_stores on every request, so a
 * missing table takes down the whole checklist. If migrations
 * did their job the tables are present and this is a silent no-op; if either is
 * still missing it surfaces a loud, actionable warning in the occ output and
 * the log rather than letting the breakage stay invisible until a user reports
 * empty lists.
 */
class EnsureStoreTablesRepairStep implements IRepairStep {
	public function __construct(
		private IDBConnection $db,
		private LoggerInterface $logger,
	) {
	}

	public function getName(): string {
		return 'Verify Pantry store tables exist';
	}

	public function run(IOutput $output): void {
		$missing = array_filter(
			[
				Application::tableName('stores'),
				Application::tableName('item_stores'),
				Application::tableName('item_prices'),
			],
			fn (string $table): bool => !$this->db->tableExists($table),
		);

		if ($missing === []) {
			return;
		}

		$list = implode(', ', $missing);
		$message = sprintf(
			'Pantry store tables are missing (%s). Checklist items will not load until this is fixed. '
			. 'Run "occ upgrade" (or "occ migrations:migrate pantry") to recreate them.',
			$list,
		);
		$output->warning($message);
		$this->logger->error($message);
	}
}
