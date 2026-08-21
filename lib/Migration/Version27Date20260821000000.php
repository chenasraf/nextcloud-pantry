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
 * Bind categories to an optional list. A null list_id keeps the category
 * global (offered on every list); a set list_id scopes it to that one list.
 * Name uniqueness moves from per-house to per-scope (house_id + list_id).
 */
class Version27Date20260821000000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$categoriesTable = Application::tableName('categories');
		if (!$schema->hasTable($categoriesTable)) {
			return null;
		}
		$table = $schema->getTable($categoriesTable);

		if (!$table->hasColumn('list_id')) {
			$table->addColumn('list_id', Types::BIGINT, [
				'notnull' => false,
				'length' => 20,
			]);
		}

		if (!$table->hasIndex('pantry_cat_list_idx')) {
			$table->addIndex(['list_id'], 'pantry_cat_list_idx');
		}

		if ($table->hasIndex('pantry_cat_house_name_uq')) {
			$table->dropIndex('pantry_cat_house_name_uq');
		}
		if (!$table->hasIndex('pantry_cat_scope_name_uq')) {
			$table->addUniqueIndex(['house_id', 'list_id', 'name'], 'pantry_cat_scope_name_uq');
		}

		return $schema;
	}
}
