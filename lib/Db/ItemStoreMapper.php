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
 * @template-extends QBMapper<ItemStore>
 */
class ItemStoreMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, Application::tableName('item_stores'), ItemStore::class);
	}

	/**
	 * @return int[]
	 */
	public function findStoreIdsForItem(int $itemId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('store_id')
			->from($this->getTableName())
			->where($qb->expr()->eq('item_id', $qb->createNamedParameter($itemId, IQueryBuilder::PARAM_INT)));
		return array_map('intval', $qb->executeQuery()->fetchAll(\PDO::FETCH_COLUMN));
	}

	/**
	 * Resolve the store ids attached to each of the given items in a single
	 * query. Items with no stores are absent from the returned map.
	 *
	 * @param int[] $itemIds
	 *
	 * @return array<int, int[]> item_id => store_ids
	 */
	public function findStoreIdsForItems(array $itemIds): array {
		if ($itemIds === []) {
			return [];
		}
		$itemIds = array_values(array_unique(array_map('intval', $itemIds)));
		$qb = $this->db->getQueryBuilder();
		$qb->select('item_id', 'store_id')
			->from($this->getTableName())
			->where($qb->expr()->in('item_id', $qb->createNamedParameter($itemIds, IQueryBuilder::PARAM_INT_ARRAY)));
		$result = $qb->executeQuery();
		$map = [];
		while ($row = $result->fetch()) {
			$map[(int)$row['item_id']][] = (int)$row['store_id'];
		}
		$result->closeCursor();
		return $map;
	}

	/**
	 * Replace the full set of stores attached to an item.
	 *
	 * @param int[] $storeIds
	 */
	public function setStoresForItem(int $itemId, array $storeIds): void {
		$this->deleteByItem($itemId);
		$unique = array_values(array_unique(array_map('intval', $storeIds)));
		foreach ($unique as $storeId) {
			$row = new ItemStore();
			$row->setItemId($itemId);
			$row->setStoreId($storeId);
			$this->insert($row);
		}
	}

	public function deleteByItem(int $itemId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('item_id', $qb->createNamedParameter($itemId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	public function deleteByStore(int $storeId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('store_id', $qb->createNamedParameter($storeId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * Remove every item-store row for the items belonging to a house. Used when
	 * a house is deleted (its lists and items are removed wholesale).
	 */
	public function deleteByHouse(int $houseId): void {
		$items = Application::tableName('list_items');
		$lists = Application::tableName('lists');
		$lookup = $this->db->getQueryBuilder();
		$lookup->select('i.id')
			->from($items, 'i')
			->innerJoin('i', $lists, 'l', $lookup->expr()->eq('i.list_id', 'l.id'))
			->where($lookup->expr()->eq('l.house_id', $lookup->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)));
		$itemIds = array_map('intval', $lookup->executeQuery()->fetchAll(\PDO::FETCH_COLUMN));
		if ($itemIds === []) {
			return;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->in('item_id', $qb->createNamedParameter($itemIds, IQueryBuilder::PARAM_INT_ARRAY)));
		$qb->executeStatement();
	}
}
