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
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)));
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
			case 'title_asc':
				$qb->orderBy('title', 'ASC')
					->addOrderBy('created_at', 'DESC');
				break;
			case 'title_desc':
				$qb->orderBy('title', 'DESC')
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
	public function findById(int $id): Note {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($qb);
	}

	public function deleteByHouse(int $houseId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
