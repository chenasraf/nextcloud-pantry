<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Migration;

use OC\DB\Connection;
use OC\DB\SchemaWrapper;

/**
 * Recreates optional columns declared in {@see ExpectedColumns} that schema
 * drift has left missing.
 *
 * Nextcloud records a migration as executed against the app's version even when
 * one of its `addColumn()` calls never reached the database.
 * Because the migration is marked done it is never re-run, so the column stays
 * missing and every query that references it fails — including the
 * Shopping Mode backfill inside Version21, which blocks the whole upgrade.
 *
 * This mirrors core's `occ db:add-missing-columns`, but also drops the leftover
 * columns a later migration meant to remove ({@see LegacyColumns}) — those make
 * `SELECT *` hydration throw once the entity stops declaring them, and core has
 * no equivalent for dropping. It only ever adds nullable columns an earlier
 * migration already intended or drops columns a later migration already meant to
 * remove, so it is safe to run repeatedly and is a no-op once the schema is whole.
 */
class SchemaRepairService {
	public function __construct(
		private Connection $connection,
	) {
	}

	/**
	 * @return SchemaRepairResult
	 */
	public function repair(bool $dryRun = false): SchemaRepairResult {
		$schema = new SchemaWrapper($this->connection);

		$added = [];
		$dropped = [];
		$missingTables = [];

		foreach (ExpectedColumns::all() as $column) {
			if (!$schema->hasTable($column['table'])) {
				if (!in_array($column['table'], $missingTables, true)) {
					$missingTables[] = $column['table'];
				}
				continue;
			}

			$table = $schema->getTable($column['table']);
			if ($table->hasColumn($column['name'])) {
				continue;
			}

			$table->addColumn($column['name'], $column['type'], $column['options']);
			$added[] = $column['table'] . '.' . $column['name'];
		}

		foreach (LegacyColumns::all() as $column) {
			if (!$schema->hasTable($column['table'])) {
				continue;
			}

			$table = $schema->getTable($column['table']);
			if (!$table->hasColumn($column['name'])) {
				continue;
			}

			$table->dropColumn($column['name']);
			$dropped[] = $column['table'] . '.' . $column['name'];
		}

		$sql = '';
		if ($added !== [] || $dropped !== []) {
			$result = $this->connection->migrateToSchema($schema->getWrappedSchema(), $dryRun);
			if ($dryRun && is_string($result)) {
				$sql = $result;
			}
		}

		return new SchemaRepairResult($added, $missingTables, $sql, $dryRun, $dropped);
	}
}
