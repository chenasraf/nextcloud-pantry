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
 * @template-extends QBMapper<Photo>
 */
class PhotoMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, Application::tableName('photos'), Photo::class);
	}

	/**
	 * @return Photo[]
	 */
	public function findByHouse(int $houseId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)))
			->orderBy('sort_order', 'ASC')
			->addOrderBy('created_at', 'DESC');

		return $this->findEntities($qb);
	}

	/**
	 * @return Photo[]
	 */
	public function findByFolder(int $folderId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('folder_id', $qb->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)))
			->orderBy('sort_order', 'ASC')
			->addOrderBy('created_at', 'DESC');

		return $this->findEntities($qb);
	}

	/**
	 * @return Photo[]
	 */
	public function findRootByHouse(int $houseId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('folder_id'))
			->orderBy('sort_order', 'ASC')
			->addOrderBy('created_at', 'DESC');

		return $this->findEntities($qb);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findById(int $id): Photo {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($qb);
	}

	public function moveToRoot(int $folderId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('folder_id', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
			->where($qb->expr()->eq('folder_id', $qb->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	public function deleteByHouse(int $houseId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
