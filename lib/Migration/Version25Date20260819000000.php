<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Migration;

use Closure;
use OCA\Pantry\AppInfo\Application;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Move item prices off the item row into a related one-to-many table so a price
 * can vary by store.
 *
 * - pantry_item_prices: one row per (item, store); a null store id is the item's
 *   single store-less (default) price. At most one price per (item, store) and at
 *   most one store-less price.
 *
 * Backfills each existing item's single price as a store-less price row. The old
 * list_items price columns are left in place here and dropped by the follow-up
 * {@see Version26Date20260819100000} once the backfill has landed.
 */
class Version25Date20260819000000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $db,
	) {
	}

	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = Application::tableName('item_prices');
		if (!$schema->hasTable($table)) {
			$t = $schema->createTable($table);
			$t->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$t->addColumn('item_id', Types::BIGINT, ['notnull' => true]);
			$t->addColumn('store_id', Types::BIGINT, ['notnull' => false, 'default' => null]);
			$t->addColumn('price_type', Types::STRING, ['notnull' => false, 'length' => 8, 'default' => null]);
			$t->addColumn('price_min', Types::FLOAT, ['notnull' => false, 'default' => null]);
			$t->addColumn('price_max', Types::FLOAT, ['notnull' => false, 'default' => null]);
			$t->addColumn('price_currency', Types::STRING, ['notnull' => false, 'length' => 8, 'default' => null]);
			// Explicit short index names: the auto-derived names for
			// "oc_pantry_item_prices" exceed Nextcloud's 30-char limit.
			$t->setPrimaryKey(['id'], 'pantry_iprice_pkey');
			$t->addUniqueIndex(['item_id', 'store_id'], 'pantry_iprice_uniq');
			$t->addIndex(['item_id'], 'pantry_iprice_item_idx');
			$t->addIndex(['store_id'], 'pantry_iprice_store_idx');
		}

		return $schema;
	}

	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$pricesTable = Application::tableName('item_prices');

		// Idempotent re-run guard: skip if any price row already exists.
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'cnt'))->from($pricesTable);
		if ((int)$qb->executeQuery()->fetchOne() > 0) {
			return;
		}

		$itemsTable = Application::tableName('list_items');
		$select = $this->db->getQueryBuilder();
		$select->select('id', 'price_type', 'price_min', 'price_max', 'price_currency')
			->from($itemsTable)
			->where($select->expr()->in('price_type', $select->createNamedParameter(['set', 'range'], IQueryBuilder::PARAM_STR_ARRAY)))
			->andWhere($select->expr()->isNotNull('price_min'));
		$rows = $select->executeQuery()->fetchAll();

		foreach ($rows as $row) {
			$insert = $this->db->getQueryBuilder();
			$insert->insert($pricesTable)->values([
				'item_id' => $insert->createNamedParameter((int)$row['id'], IQueryBuilder::PARAM_INT),
				'store_id' => $insert->createNamedParameter(null, IQueryBuilder::PARAM_NULL),
				'price_type' => $insert->createNamedParameter($row['price_type'], IQueryBuilder::PARAM_STR),
				'price_min' => $insert->createNamedParameter($row['price_min']),
				'price_max' => $insert->createNamedParameter($row['price_max']),
				'price_currency' => $insert->createNamedParameter($row['price_currency'], IQueryBuilder::PARAM_STR),
			]);
			$insert->executeStatement();
		}
	}
}
