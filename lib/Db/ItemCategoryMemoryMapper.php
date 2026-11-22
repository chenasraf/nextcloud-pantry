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
 * @template-extends QBMapper<ItemCategoryMemory>
 */
class ItemCategoryMemoryMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, Application::tableName('item_cat_memory'), ItemCategoryMemory::class);
	}

	/**
	 * Add to a row's counts, creating it when the name has never met this
	 * category before.
	 */
	public function bump(int $houseId, string $nameKey, int $categoryId, int $uses, int $rejections, int $now): void {
		if ($this->addCounts($houseId, $nameKey, $categoryId, $uses, $rejections, $now) > 0) {
			return;
		}
		$row = new ItemCategoryMemory();
		$row->setHouseId($houseId);
		$row->setNameKey($nameKey);
		$row->setCategoryId($categoryId);
		$row->setUses($uses);
		$row->setRejections($rejections);
		$row->setUpdatedAt($now);
		try {
			$this->insert($row);
		} catch (Exception $e) {
			// Another request created the row between the update and the insert.
			if ($e->getReason() !== Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw $e;
			}
			$this->addCounts($houseId, $nameKey, $categoryId, $uses, $rejections, $now);
		}
	}

	/**
	 * @return list<ItemCategoryMemory>
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

	/**
	 * @param list<int> $categoryIds
	 */
	public function deleteByCategories(array $categoryIds): void {
		foreach (array_chunk($categoryIds, 1000) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete($this->getTableName())
				->where($qb->expr()->in('category_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			$qb->executeStatement();
		}
	}

	public function deleteByHouse(int $houseId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	private function addCounts(int $houseId, string $nameKey, int $categoryId, int $uses, int $rejections, int $now): int {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('uses', $qb->createFunction('uses + ' . $uses))
			->set('rejections', $qb->createFunction('rejections + ' . $rejections))
			->set('updated_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('name_key', $qb->createNamedParameter($nameKey)))
			->andWhere($qb->expr()->eq('category_id', $qb->createNamedParameter($categoryId, IQueryBuilder::PARAM_INT)));
		return $qb->executeStatement();
	}
}
