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
 * @template-extends QBMapper<ShoppingSession>
 */
class ShoppingSessionMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, Application::tableName('shopping_sessions'), ShoppingSession::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findById(int $id): ShoppingSession {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * The user's live session, if any (across all houses). Live = closed_at is
	 * null. Newest first for determinism; the one-live-per-user rule means there
	 * should be at most one.
	 */
	public function findLiveByUser(string $uid): ?ShoppingSession {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->isNull('closed_at'))
			->orderBy('created_at', 'DESC')
			->setMaxResults(1);
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * Closed sessions for the history view, newest first (by closed_at). ADR 0008.
	 *  - 'mine'  → house AND user_id = me
	 *  - 'house' → house AND (user_id = me OR is_private = false)
	 *
	 * The `is_private` widening (a private trip is hidden from housemates even
	 * after close) is implemented purely by this predicate — no schema change.
	 *
	 * @param 'mine'|'house' $scope
	 * @return ShoppingSession[]
	 */
	public function findClosedForHistory(int $houseId, string $uid, string $scope, int $limit, int $offset): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNotNull('closed_at'));
		if ($scope === 'house') {
			$qb->andWhere($qb->expr()->orX(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR)),
				$qb->expr()->eq('is_private', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)),
			));
		} else {
			$qb->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR)));
		}
		$qb->orderBy('closed_at', 'DESC')
			->addOrderBy('id', 'DESC')
			->setMaxResults($limit)
			->setFirstResult($offset);
		return $this->findEntities($qb);
	}

	/**
	 * Derived house presence (ADR 0004): live sessions in the house whose
	 * last_seen_at is fresh (>= cutoff) and which have opted in (is_private
	 * false or null). Newest heartbeat first. Not self-filtered — the caller's
	 * own session is included; the client decides whether to render it.
	 *
	 * @return ShoppingSession[]
	 */
	public function findPresentInHouse(int $houseId, int $cutoff): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('closed_at'))
			->andWhere($qb->expr()->gte('last_seen_at', $qb->createNamedParameter($cutoff, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('is_private', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)),
				$qb->expr()->isNull('is_private'),
			))
			->orderBy('last_seen_at', 'DESC')
			->addOrderBy('id', 'DESC');
		return $this->findEntities($qb);
	}

	/**
	 * Live sessions whose last_seen_at predates the cutoff — abandoned trips the
	 * lifecycle job auto-closes (ADR 0001).
	 *
	 * @return ShoppingSession[]
	 */
	public function findIdleLive(int $cutoff): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->isNull('closed_at'))
			->andWhere($qb->expr()->lt('last_seen_at', $qb->createNamedParameter($cutoff, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	/**
	 * Closed sessions whose closed_at predates the cutoff — the retention purge
	 * horizon (ADR 0008).
	 *
	 * @return ShoppingSession[]
	 */
	public function findClosedBefore(int $cutoff): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->isNotNull('closed_at'))
			->andWhere($qb->expr()->lt('closed_at', $qb->createNamedParameter($cutoff, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}
}
