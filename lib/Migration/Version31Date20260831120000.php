<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Migration;

use Closure;
use OCA\Pantry\AppInfo\Application;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Add per-house field reminder time. Date custom-field reminders are sent at
 * this time of day (minutes since midnight, server timezone). Existing houses
 * inherit their recurrence time so reminders and recurring items fire together
 * until an admin sets them apart.
 */
class Version31Date20260831120000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $connection,
	) {
	}

	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$housesTable = Application::tableName('houses');
		if ($schema->hasTable($housesTable)) {
			$table = $schema->getTable($housesTable);
			if (!$table->hasColumn('field_reminder_time')) {
				$table->addColumn('field_reminder_time', Types::INTEGER, [
					'notnull' => true,
					'default' => 480,
				]);
			}
		}

		return $schema;
	}

	/**
	 * Seed each house's field reminder time from its recurrence time, so the new
	 * setting starts equal to the one it mirrors.
	 *
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$housesTable = Application::tableName('houses');

		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable($housesTable)) {
			return;
		}
		$table = $schema->getTable($housesTable);
		if (!$table->hasColumn('recurrence_time') || !$table->hasColumn('field_reminder_time')) {
			return;
		}

		$select = $this->connection->getQueryBuilder();
		$select->select('id', 'recurrence_time')
			->from($housesTable);
		$result = $select->executeQuery();

		$updated = 0;
		while ($row = $result->fetch()) {
			$update = $this->connection->getQueryBuilder();
			$update->update($housesTable)
				->set('field_reminder_time', $update->createNamedParameter((int)$row['recurrence_time'], IQueryBuilder::PARAM_INT))
				->where($update->expr()->eq('id', $update->createNamedParameter((int)$row['id'], IQueryBuilder::PARAM_INT)));
			$update->executeStatement();
			$updated++;
		}
		$result->closeCursor();

		if ($updated > 0) {
			$output->info('Pantry: seeded field reminder time from recurrence time for ' . $updated . ' house(s)');
		}
	}
}
