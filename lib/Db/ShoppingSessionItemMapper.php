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
		parent::__construct($db, Application::tableName('shopping_session_items'), ShoppingSessionItem::class);
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

	/**
	 * A user's checks logged within [from, to) (epoch seconds), across all their
	 * sessions in the house. Joins sessions for the owner + house scope.
	 *
	 * @return ShoppingSessionItem[]
	 */
	public function findForUserBetween(string $uid, int $houseId, int $from, int $to): array {
		$qb = $this->db->getQueryBuilder();
		$sessions = Application::tableName('shopping_sessions');
		$qb->select('si.*')
			->from($this->getTableName(), 'si')
			->innerJoin('si', $sessions, 's', $qb->expr()->eq('si.session_id', 's.id'))
			->where($qb->expr()->eq('s.user_id', $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('s.house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->gte('si.checked_at', $qb->createNamedParameter($from, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->lt('si.checked_at', $qb->createNamedParameter($to, IQueryBuilder::PARAM_INT)))
			->orderBy('si.checked_at', 'DESC');
		return $this->findEntities($qb);
	}
}
