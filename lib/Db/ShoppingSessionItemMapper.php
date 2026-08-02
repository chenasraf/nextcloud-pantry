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
 * @template-extends QBMapper<ShoppingSessionItem>
 */
class ShoppingSessionItemMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, Application::tableName('shopsess_items'), ShoppingSessionItem::class);
	}

	/**
	 * The session's checked log, oldest first.
	 *
	 * @return ShoppingSessionItem[]
	 */
	public function findBySession(int $sessionId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('session_id', $qb->createNamedParameter($sessionId, IQueryBuilder::PARAM_INT)))
			->orderBy('checked_at', 'ASC')
			->addOrderBy('id', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * Count of check-log rows for a session (the trip's checked-item count).
	 */
	public function countBySession(int $sessionId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'cnt'))
			->from($this->getTableName())
			->where($qb->expr()->eq('session_id', $qb->createNamedParameter($sessionId, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}

	public function findBySessionAndItem(int $sessionId, int $itemId): ?ShoppingSessionItem {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('session_id', $qb->createNamedParameter($sessionId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('item_id', $qb->createNamedParameter($itemId, IQueryBuilder::PARAM_INT)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function deleteBySessionAndItem(int $sessionId, int $itemId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('session_id', $qb->createNamedParameter($sessionId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('item_id', $qb->createNamedParameter($itemId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	public function deleteBySession(int $sessionId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('session_id', $qb->createNamedParameter($sessionId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
