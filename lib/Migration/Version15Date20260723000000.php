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
 * Reconcile schema that earlier migrations declared but that never actually
 * landed on some instances.
 *
 * On certain upgrades a migration gets recorded as executed in oc_migrations
 * while its schema changes are missing (partial/interrupted upgrade). Because
 * the version is already marked executed, the original migration will not run
 * again, so the drift is permanent until reconciled. The item-list queries
 * filter on archived_at and read pantry_item_stores on every request, so the
 * missing schema takes the whole checklist down with a hard SQL error.
 *
 * This migration re-asserts the affected objects — the archived_at column and
 * its index (Version13) and the store tables (Version14) — but only when they
 * are actually missing, so it is a no-op on healthy installs and safe on
 * instances that were patched by hand.
 */
class Version15Date20260723000000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$itemsTable = Application::tableName('list_items');
		if ($schema->hasTable($itemsTable)) {
			$table = $schema->getTable($itemsTable);
			if (!$table->hasColumn('archived_at')) {
				$output->warning('Pantry: list_items.archived_at column was missing; recreating it');
				$table->addColumn('archived_at', Types::BIGINT, [
					'notnull' => false,
					'length' => 20,
				]);
			}
			if (!$table->hasIndex('pantry_items_archived_idx')) {
				$table->addIndex(['archived_at'], 'pantry_items_archived_idx');
			}
		}

		$storesTable = Application::tableName('stores');
		if (!$schema->hasTable($storesTable)) {
			$output->warning('Pantry: stores table was missing; recreating it');
			$table = $schema->createTable($storesTable);
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('house_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 128]);
			$table->addColumn('icon', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('color', Types::STRING, ['notnull' => true, 'length' => 16]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['house_id', 'name'], 'pantry_store_house_name_uq');
		}

		$itemStoresTable = Application::tableName('item_stores');
		if (!$schema->hasTable($itemStoresTable)) {
			$output->warning('Pantry: item_stores table was missing; recreating it');
			$table = $schema->createTable($itemStoresTable);
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('item_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('store_id', Types::BIGINT, ['notnull' => true]);
			// Explicit short index names: the auto-derived names for
			// "oc_pantry_item_stores" exceed Nextcloud's 30-char limit.
			$table->setPrimaryKey(['id'], 'pantry_istore_pkey');
			$table->addUniqueIndex(['item_id', 'store_id'], 'pantry_istore_uniq');
			$table->addIndex(['store_id'], 'pantry_istore_store_idx');
		}

		return $schema;
	}
}
