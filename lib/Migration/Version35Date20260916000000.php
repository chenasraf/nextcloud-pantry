<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Migration;

use Closure;
use OCA\Pantry\AppInfo\Application;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Housemates joining a shopping trip.
 *
 * Membership is a join table rather than a column on the session, so a trip
 * stays one row: closing it closes it for everyone, and the check log in
 * `shopsess_items` needs no owner dimension.
 *
 * The starter is not given a row here — ownership already lives in
 * `shopping_sessions.user_id`, and materialising it would need a backfill for
 * every trip that predates this table. Membership therefore reads as
 * "owner, plus every row here whose left_at is null".
 *
 * The `shopsess_` shorthand and <= 30 char index names follow the sibling
 * session tables (Oracle identifier limit).
 */
class Version35Date20260916000000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		$members = Application::tableName('shopsess_members');
		if (!$schema->hasTable($members)) {
			$table = $schema->createTable($members);
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('session_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('joined_at', Types::BIGINT, ['notnull' => true]);
			// Set when a housemate steps out of a trip that is still running.
			// Rejoining reuses the row, so the pair stays unique.
			$table->addColumn('left_at', Types::BIGINT, ['notnull' => false, 'default' => null]);
			$table->setPrimaryKey(['id'], 'pantry_shopsm_pk');
			$table->addUniqueIndex(['session_id', 'user_id'], 'pantry_shopsm_uniq');
			$table->addIndex(['session_id'], 'pantry_shopsm_sess');
			$table->addIndex(['user_id'], 'pantry_shopsm_user');
		}

		return $schema;
	}
}
