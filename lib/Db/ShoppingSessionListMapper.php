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
 * @template-extends QBMapper<ShoppingSessionList>
 */
class ShoppingSessionListMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, Application::tableName('shopping_session_lists'), ShoppingSessionList::class);
	}

	/**
	 * @return int[] The list ids in the session's scope.
	 */
	public function findListIdsForSession(int $sessionId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('list_id')
			->from($this->getTableName())
			->where($qb->expr()->eq('session_id', $qb->createNamedParameter($sessionId, IQueryBuilder::PARAM_INT)));
		return array_map('intval', $qb->executeQuery()->fetchAll(\PDO::FETCH_COLUMN));
	}

	/**
	 * @param int[] $listIds
	 */
	public function setListsForSession(int $sessionId, array $listIds): void {
		$this->deleteBySession($sessionId);
		foreach (array_values(array_unique(array_map('intval', $listIds))) as $listId) {
			$row = new ShoppingSessionList();
			$row->setSessionId($sessionId);
			$row->setListId($listId);
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
