<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCA\Pantry\AppInfo\Application;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<ItemQuantityMemory>
 */
class ItemQuantityMemoryMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, Application::tableName('item_qty_memory'), ItemQuantityMemory::class);
	}

	/**
	 * Remember an amount for a name on a list, or forget it with null.
	 */
	public function put(int $houseId, int $listId, string $nameKey, ?string $quantity, int $now): void {
		if ($this->setQuantity($listId, $nameKey, $quantity, $now) > 0) {
			return;
		}
		$row = new ItemQuantityMemory();
		$row->setHouseId($houseId);
		$row->setListId($listId);
		$row->setNameKey($nameKey);
		$row->setQuantity($quantity);
		$row->setUpdatedAt($now);
		try {
			$this->insert($row);
		} catch (Exception $e) {
			// Another request created the row between the update and the insert.
			if ($e->getReason() !== Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw $e;
			}
			$this->setQuantity($listId, $nameKey, $quantity, $now);
		}
	}

	/**
	 * @return list<ItemQuantityMemory>
	 */
	public function findForHouse(int $houseId, ?int $since = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)))
			->orderBy('id', 'ASC');
		if ($since !== null) {
			$qb->andWhere($qb->expr()->gte('updated_at', $qb->createNamedParameter($since, IQueryBuilder::PARAM_INT)));
		}
		return array_values($this->findEntities($qb));
	}

	public function hasAnyForHouse(int $houseId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$found = $result->fetchOne() !== false;
		$result->closeCursor();
		return $found;
	}

	public function deleteByList(int $listId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	public function deleteByHouse(int $houseId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	private function setQuantity(int $listId, string $nameKey, ?string $quantity, int $now): int {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('quantity', $qb->createNamedParameter($quantity))
			->set('updated_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('name_key', $qb->createNamedParameter($nameKey)));
		return $qb->executeStatement();
	}
}
