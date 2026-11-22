<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Migration;

use Closure;
use OCA\Pantry\AppInfo\Application;
use OCA\Pantry\Db\HouseMapper;
use OCA\Pantry\Service\ItemMemoryService;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Item memory: the category and amount each household picks for an item, so
 * clients can suggest them the next time the item is typed in.
 *
 * - pantry_item_cat_memory: per house, item name and category, how often the
 *   category was chosen for that name and how often it was turned down.
 * - pantry_item_qty_memory: per list and item name, the amount last chosen.
 *
 * Both are seeded from the items every house already has.
 */
class Version54Date20261122000000 extends SimpleMigrationStep {
	public function __construct(
		private HouseMapper $houses,
		private ItemMemoryService $memory,
	) {
	}

	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$changed = false;

		$catTable = Application::tableName('item_cat_memory');
		if (!$schema->hasTable($catTable)) {
			$table = $schema->createTable($catTable);
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('house_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('name_key', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('category_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('uses', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->addColumn('rejections', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['house_id', 'name_key', 'category_id'], 'pantry_icm_uniq');
			$table->addIndex(['house_id', 'updated_at'], 'pantry_icm_house_upd');
			$table->addIndex(['category_id'], 'pantry_icm_cat');
			$changed = true;
		}

		$qtyTable = Application::tableName('item_qty_memory');
		if (!$schema->hasTable($qtyTable)) {
			$table = $schema->createTable($qtyTable);
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('house_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('list_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('name_key', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('quantity', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['list_id', 'name_key'], 'pantry_iqm_uniq');
			$table->addIndex(['house_id', 'updated_at'], 'pantry_iqm_house_upd');
			$changed = true;
		}

		return $changed ? $schema : null;
	}

	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$schema = $schemaClosure();
		if (!$schema->hasTable(Application::tableName('item_cat_memory'))
			|| !$schema->hasTable(Application::tableName('item_qty_memory'))) {
			return;
		}

		$seeded = 0;
		foreach ($this->houses->findAll() as $house) {
			if ($this->memory->seedIfEmpty((int)$house->getId())) {
				$seeded++;
			}
		}
		if ($seeded > 0) {
			$output->info('Pantry: built item memory for ' . $seeded . ' house(s)');
		}
	}
}
