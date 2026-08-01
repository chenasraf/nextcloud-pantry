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
 * @template-extends QBMapper<ShoppingSessionStore>
 */
class ShoppingSessionStoreMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, Application::tableName('shopping_session_stores'), ShoppingSessionStore::class);
	}

	/**
	 * The session's store sequence, in order.
	 *
	 * @return ShoppingSessionStore[]
	 */
	public function findBySession(int $sessionId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('session_id', $qb->createNamedParameter($sessionId, IQueryBuilder::PARAM_INT)))
			->orderBy('position', 'ASC')
			->addOrderBy('id', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * Replace the session's store sequence with the given ordered store ids.
	 * Positions are assigned from the array order.
	 *
	 * @param int[] $storeIds Ordered store ids.
	 */
	public function setStoresForSession(int $sessionId, array $storeIds): void {
		$this->deleteBySession($sessionId);
		$position = 0;
		foreach ($storeIds as $storeId) {
			$row = new ShoppingSessionStore();
			$row->setSessionId($sessionId);
			$row->setStoreId((int)$storeId);
			$row->setPosition($position++);
			$this->insert($row);
		}
	}

	public function deleteBySession(int $sessionId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('session_id', $qb->createNamedParameter($sessionId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
