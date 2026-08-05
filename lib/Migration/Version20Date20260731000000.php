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
 * Shopping Mode — the session + reminders schema.
 *
 * Five tables: a shopper's session, its checklist scope, its ordered store
 * sequence, its per-trip check log, and house-scoped reminders. Some columns
 * (`is_private`, `billed_*`, the `shopsess_items` and
 * `shopping_reminders` tables) are not read yet; they are created up front so
 * later work needs no follow-up migration.
 *
 * Booleans are declared `notnull => false` with a default: Doctrine binds PHP
 * false as NULL under NOT NULL, which the schema integrity check rejects.
 *
 * Index/constraint names are kept <= 30 chars for Oracle compatibility, and
 * primary keys carry explicit short names because the auto-derived
 * "oc_pantry_shopping_*_pkey" would exceed that limit. For the same reason the
 * session child tables use the `shopsess_` shorthand (for `shopping_session_`):
 * the full `oc_pantry_shopping_session_lists` (32) exceeds the 30-char table
 * name limit, whereas `oc_pantry_shopsess_lists` (24) fits.
 */
class Version20Date20260731000000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$sessions = Application::tableName('shopping_sessions');
		if (!$schema->hasTable($sessions)) {
			$table = $schema->createTable($sessions);
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('house_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('active_store_id', Types::BIGINT, ['notnull' => false, 'default' => null]);
			$table->addColumn('last_seen_at', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('closed_at', Types::BIGINT, ['notnull' => false, 'default' => null]);
			// Scope toggle: keep buy-anywhere items when narrowing by store.
			$table->addColumn('include_unassigned', Types::BOOLEAN, ['notnull' => false, 'default' => true]);
			// Per-session "shop privately" presence opt-out. Not read yet.
			$table->addColumn('is_private', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
			// Storeless billed-total fallback. Not read yet.
			$table->addColumn('billed_total', Types::FLOAT, ['notnull' => false, 'default' => null]);
			$table->addColumn('billed_currency', Types::STRING, ['notnull' => false, 'length' => 8, 'default' => null]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id'], 'pantry_shopsess_pk');
			$table->addIndex(['house_id'], 'pantry_shopsess_house');
			$table->addIndex(['user_id'], 'pantry_shopsess_user');
		}

		$lists = Application::tableName('shopsess_lists');
		if (!$schema->hasTable($lists)) {
			$table = $schema->createTable($lists);
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('session_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('list_id', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id'], 'pantry_shopsl_pk');
			$table->addUniqueIndex(['session_id', 'list_id'], 'pantry_shopsl_uniq');
			$table->addIndex(['session_id'], 'pantry_shopsl_sess');
		}

		$stores = Application::tableName('shopsess_stores');
		if (!$schema->hasTable($stores)) {
			$table = $schema->createTable($stores);
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('session_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('store_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('position', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			// Per-store billed actuals. Not read yet.
			$table->addColumn('billed_total', Types::FLOAT, ['notnull' => false, 'default' => null]);
			$table->addColumn('billed_currency', Types::STRING, ['notnull' => false, 'length' => 8, 'default' => null]);
			$table->setPrimaryKey(['id'], 'pantry_shopss_pk');
			$table->addIndex(['session_id'], 'pantry_shopss_sess');
		}

		$items = Application::tableName('shopsess_items');
		if (!$schema->hasTable($items)) {
			$table = $schema->createTable($items);
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('session_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('item_id', Types::BIGINT, ['notnull' => true]);
			// Active store when the item was checked; null if no store was active.
			$table->addColumn('store_id', Types::BIGINT, ['notnull' => false, 'default' => null]);
			$table->addColumn('checked_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id'], 'pantry_shopsi_pk');
			$table->addUniqueIndex(['session_id', 'item_id'], 'pantry_shopsi_uniq');
			$table->addIndex(['session_id'], 'pantry_shopsi_sess');
		}

		$reminders = Application::tableName('shopping_reminders');
		if (!$schema->hasTable($reminders)) {
			$table = $schema->createTable($reminders);
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('house_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('text', Types::TEXT, ['notnull' => true]);
			// Enum: on_start | on_close | on_store_advance. Named show_on because
			// "trigger" is a reserved SQL word.
			$table->addColumn('show_on', Types::STRING, ['notnull' => true, 'length' => 32]);
			$table->addColumn('position', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->addColumn('enabled', Types::BOOLEAN, ['notnull' => false, 'default' => true]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id'], 'pantry_shoprem_pk');
			$table->addIndex(['house_id'], 'pantry_shoprem_house');
		}

		return $schema;
	}
}
