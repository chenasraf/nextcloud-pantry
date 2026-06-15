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
 * @template-extends QBMapper<Note>
 */
class NoteMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, Application::tableName('notes'), Note::class);
	}

	/**
	 * @return Note[]
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

	private function applySort(IQueryBuilder $qb, string $sortBy): void {
		// Pinned notes always float to the top regardless of sort mode.
		$qb->orderBy('is_pinned', 'DESC');
		switch ($sortBy) {
			case 'newest':
				$qb->addOrderBy('created_at', 'DESC');
				break;
			case 'oldest':
				$qb->addOrderBy('created_at', 'ASC');
				break;
			case 'title_asc':
				$qb->addOrderBy('title', 'ASC')
					->addOrderBy('created_at', 'DESC');
				break;
			case 'title_desc':
				$qb->addOrderBy('title', 'DESC')
					->addOrderBy('created_at', 'DESC');
				break;
			default: // custom
				$qb->addOrderBy('sort_order', 'ASC')
					->addOrderBy('created_at', 'DESC');
				break;
		}
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findById(int $id, bool $includeDeleted = false): Note {
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
	 * Find soft-deleted notes in a house, most recently deleted first.
	 *
	 * @return Note[]
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

	public function deleteByHouse(int $houseId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * Hard-delete every soft-deleted note in the house.
	 */
	public function emptyTrashForHouse(int $houseId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNotNull('deleted_at'));
		$qb->executeStatement();
	}

	/**
	 * Find soft-deleted notes in the house whose deleted_at is strictly before
	 * the cutoff (seconds since epoch). Used by the purge job.
	 *
	 * @return Note[]
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
