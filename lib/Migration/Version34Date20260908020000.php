<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Migration;

use Closure;
use OCA\Pantry\AppInfo\Application;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Give every category in a house a distinct sort_order.
 *
 * Categories sharing a sort_order have no defined order against each other, so
 * their items interleave wherever a query clusters by category, and a drag
 * reorder cannot break the tie for them either, since it renumbers positions
 * rather than inventing new ones.
 *
 * The rank runs over the house as a whole, grouped by list scope and then by
 * (sort_order, name): one sequence, in the same shape the category manager
 * writes back after a drag, so every category a list can display holds a
 * position of its own. Categories with a deliberate order keep their relative
 * places within their group.
 */
class Version34Date20260908020000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $connection,
	) {
	}

	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$categoriesTable = Application::tableName('categories');

		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable($categoriesTable)) {
			return;
		}

		$select = $this->connection->getQueryBuilder();
		$select->select('id', 'house_id', 'sort_order')
			->from($categoriesTable)
			// Global categories lead, then one contiguous run per list — the
			// shape a drag in the category manager writes back. An explicit
			// CASE because databases disagree on where NULLs sort.
			->orderBy('house_id', 'ASC')
			->addOrderBy($select->createFunction('CASE WHEN list_id IS NULL THEN 0 ELSE 1 END'), 'ASC')
			->addOrderBy('list_id', 'ASC')
			->addOrderBy('sort_order', 'ASC')
			->addOrderBy('name', 'ASC')
			->addOrderBy('id', 'ASC');
		$result = $select->executeQuery();
		/** @var list<array{id: int|string, house_id: int|string, sort_order: int|string}> $rows */
		$rows = $result->fetchAll();
		$result->closeCursor();

		$update = $this->connection->getQueryBuilder();
		$update->update($categoriesTable)
			->set('sort_order', $update->createParameter('sortOrder'))
			->where($update->expr()->eq('id', $update->createParameter('id')));

		$houseId = null;
		$rank = 0;
		$renumbered = 0;
		foreach ($rows as $row) {
			$rowHouseId = (int)$row['house_id'];
			if ($rowHouseId !== $houseId) {
				$houseId = $rowHouseId;
				$rank = 0;
			}
			// updated_at stays put: the visible order does not change here, so
			// this is not an edit anyone made to the category.
			if ((int)$row['sort_order'] !== $rank) {
				$update->setParameter('sortOrder', $rank, IQueryBuilder::PARAM_INT);
				$update->setParameter('id', (int)$row['id'], IQueryBuilder::PARAM_INT);
				$update->executeStatement();
				$renumbered++;
			}
			$rank++;
		}

		if ($renumbered > 0) {
			$output->info('Pantry: gave ' . $renumbered . ' categories a distinct sort order');
		}
	}
}
