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
 * @template-extends QBMapper<HouseMemberRole>
 */
class HouseMemberRoleMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, Application::tableName('house_member_roles'), HouseMemberRole::class);
	}

	/**
	 * @return int[]
	 */
	public function findRoleIdsForMember(int $memberId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('role_id')
			->from($this->getTableName())
			->where($qb->expr()->eq('member_id', $qb->createNamedParameter($memberId, IQueryBuilder::PARAM_INT)));
		return array_map('intval', $qb->executeQuery()->fetchAll(\PDO::FETCH_COLUMN));
	}

	/**
	 * Resolve the role ids a user holds in a house by joining the membership row.
	 *
	 * @return int[]
	 */
	public function findRoleIdsForUserInHouse(string $uid, int $houseId): array {
		$members = Application::tableName('house_members');
		$qb = $this->db->getQueryBuilder();
		$qb->select('mr.role_id')
			->from($this->getTableName(), 'mr')
			->innerJoin('mr', $members, 'm', $qb->expr()->eq('mr.member_id', 'm.id'))
			->where($qb->expr()->eq('m.user_id', $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('m.house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)));
		return array_map('intval', $qb->executeQuery()->fetchAll(\PDO::FETCH_COLUMN));
	}

	/**
	 * Replace the full set of roles assigned to a member.
	 *
	 * @param int[] $roleIds
	 */
	public function setRolesForMember(int $memberId, array $roleIds): void {
		$this->deleteByMember($memberId);
		$unique = array_values(array_unique(array_map('intval', $roleIds)));
		foreach ($unique as $roleId) {
			$row = new HouseMemberRole();
			$row->setMemberId($memberId);
			$row->setRoleId($roleId);
			$this->insert($row);
		}
	}

	public function deleteByMember(int $memberId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('member_id', $qb->createNamedParameter($memberId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	public function deleteByRole(int $roleId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('role_id', $qb->createNamedParameter($roleId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * Remove every member-role row for the members of a house. Used when a
	 * house is deleted (members are removed wholesale).
	 */
	public function deleteByHouse(int $houseId): void {
		$members = Application::tableName('house_members');
		$lookup = $this->db->getQueryBuilder();
		$lookup->select('id')
			->from($members)
			->where($lookup->expr()->eq('house_id', $lookup->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)));
		$memberIds = array_map('intval', $lookup->executeQuery()->fetchAll(\PDO::FETCH_COLUMN));
		if ($memberIds === []) {
			return;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->in('member_id', $qb->createNamedParameter($memberIds, IQueryBuilder::PARAM_INT_ARRAY)));
		$qb->executeStatement();
	}
}
