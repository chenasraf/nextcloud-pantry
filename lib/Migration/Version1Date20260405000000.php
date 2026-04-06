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
 * Initial schema for Pantry: houses, members, shopping lists, list items.
 */
class Version1Date20260405000000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// ---- pantry_houses ----
		$housesTable = Application::tableName('houses');
		if (!$schema->hasTable($housesTable)) {
			$table = $schema->createTable($housesTable);
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('name', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('description', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('owner_uid', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('created_at', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('updated_at', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['owner_uid'], 'pantry_houses_owner_idx');
		}

		// ---- pantry_house_members ----
		$membersTable = Application::tableName('house_members');
		if (!$schema->hasTable($membersTable)) {
			$table = $schema->createTable($membersTable);
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('house_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('role', Types::STRING, [
				'notnull' => true,
				'length' => 16,
			]);
			$table->addColumn('joined_at', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['house_id', 'user_id'], 'pantry_members_house_user_uq');
			$table->addIndex(['user_id'], 'pantry_members_user_idx');
		}

		// ---- pantry_lists ----
		$listsTable = Application::tableName('lists');
		if (!$schema->hasTable($listsTable)) {
			$table = $schema->createTable($listsTable);
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('house_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('name', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('description', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('sort_order', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('created_at', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('updated_at', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['house_id'], 'pantry_lists_house_idx');
		}

		// ---- pantry_categories ----
		$categoriesTable = Application::tableName('categories');
		if (!$schema->hasTable($categoriesTable)) {
			$table = $schema->createTable($categoriesTable);
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('house_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('name', Types::STRING, [
				'notnull' => true,
				'length' => 128,
			]);
			$table->addColumn('icon', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('color', Types::STRING, [
				'notnull' => true,
				'length' => 16,
			]);
			$table->addColumn('sort_order', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('created_at', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('updated_at', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['house_id', 'name'], 'pantry_cat_house_name_uq');
		}

		// ---- pantry_list_items ----
		$itemsTable = Application::tableName('list_items');
		if (!$schema->hasTable($itemsTable)) {
			$table = $schema->createTable($itemsTable);
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('list_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('name', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('category_id', Types::BIGINT, [
				'notnull' => false,
				'length' => 20,
			]);
			$table->addColumn('quantity', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('bought', Types::BOOLEAN, [
				'notnull' => false,
			]);
			$table->addColumn('bought_at', Types::BIGINT, [
				'notnull' => false,
				'length' => 20,
			]);
			$table->addColumn('bought_by', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('rrule', Types::STRING, [
				'notnull' => false,
				'length' => 512,
			]);
			$table->addColumn('repeat_from_completion', Types::BOOLEAN, [
				'notnull' => false,
			]);
			$table->addColumn('next_due_at', Types::BIGINT, [
				'notnull' => false,
				'length' => 20,
			]);
			$table->addColumn('image_file_id', Types::BIGINT, [
				'notnull' => false,
				'length' => 20,
			]);
			$table->addColumn('sort_order', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('created_at', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('updated_at', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['list_id'], 'pantry_items_list_idx');
			$table->addIndex(['category_id'], 'pantry_items_cat_idx');
		} else {
			// Idempotent adds for columns introduced after the initial create
			// (covers early-dev deployments where the table already existed).
			$table = $schema->getTable($itemsTable);
			if (!$table->hasColumn('image_file_id')) {
				$table->addColumn('image_file_id', Types::BIGINT, [
					'notnull' => false,
					'length' => 20,
				]);
			}
		}

		return $schema;
	}
}
