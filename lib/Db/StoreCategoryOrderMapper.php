<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCA\Pantry\AppInfo\Application;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<StoreCategoryOrder>
 */
class StoreCategoryOrderMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, Application::tableName('store_cat_order'), StoreCategoryOrder::class);
	}

	/**
	 * A store's arrangement, in the order it is walked.
	 *
	 * @return StoreCategoryOrder[]
	 */
	public function findByStore(int $storeId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('store_id', $qb->createNamedParameter($storeId, IQueryBuilder::PARAM_INT)))
			->orderBy('sort_order', 'ASC')
			->addOrderBy('category_id', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * Replace a store's arrangement with the given category ids, in order.
	 * An empty list drops the arrangement, returning the store to the
	 * house-wide category order.
	 *
	 * @param list<int> $categoryIds
	 */
	public function replaceForStore(int $storeId, array $categoryIds): void {
		$this->deleteByStore($storeId);
		foreach (array_values($categoryIds) as $position => $categoryId) {
			$row = new StoreCategoryOrder();
			$row->setStoreId($storeId);
			$row->setCategoryId($categoryId);
			$row->setSortOrder($position);
			$this->insert($row);
		}
	}

	public function deleteByStore(int $storeId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('store_id', $qb->createNamedParameter($storeId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	public function deleteByCategory(int $categoryId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('category_id', $qb->createNamedParameter($categoryId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * Remove every arrangement belonging to a house's stores. Used when a house
	 * is deleted, before its stores and categories go.
	 */
	public function deleteByHouse(int $houseId): void {
		$lookup = $this->db->getQueryBuilder();
		$lookup->select('id')
			->from(Application::tableName('stores'))
			->where($lookup->expr()->eq('house_id', $lookup->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)));
		$result = $lookup->executeQuery();
		$storeIds = array_map('intval', $result->fetchAll(\PDO::FETCH_COLUMN));
		$result->closeCursor();
		if ($storeIds === []) {
			return;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->in('store_id', $qb->createNamedParameter($storeIds, IQueryBuilder::PARAM_INT_ARRAY)));
		$qb->executeStatement();
	}
}
