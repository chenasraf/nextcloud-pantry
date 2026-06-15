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
	public function findByHouse(int $houseId, string $sortBy = 'custom'): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('deleted_at'));
		$this->applySort($qb, $sortBy);

		return $this->findEntities($qb);
	}

	/**
	 * @return Photo[]
	 */
	public function findByFolder(int $folderId, string $sortBy = 'custom'): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('folder_id', $qb->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('deleted_at'));
		$this->applySort($qb, $sortBy);

		return $this->findEntities($qb);
	}

	/**
	 * @return Photo[]
	 */
	public function findRootByHouse(int $houseId, string $sortBy = 'custom'): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('folder_id'))
			->andWhere($qb->expr()->isNull('deleted_at'));
		$this->applySort($qb, $sortBy);

		return $this->findEntities($qb);
	}

	private function applySort(IQueryBuilder $qb, string $sortBy): void {
		switch ($sortBy) {
			case 'newest':
				$qb->orderBy('created_at', 'DESC');
				break;
			case 'oldest':
				$qb->orderBy('created_at', 'ASC');
				break;
			case 'description_asc':
				$qb->orderBy('caption', 'ASC')
					->addOrderBy('created_at', 'DESC');
				break;
			case 'description_desc':
				$qb->orderBy('caption', 'DESC')
					->addOrderBy('created_at', 'DESC');
				break;
			default: // custom
				$qb->orderBy('sort_order', 'ASC')
					->addOrderBy('created_at', 'DESC');
				break;
		}
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findById(int $id, bool $includeDeleted = false): Photo {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		if (!$includeDeleted) {
			$qb->andWhere($qb->expr()->isNull('deleted_at'));
		}

		return $this->findEntity($qb);
	}

	/**
	 * Find soft-deleted photos in a house, most recently deleted first.
	 *
	 * @return Photo[]
	 */
	public function findDeletedByHouse(int $houseId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNotNull('deleted_at'))
			->orderBy('deleted_at', 'DESC');

		return $this->findEntities($qb);
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

	/**
	 * Hard-delete every soft-deleted photo in the house.
	 *
	 * @return Photo[] The rows that were removed (so callers can clean files).
	 */
	public function emptyTrashForHouse(int $houseId): array {
		$rows = $this->findDeletedByHouse($houseId);
		foreach ($rows as $row) {
			$this->delete($row);
		}
		return $rows;
	}

	/**
	 * Find soft-deleted photos in the house whose deleted_at is strictly before
	 * the cutoff (seconds since epoch). Used by the purge job.
	 *
	 * @return Photo[]
	 */
	public function findExpiredTrashByHouse(int $houseId, int $cutoff): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNotNull('deleted_at'))
			->andWhere($qb->expr()->lt('deleted_at', $qb->createNamedParameter($cutoff, IQueryBuilder::PARAM_INT)));

		return $this->findEntities($qb);
	}
}
