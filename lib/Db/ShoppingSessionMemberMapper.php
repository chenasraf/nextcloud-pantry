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
 * @template-extends QBMapper<ShoppingSessionMember>
 */
class ShoppingSessionMemberMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, Application::tableName('shopsess_members'), ShoppingSessionMember::class);
	}

	/**
	 * Everyone currently in a trip, excluding its owner and anyone who left.
	 * Oldest join first.
	 *
	 * @return ShoppingSessionMember[]
	 */
	public function findActiveBySession(int $sessionId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('session_id', $qb->createNamedParameter($sessionId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('left_at'))
			->orderBy('joined_at', 'ASC')
			->addOrderBy('id', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * Active member uids for several trips at once, keyed by session id. Keeps
	 * the presence list to one query regardless of how many trips are live.
	 *
	 * @param list<int> $sessionIds
	 * @return array<int, list<string>>
	 */
	public function findActiveUserIdsForSessions(array $sessionIds): array {
		if ($sessionIds === []) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('session_id', 'user_id')
			->from($this->getTableName())
			->where($qb->expr()->in('session_id', $qb->createNamedParameter($sessionIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($qb->expr()->isNull('left_at'))
			->orderBy('joined_at', 'ASC')
			->addOrderBy('id', 'ASC');
		$result = $qb->executeQuery();
		$map = [];
		while ($row = $result->fetch()) {
			$map[(int)$row['session_id']][] = (string)$row['user_id'];
		}
		$result->closeCursor();
		return $map;
	}

	/**
	 * The membership row for a user in a trip, whether or not they have left.
	 */
	public function findBySessionAndUser(int $sessionId, string $uid): ?ShoppingSessionMember {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('session_id', $qb->createNamedParameter($sessionId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * The live trip this user has joined, if any. Ordered newest first so a
	 * stale row can never shadow a current join.
	 */
	public function findLiveJoinedSessionId(string $uid): ?int {
		$sessions = Application::tableName('shopping_sessions');
		$qb = $this->db->getQueryBuilder();
		$qb->select('m.session_id')
			->from($this->getTableName(), 'm')
			->innerJoin('m', $sessions, 's', $qb->expr()->eq('m.session_id', 's.id'))
			->where($qb->expr()->eq('m.user_id', $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->isNull('m.left_at'))
			->andWhere($qb->expr()->isNull('s.closed_at'))
			->orderBy('m.joined_at', 'DESC')
			->addOrderBy('m.id', 'DESC')
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		return $row === false ? null : (int)$row['session_id'];
	}

	/**
	 * Mark everyone still in a trip as having left. Used when the trip closes so
	 * no membership row outlives its session.
	 */
	public function markAllLeft(int $sessionId, int $now): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('left_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('session_id', $qb->createNamedParameter($sessionId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('left_at'));
		$qb->executeStatement();
	}

	public function deleteBySession(int $sessionId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('session_id', $qb->createNamedParameter($sessionId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
