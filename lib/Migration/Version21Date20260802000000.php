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
 * Shopping Mode — freeze a per-trip item snapshot on the check log.
 *
 * The review/history read an item's name + price off the live checklist item,
 * so hard-deleting an item (emptying trash) erased it from finished trips. This
 * adds snapshot columns to shopping_session_items; the session-close path fills
 * them from the item at close time, and closed trips render from the snapshot —
 * so a finished trip is self-contained and survives the item being edited,
 * un-done, or deleted.
 *
 * Existing rows are backfilled from the current item (best effort — the true
 * at-close values of past trips are unrecoverable; rows whose item is already
 * gone stay null and are skipped by the review).
 */
class Version21Date20260802000000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $connection,
	) {
	}

	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$items = Application::tableName('shopping_session_items');
		if (!$schema->hasTable($items)) {
			return null;
		}
		$table = $schema->getTable($items);

		if (!$table->hasColumn('item_name')) {
			$table->addColumn('item_name', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => null]);
		}
		if (!$table->hasColumn('quantity')) {
			$table->addColumn('quantity', Types::STRING, ['notnull' => false, 'length' => 64, 'default' => null]);
		}
		if (!$table->hasColumn('price_type')) {
			$table->addColumn('price_type', Types::STRING, ['notnull' => false, 'length' => 8, 'default' => null]);
		}
		if (!$table->hasColumn('price_min')) {
			$table->addColumn('price_min', Types::FLOAT, ['notnull' => false, 'default' => null]);
		}
		if (!$table->hasColumn('price_max')) {
			$table->addColumn('price_max', Types::FLOAT, ['notnull' => false, 'default' => null]);
		}
		if (!$table->hasColumn('price_currency')) {
			$table->addColumn('price_currency', Types::STRING, ['notnull' => false, 'length' => 8, 'default' => null]);
		}

		return $schema;
	}

	/**
	 * Backfill the snapshot on existing check-log rows from their current item.
	 *
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$logs = Application::tableName('shopping_session_items');
		$items = Application::tableName('list_items');

		$select = $this->connection->getQueryBuilder();
		$select->select('si.id', 'i.name', 'i.quantity', 'i.price_type', 'i.price_min', 'i.price_max', 'i.price_currency')
			->from($logs, 'si')
			->innerJoin('si', $items, 'i', $select->expr()->eq('si.item_id', 'i.id'))
			->where($select->expr()->isNull('si.item_name'));
		$result = $select->executeQuery();

		$updated = 0;
		while ($row = $result->fetch()) {
			$update = $this->connection->getQueryBuilder();
			$update->update($logs)
				->set('item_name', $update->createNamedParameter($row['name']))
				->set('quantity', $update->createNamedParameter($row['quantity']))
				->set('price_type', $update->createNamedParameter($row['price_type']))
				->set('price_min', $update->createNamedParameter($row['price_min']))
				->set('price_max', $update->createNamedParameter($row['price_max']))
				->set('price_currency', $update->createNamedParameter($row['price_currency']))
				->where($update->expr()->eq('id', $update->createNamedParameter($row['id'], IQueryBuilder::PARAM_INT)));
			$update->executeStatement();
			$updated++;
		}
		$result->closeCursor();

		if ($updated > 0) {
			$output->info('Pantry: backfilled shopping check-log snapshot for ' . $updated . ' row(s)');
		}
	}
}
