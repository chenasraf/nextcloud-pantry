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
 * Introduce user-defined stores and a many-to-many relation to checklist items.
 *
 * - pantry_stores: per-house list of shops (name + icon + color), no custom sort.
 * - pantry_item_stores: many-to-many between checklist items and stores.
 */
class Version14Date20260720000000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$storesTable = Application::tableName('stores');
		if (!$schema->hasTable($storesTable)) {
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
