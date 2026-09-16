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
 * Per-store category order, used to walk a shop in aisle order.
 *
 * A row overrides `categories.sort_order` for one store; a category with no row
 * keeps its house-wide position, so a store that was never arranged needs no
 * rows at all.
 *
 * Short `scatord_` index names stay inside Oracle's 30-char identifier limit.
 */
class Version36Date20260916010000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		$table = Application::tableName('store_cat_order');
		if (!$schema->hasTable($table)) {
			$t = $schema->createTable($table);
			$t->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$t->addColumn('store_id', Types::BIGINT, ['notnull' => true]);
			$t->addColumn('category_id', Types::BIGINT, ['notnull' => true]);
			$t->addColumn('sort_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$t->setPrimaryKey(['id'], 'pantry_scatord_pkey');
			$t->addUniqueIndex(['store_id', 'category_id'], 'pantry_scatord_uniq');
			$t->addIndex(['category_id'], 'pantry_scatord_cat_idx');
		}

		return $schema;
	}
}
