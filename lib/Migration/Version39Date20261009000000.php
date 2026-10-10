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
 * Add the per-list item defaults and carry each list's recurrence default
 * into them. The recurrence columns are dropped by
 * {@see Version40Date20261009010000} once their values live here.
 */
class Version39Date20261009000000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $connection,
	) {
	}

	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		$listsTable = Application::tableName('lists');
		if (!$schema->hasTable($listsTable)) {
			return null;
		}
		$table = $schema->getTable($listsTable);
		if ($table->hasColumn('item_defaults')) {
			return null;
		}
		$table->addColumn('item_defaults', Types::TEXT, [
			'notnull' => false,
			'default' => null,
		]);

		return $schema;
	}

	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$listsTable = Application::tableName('lists');

		$schema = $schemaClosure();
		if (!$schema->hasTable($listsTable)) {
			return;
		}
		$table = $schema->getTable($listsTable);
		if (!$table->hasColumn('item_defaults') || !$table->hasColumn('default_recurrence_mode')) {
			return;
		}

		$select = $this->connection->getQueryBuilder();
		$select->select('id', 'default_recurrence_mode', 'default_recurrence_kind', 'default_rrule', 'default_repeat_from_completion')
			->from($listsTable)
			->where($select->expr()->isNull('item_defaults'));
		$result = $select->executeQuery();

		$update = $this->connection->getQueryBuilder();
		$update->update($listsTable)
			->set('item_defaults', $update->createParameter('defaults'))
			->where($update->expr()->eq('id', $update->createParameter('id')));

		$migrated = 0;
		while ($row = $result->fetch()) {
			$recurrence = self::recurrenceEntry(
				(string)($row['default_recurrence_mode'] ?? 'remember'),
				(string)($row['default_recurrence_kind'] ?? 'none'),
				$row['default_rrule'] !== null ? (string)$row['default_rrule'] : null,
				(bool)($row['default_repeat_from_completion'] ?? false),
			);
			if ($recurrence === null) {
				continue;
			}
			$update->setParameter('defaults', json_encode(['recurrence' => $recurrence], JSON_THROW_ON_ERROR), IQueryBuilder::PARAM_STR);
			$update->setParameter('id', (int)$row['id'], IQueryBuilder::PARAM_INT);
			$update->executeStatement();
			$migrated++;
		}
		$result->closeCursor();

		if ($migrated > 0) {
			$output->info('Pantry: moved the recurrence default into item defaults for ' . $migrated . ' list(s)');
		}
	}

	/**
	 * A list pinned to staples needs no entry: an empty composer adds staples anyway.
	 *
	 * @return array{mode: string, value: array{kind: string, rrule: string|null, repeatFromCompletion: bool}}|null
	 */
	public static function recurrenceEntry(string $mode, string $kind, ?string $rrule, bool $repeatFromCompletion): ?array {
		if ($mode === 'remember') {
			$entryMode = 'remember';
			if (!in_array($kind, ['none', 'once', 'recurring'], true)) {
				$kind = 'none';
			}
		} elseif ($mode === 'once' || $mode === 'recurring') {
			$entryMode = 'fixed';
			$kind = $mode;
		} else {
			return null;
		}
		$recurring = $kind === 'recurring';
		$value = [
			'kind' => $kind,
			'rrule' => $recurring ? ($rrule ?? 'FREQ=WEEKLY;INTERVAL=1') : null,
			'repeatFromCompletion' => $recurring && $repeatFromCompletion,
		];
		return ['mode' => $entryMode, 'value' => $value];
	}
}
