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
 * @template-extends QBMapper<ItemPrice>
 */
class ItemPriceMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, Application::tableName('item_prices'), ItemPrice::class);
	}

	/**
	 * The item's prices, each in its serialized shape.
	 *
	 * @return list<array{storeId: ?int, priceType: ?string, priceMin: ?float, priceMax: ?float, priceCurrency: ?string}>
	 */
	public function findForItem(int $itemId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('item_id', $qb->createNamedParameter($itemId, IQueryBuilder::PARAM_INT)));
		return array_map(
			static fn (ItemPrice $p) => $p->jsonSerialize(),
			$this->findEntities($qb),
		);
	}

	/**
	 * Resolve the prices attached to each of the given items in a single query.
	 * Items with no prices are absent from the returned map.
	 *
	 * @param int[] $itemIds
	 *
	 * @return array<int, list<array{storeId: ?int, priceType: ?string, priceMin: ?float, priceMax: ?float, priceCurrency: ?string}>>
	 */
	public function findForItems(array $itemIds): array {
		if ($itemIds === []) {
			return [];
		}
		$itemIds = array_values(array_unique(array_map('intval', $itemIds)));
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->in('item_id', $qb->createNamedParameter($itemIds, IQueryBuilder::PARAM_INT_ARRAY)));
		$map = [];
		foreach ($this->findEntities($qb) as $price) {
			$map[(int)$price->getItemId()][] = $price->jsonSerialize();
		}
		return $map;
	}

	/**
	 * Replace the full set of prices attached to an item. Entries are keyed by
	 * store id (a null store id is the single store-less price), so at most one
	 * price survives per store and at most one store-less price.
	 *
	 * @param list<array{storeId?: ?int, priceType?: ?string, priceMin?: ?float, priceMax?: ?float, priceCurrency?: ?string}> $prices
	 */
	public function setPricesForItem(int $itemId, array $prices): void {
		$this->deleteByItem($itemId);
		$byStore = [];
		foreach ($prices as $price) {
			$storeId = isset($price['storeId']) && $price['storeId'] !== null ? (int)$price['storeId'] : null;
			$byStore[$storeId === null ? 'none' : $storeId] = [
				'storeId' => $storeId,
				'priceType' => $price['priceType'] ?? null,
				'priceMin' => $price['priceMin'] ?? null,
				'priceMax' => $price['priceMax'] ?? null,
				'priceCurrency' => $price['priceCurrency'] ?? null,
			];
		}
		foreach ($byStore as $price) {
			$row = new ItemPrice();
			$row->setItemId($itemId);
			$row->setStoreId($price['storeId']);
			$row->setPriceType($price['priceType']);
			$row->setPriceMin($price['priceMin']);
			$row->setPriceMax($price['priceMax']);
			$row->setPriceCurrency($price['priceCurrency']);
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
	 * Remove every price row for the items belonging to a house. Used when a
	 * house is deleted (its lists and items are removed wholesale).
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
