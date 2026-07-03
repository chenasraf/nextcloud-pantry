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
 * @template-extends QBMapper<Share>
 */
class ShareMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, Application::tableName('shares'), Share::class);
	}

	/**
	 * All shares on a single entity.
	 *
	 * @return Share[]
	 */
	public function findForEntity(string $type, int $entityId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('entity_type', $qb->createNamedParameter($type, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('entity_id', $qb->createNamedParameter($entityId, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	/**
	 * The share (if any) granting a specific user access to a specific entity.
	 */
	public function findForUserAndEntity(string $uid, string $type, int $entityId): ?Share {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('entity_type', $qb->createNamedParameter($type, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('entity_id', $qb->createNamedParameter($entityId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('shared_with_uid', $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR)));
		$rows = $this->findEntities($qb);
		return $rows[0] ?? null;
	}

	/**
	 * All shares granted to a user within a house.
	 *
	 * @return Share[]
	 */
	public function findForUserInHouse(string $uid, int $houseId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('shared_with_uid', $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR)));
		return $this->findEntities($qb);
	}

	/**
	 * Replace the full set of shares on an entity.
	 *
	 * @param array<array{uid: string, permission: string}> $entries
	 */
	public function setSharesForEntity(int $houseId, string $type, int $entityId, string $createdBy, array $entries): void {
		$this->deleteForEntity($type, $entityId);
		$now = time();
		$seen = [];
		foreach ($entries as $entry) {
			$uid = (string)($entry['uid'] ?? '');
			if ($uid === '' || isset($seen[$uid])) {
				continue;
			}
			$seen[$uid] = true;
			$permission = ($entry['permission'] ?? Share::PERM_VIEW) === Share::PERM_EDIT
				? Share::PERM_EDIT
				: Share::PERM_VIEW;
			$row = new Share();
			$row->setHouseId($houseId);
			$row->setEntityType($type);
			$row->setEntityId($entityId);
			$row->setSharedWithUid($uid);
			$row->setPermission($permission);
			$row->setCreatedBy($createdBy);
			$row->setCreatedAt($now);
			$row->setUpdatedAt($now);
			$this->insert($row);
		}
	}

	public function deleteForEntity(string $type, int $entityId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('entity_type', $qb->createNamedParameter($type, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('entity_id', $qb->createNamedParameter($entityId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	public function deleteByHouse(int $houseId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
