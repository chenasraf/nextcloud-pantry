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
 * adds snapshot columns to shopsess_items; the session-close path fills
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

		$items = Application::tableName('shopsess_items');
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
	 * The price_* columns on list_items come from Version19. If schema drift
	 * left them missing, selecting them here would abort the whole
	 * upgrade, so only the columns that actually exist are read; the absent ones
	 * stay null on the snapshot and RepairSchemaColumnsStep restores them after
	 * migrations finish.
	 *
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$logs = Application::tableName('shopsess_items');
		$items = Application::tableName('list_items');

		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable($items)) {
			return;
		}
		$itemsTable = $schema->getTable($items);

		// name/quantity predate the price columns and are always present; the
		// price columns are read only where the source column actually exists.
		$snapshot = [
			'item_name' => 'name',
			'quantity' => 'quantity',
			'price_type' => 'price_type',
			'price_min' => 'price_min',
			'price_max' => 'price_max',
			'price_currency' => 'price_currency',
		];
		$snapshot = array_filter(
			$snapshot,
			fn (string $source): bool => $itemsTable->hasColumn($source),
		);

		$select = $this->connection->getQueryBuilder();
		$select->select('si.id')
			->addSelect(array_map(fn (string $source): string => 'i.' . $source, array_values($snapshot)))
			->from($logs, 'si')
			->innerJoin('si', $items, 'i', $select->expr()->eq('si.item_id', 'i.id'))
			->where($select->expr()->isNull('si.item_name'));
		$result = $select->executeQuery();

		$updated = 0;
		while ($row = $result->fetch()) {
			$update = $this->connection->getQueryBuilder();
			$update->update($logs);
			foreach ($snapshot as $target => $source) {
				$update->set($target, $update->createNamedParameter($row[$source]));
			}
			$update->where($update->expr()->eq('id', $update->createNamedParameter($row['id'], IQueryBuilder::PARAM_INT)));
			$update->executeStatement();
			$updated++;
		}
		$result->closeCursor();

		if ($updated > 0) {
			$output->info('Pantry: backfilled shopping check-log snapshot for ' . $updated . ' row(s)');
		}
	}
}
