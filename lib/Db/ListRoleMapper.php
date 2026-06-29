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
 * @template-extends QBMapper<ListRole>
 */
class ListRoleMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, Application::tableName('list_roles'), ListRole::class);
	}

	/**
	 * Allowed role ids for a list. An empty result means the list is open to
	 * every role in the house.
	 *
	 * @return int[]
	 */
	public function findRoleIdsForList(int $listId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('role_id')
			->from($this->getTableName())
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)));
		return array_map('intval', $qb->executeQuery()->fetchAll(\PDO::FETCH_COLUMN));
	}

	/**
	 * Replace the full set of roles allowed to access a list. An empty array
	 * leaves the list open to everyone.
	 *
	 * @param int[] $roleIds
	 */
	public function setRolesForList(int $listId, array $roleIds): void {
		$this->deleteByList($listId);
		$unique = array_values(array_unique(array_map('intval', $roleIds)));
		foreach ($unique as $roleId) {
			$row = new ListRole();
			$row->setListId($listId);
			$row->setRoleId($roleId);
			$this->insert($row);
		}
	}

	public function deleteByList(int $listId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	public function deleteByRole(int $roleId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('role_id', $qb->createNamedParameter($roleId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
