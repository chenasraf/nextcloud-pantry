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
 * @template-extends QBMapper<ItemLabel>
 */
class ItemLabelMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, Application::tableName('item_labels'), ItemLabel::class);
	}

	/**
	 * @return int[]
	 */
	public function findLabelIdsForItem(int $itemId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('label_id')
			->from($this->getTableName())
			->where($qb->expr()->eq('item_id', $qb->createNamedParameter($itemId, IQueryBuilder::PARAM_INT)));
		return array_map('intval', $qb->executeQuery()->fetchAll(\PDO::FETCH_COLUMN));
	}

	/**
	 * Resolve the label ids attached to each of the given items in a single
	 * query. Items with no labels are absent from the returned map.
	 *
	 * @param int[] $itemIds
	 *
	 * @return array<int, int[]> item_id => label_ids
	 */
	public function findLabelIdsForItems(array $itemIds): array {
		if ($itemIds === []) {
			return [];
		}
		$itemIds = array_values(array_unique(array_map('intval', $itemIds)));
		$qb = $this->db->getQueryBuilder();
		$qb->select('item_id', 'label_id')
			->from($this->getTableName())
			->where($qb->expr()->in('item_id', $qb->createNamedParameter($itemIds, IQueryBuilder::PARAM_INT_ARRAY)));
		$result = $qb->executeQuery();
		$map = [];
		while ($row = $result->fetch()) {
			$map[(int)$row['item_id']][] = (int)$row['label_id'];
		}
		$result->closeCursor();
		return $map;
	}

	/**
	 * Replace the full set of labels attached to an item.
	 *
	 * @param int[] $labelIds
	 */
	public function setLabelsForItem(int $itemId, array $labelIds): void {
		$this->deleteByItem($itemId);
		$unique = array_values(array_unique(array_map('intval', $labelIds)));
		foreach ($unique as $labelId) {
			$row = new ItemLabel();
			$row->setItemId($itemId);
			$row->setLabelId($labelId);
			$this->insert($row);
		}
	}

	public function deleteByItem(int $itemId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('item_id', $qb->createNamedParameter($itemId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	public function deleteByLabel(int $labelId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('label_id', $qb->createNamedParameter($labelId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * Detach a label from items that live on a different list than the given
	 * one. Used when a label becomes scoped to a single list: items on other
	 * lists can no longer carry it. Bounded to the items that actually carry the
	 * label rather than scanning every item in the database.
	 */
	public function detachFromItemsNotInList(int $labelId, int $listId): void {
		$labeledItemIds = $this->findItemIdsForLabel($labelId);
		if ($labeledItemIds === []) {
			return;
		}

		// Of the label's own items, the ones not on the target list.
		$items = Application::tableName('list_items');
		$lookup = $this->db->getQueryBuilder();
		$lookup->select('id')
			->from($items)
			->where($lookup->expr()->in('id', $lookup->createNamedParameter($labeledItemIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($lookup->expr()->neq('list_id', $lookup->createNamedParameter($listId, IQueryBuilder::PARAM_INT)));
		$toDetach = array_map('intval', $lookup->executeQuery()->fetchAll(\PDO::FETCH_COLUMN));
		if ($toDetach === []) {
			return;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('label_id', $qb->createNamedParameter($labelId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->in('item_id', $qb->createNamedParameter($toDetach, IQueryBuilder::PARAM_INT_ARRAY)));
		$qb->executeStatement();
	}

	/**
	 * @return int[] Item ids currently carrying the given label.
	 */
	private function findItemIdsForLabel(int $labelId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('item_id')
			->from($this->getTableName())
			->where($qb->expr()->eq('label_id', $qb->createNamedParameter($labelId, IQueryBuilder::PARAM_INT)));
		return array_map('intval', $qb->executeQuery()->fetchAll(\PDO::FETCH_COLUMN));
	}

	/**
	 * Remove every item-label row for the items belonging to a house. Used when
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
