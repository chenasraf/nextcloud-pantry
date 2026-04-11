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
 * @template-extends QBMapper<ChecklistItem>
 */
class ChecklistItemMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, Application::tableName('list_items'), ChecklistItem::class);
	}

	/**
	 * @return ChecklistItem[]
	 */
	public function findByList(int $listId, string $sortBy = 'custom'): array {
		$qb = $this->db->getQueryBuilder();
		$items = $this->getTableName();

		if ($sortBy === 'category') {
			// Left-join the categories table so items with no category still appear.
			// Uncategorized items are pushed to the end via a CASE expression in
			// ORDER BY (we can't SELECT it as an alias because the mapper would
			// try to set it as an entity attribute).
			$categories = Application::tableName('categories');
			$qb->select('i.*')
				->from($items, 'i')
				->leftJoin(
					'i',
					$categories,
					'c',
					$qb->expr()->eq('i.category_id', 'c.id'),
				)
				->where($qb->expr()->eq('i.list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)))
				->orderBy(
					$qb->createFunction('CASE WHEN i.category_id IS NULL THEN 1 ELSE 0 END'),
					'ASC',
				)
				->addOrderBy('c.name', 'ASC')
				->addOrderBy('i.name', 'ASC')
				->addOrderBy('i.created_at', 'ASC');
			return $this->findEntities($qb);
		}

		$qb->select('*')
			->from($items)
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)));

		switch ($sortBy) {
			case 'newest':
				$qb->orderBy('created_at', 'DESC');
				break;
			case 'oldest':
				$qb->orderBy('created_at', 'ASC');
				break;
			case 'name_asc':
				$qb->orderBy('name', 'ASC')
					->addOrderBy('created_at', 'ASC');
				break;
			case 'name_desc':
				$qb->orderBy('name', 'DESC')
					->addOrderBy('created_at', 'ASC');
				break;
			default: // custom
				$qb->orderBy('sort_order', 'ASC')
					->addOrderBy('created_at', 'ASC');
				break;
		}

		return $this->findEntities($qb);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findById(int $id): ChecklistItem {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($qb);
	}

	/**
	 * Find all done items whose next_due_at has passed.
	 *
	 * @return ChecklistItem[]
	 */
	public function findDueRecurring(int $now): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('done', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->isNotNull('next_due_at'))
			->andWhere($qb->expr()->lte('next_due_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT)));

		return $this->findEntities($qb);
	}

	/**
	 * Find undone fixed-schedule items whose next_due_at has passed.
	 * These are items the user never checked off but whose schedule has ticked again.
	 *
	 * @return ChecklistItem[]
	 */
	public function findDueFixedScheduleUndone(int $now): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('done', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->eq('repeat_from_completion', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->isNotNull('rrule'))
			->andWhere($qb->expr()->isNotNull('next_due_at'))
			->andWhere($qb->expr()->lte('next_due_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT)));

		return $this->findEntities($qb);
	}

	public function deleteByList(int $listId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
