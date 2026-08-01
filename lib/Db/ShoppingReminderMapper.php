<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCA\Pantry\AppInfo\Application;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<ShoppingReminder>
 */
class ShoppingReminderMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, Application::tableName('shopping_reminders'), ShoppingReminder::class);
	}

	/**
	 * All of a house's reminders, ordered for the manager (position, then id).
	 *
	 * @return ShoppingReminder[]
	 */
	public function findByHouse(int $houseId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)))
			->orderBy('position', 'ASC')
			->addOrderBy('id', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findById(int $id): ShoppingReminder {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * The current max position in a house (−1 when the house has none), so a new
	 * reminder appends to the end.
	 */
	public function maxPositionForHouse(int $houseId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->max('position'))
			->from($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$max = $result->fetchOne();
		$result->closeCursor();
		return $max === null || $max === false ? -1 : (int)$max;
	}

	public function deleteByHouse(int $houseId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
