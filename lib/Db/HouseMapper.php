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
 * @template-extends QBMapper<House>
 */
class HouseMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, Application::tableName('houses'), House::class);
	}

	/**
	 * @return House[]
	 */
	public function findAllForUser(string $uid): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('h.*')
			->from($this->getTableName(), 'h')
			->innerJoin(
				'h',
				Application::tableName('house_members'),
				'm',
				$qb->expr()->eq('m.house_id', 'h.id'),
			)
			->where($qb->expr()->eq('m.user_id', $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR)))
			->orderBy('h.name', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findById(int $id): House {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($qb);
	}
}
