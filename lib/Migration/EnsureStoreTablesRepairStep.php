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
 * Post-migration health check that flags any missing item-relation table.
 *
 * Runs on every `occ upgrade` and `occ maintenance:repair`, after migrations.
 * The item-list endpoints query the store, price and label tables on every
 * request, so a missing one takes down the whole checklist. If migrations did
 * their job the tables are present and this is a silent no-op; if any is still
 * missing it surfaces a loud, actionable warning in the occ output and the log
 * rather than letting the breakage stay invisible until a user reports empty
 * lists.
 */
class EnsureStoreTablesRepairStep implements IRepairStep {
	public function __construct(
		private IDBConnection $db,
		private LoggerInterface $logger,
	) {
	}

	public function getName(): string {
		return 'Verify Pantry item-relation tables exist';
	}

	public function run(IOutput $output): void {
		$missing = array_filter(
			[
				Application::tableName('stores'),
				Application::tableName('item_stores'),
				Application::tableName('item_prices'),
				Application::tableName('labels'),
				Application::tableName('item_labels'),
				Application::tableName('field_defs'),
				Application::tableName('field_options'),
				Application::tableName('field_values'),
			],
			fn (string $table): bool => !$this->db->tableExists($table),
		);

		if ($missing === []) {
			return;
		}

		$list = implode(', ', $missing);
		$message = sprintf(
			'Pantry tables are missing (%s). Checklist items will not load until this is fixed. '
			. 'Run "occ upgrade" (or "occ migrations:migrate pantry") to recreate them.',
			$list,
		);
		$output->warning($message);
		$this->logger->error($message);
	}
}
