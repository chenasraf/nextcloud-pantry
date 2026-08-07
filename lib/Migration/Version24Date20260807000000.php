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
 * Per-trip skip list: items a shopper removes from the current shopping session
 * only. A row means "hide this in-scope item for this trip"; it does not touch
 * the checklist item (still on the list, not marked done). Rows are ephemeral —
 * dropped when the session is purged.
 */
class Version24Date20260807000000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$skips = Application::tableName('shopsess_skips');
		if (!$schema->hasTable($skips)) {
			$table = $schema->createTable($skips);
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('session_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('item_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id'], 'pantry_shopsk_pk');
			$table->addUniqueIndex(['session_id', 'item_id'], 'pantry_shopsk_uniq');
			$table->addIndex(['session_id'], 'pantry_shopsk_sess');
		}

		return $schema;
	}
}
