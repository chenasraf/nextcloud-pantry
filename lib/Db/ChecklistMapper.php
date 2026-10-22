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
 * @template-extends QBMapper<Checklist>
 */
class ChecklistMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, Application::tableName('lists'), Checklist::class);
	}

	/**
	 * Active lists in a house: every kind when `$kind` is null.
	 *
	 * @return Checklist[]
	 */
	public function findByHouse(int $houseId, string $sortBy = 'custom', ?string $kind = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('deleted_at'))
			->andWhere($qb->expr()->isNull('archived_at'));
		$this->whereKind($qb, $kind);
		switch ($sortBy) {
			case 'name_asc':
				$qb->orderBy('name', 'ASC');
				break;
			case 'name_desc':
				$qb->orderBy('name', 'DESC');
				break;
			default: // custom
				$qb->orderBy('sort_order', 'ASC')
					->addOrderBy('name', 'ASC');
				break;
		}

		return $this->findEntities($qb);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findById(int $id, bool $includeDeleted = false): Checklist {
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
	 * Find soft-deleted lists in a house, most recently deleted first.
	 *
	 * @return Checklist[]
	 */
	public function findDeletedByHouse(int $houseId, ?string $kind = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNotNull('deleted_at'))
			->orderBy('deleted_at', 'DESC');
		$this->whereKind($qb, $kind);

		return $this->findEntities($qb);
	}

	/**
	 * Find archived lists in a house, most recently archived first.
	 *
	 * @return Checklist[]
	 */
	public function findArchivedByHouse(int $houseId, ?string $kind = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNotNull('archived_at'))
			->orderBy('archived_at', 'DESC');
		$this->whereKind($qb, $kind);

		return $this->findEntities($qb);
	}

	/**
	 * Every list in a house, whatever its kind, archived or trashed.
	 *
	 * @return Checklist[]
	 */
	public function findAllByHouse(int $houseId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)));

		return $this->findEntities($qb);
	}

	public function deleteByHouse(int $houseId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * Hard-delete every soft-deleted list of a kind in the house.
	 *
	 * @return Checklist[] The rows that were removed (so callers can clean items).
	 */
	public function emptyTrashForHouse(int $houseId, ?string $kind = null): array {
		$rows = $this->findDeletedByHouse($houseId, $kind);
		foreach ($rows as $row) {
			$this->delete($row);
		}
		return $rows;
	}

	/**
	 * Find soft-deleted checklists in the house whose deleted_at is strictly
	 * before the cutoff (seconds since epoch). Used by the purge job.
	 *
	 * @return Checklist[]
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

	/**
	 * A null kind column reads as a checklist (see {@see Checklist::getKind()}).
	 */
	private function whereKind(IQueryBuilder $qb, ?string $kind): void {
		if ($kind === null) {
			return;
		}
		$match = $qb->expr()->eq('kind', $qb->createNamedParameter($kind));
		if ($kind === Checklist::KIND_CHECKLIST) {
			$match = $qb->expr()->orX($match, $qb->expr()->isNull('kind'));
		}
		$qb->andWhere($match);
	}
}
