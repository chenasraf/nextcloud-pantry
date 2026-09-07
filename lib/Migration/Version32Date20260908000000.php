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
 * Add the per-list recurrence default to checklists.
 *
 * `default_recurrence_mode` is the policy an editor picks; the remaining three
 * columns hold the recurrence new items actually start with. The two are split
 * because the "remember" policy keeps learning from what gets added, which would
 * otherwise overwrite the policy itself.
 */
class Version32Date20260908000000 extends SimpleMigrationStep {
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

		$listsTable = Application::tableName('lists');
		if (!$schema->hasTable($listsTable)) {
			return null;
		}
		$table = $schema->getTable($listsTable);

		if (!$table->hasColumn('default_recurrence_mode')) {
			$table->addColumn('default_recurrence_mode', Types::STRING, [
				'notnull' => false,
				'length' => 16,
				'default' => 'remember',
			]);
		}
		if (!$table->hasColumn('default_recurrence_kind')) {
			$table->addColumn('default_recurrence_kind', Types::STRING, [
				'notnull' => false,
				'length' => 16,
				'default' => 'none',
			]);
		}
		if (!$table->hasColumn('default_rrule')) {
			$table->addColumn('default_rrule', Types::STRING, [
				'notnull' => false,
				'length' => 255,
				'default' => null,
			]);
		}
		if (!$table->hasColumn('default_repeat_from_completion')) {
			$table->addColumn('default_repeat_from_completion', Types::BOOLEAN, [
				'notnull' => false,
				'default' => false,
			]);
		}

		return $schema;
	}

	/**
	 * Carry the remembered "Once" choice over into the recurrence default, so
	 * lists that were pinned to one-time items keep adding them.
	 *
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$listsTable = Application::tableName('lists');

		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable($listsTable)) {
			return;
		}
		$table = $schema->getTable($listsTable);
		if (!$table->hasColumn('delete_on_done_default') || !$table->hasColumn('default_recurrence_kind')) {
			return;
		}

		$update = $this->connection->getQueryBuilder();
		$update->update($listsTable)
			->set('default_recurrence_kind', $update->createNamedParameter('once', IQueryBuilder::PARAM_STR))
			->where($update->expr()->eq('delete_on_done_default', $update->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)));
		$migrated = $update->executeStatement();

		if ($migrated > 0) {
			$output->info('Pantry: seeded the recurrence default from the remembered "Once" choice for ' . $migrated . ' list(s)');
		}
	}
}
