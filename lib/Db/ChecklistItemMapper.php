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
	 * @param string $categorySort When $sortBy is 'category', the category sort
	 *                             mode to apply (name_asc, name_desc, custom).
	 *
	 * @return ChecklistItem[]
	 */
	public function findByList(int $listId, string $sortBy = 'custom', string $categorySort = 'name_asc'): array {
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
				->andWhere($qb->expr()->isNull('i.deleted_at'))
				->andWhere($qb->expr()->isNull('i.archived_at'))
				->orderBy(
					$qb->createFunction('CASE WHEN i.category_id IS NULL THEN 1 ELSE 0 END'),
					'ASC',
				);
			switch ($categorySort) {
				case 'name_desc':
					$qb->addOrderBy('c.name', 'DESC');
					break;
				case 'custom':
					$qb->addOrderBy('c.sort_order', 'ASC')
						->addOrderBy('c.name', 'ASC');
					break;
				default: // name_asc
					$qb->addOrderBy('c.name', 'ASC');
					break;
			}
			// Within a category, honour the shared custom order (sort_order), then
			// fall back to name/created_at so equal (or legacy 0) sort_orders stay
			// stable. Category *header* order is controlled separately above.
			$qb->addOrderBy('i.sort_order', 'ASC')
				->addOrderBy('i.name', 'ASC')
				->addOrderBy('i.created_at', 'ASC');
			return $this->findEntities($qb);
		}

		$qb->select('*')
			->from($items)
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('deleted_at'))
			->andWhere($qb->expr()->isNull('archived_at'));

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
	 * Find non-deleted items across every non-deleted list in the house. The
	 * "custom" sort mode is not supported here because sort_order is scoped
	 * to a single list — callers should fall back to a deterministic sort.
	 *
	 * @return ChecklistItem[]
	 */
	public function findByHouse(int $houseId, string $sortBy = 'newest', string $categorySort = 'name_asc'): array {
		$qb = $this->db->getQueryBuilder();
		$items = $this->getTableName();
		$lists = Application::tableName('lists');

		if ($sortBy === 'category') {
			$categories = Application::tableName('categories');
			$qb->select('i.*')
				->from($items, 'i')
				->innerJoin('i', $lists, 'l', $qb->expr()->andX(
					$qb->expr()->eq('i.list_id', 'l.id'),
					$qb->expr()->isNull('l.deleted_at'),
				))
				->leftJoin(
					'i',
					$categories,
					'c',
					$qb->expr()->eq('i.category_id', 'c.id'),
				)
				->where($qb->expr()->eq('l.house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->isNull('i.deleted_at'))
				->andWhere($qb->expr()->isNull('i.archived_at'))
				->orderBy(
					$qb->createFunction('CASE WHEN i.category_id IS NULL THEN 1 ELSE 0 END'),
					'ASC',
				);
			switch ($categorySort) {
				case 'name_desc':
					$qb->addOrderBy('c.name', 'DESC');
					break;
				case 'custom':
					$qb->addOrderBy('c.sort_order', 'ASC')
						->addOrderBy('c.name', 'ASC');
					break;
				default: // name_asc
					$qb->addOrderBy('c.name', 'ASC');
					break;
			}
			$qb->addOrderBy('i.name', 'ASC')
				->addOrderBy('i.created_at', 'ASC');
			return $this->findEntities($qb);
		}

		$qb->select('i.*')
			->from($items, 'i')
			->innerJoin('i', $lists, 'l', $qb->expr()->andX(
				$qb->expr()->eq('i.list_id', 'l.id'),
				$qb->expr()->isNull('l.deleted_at'),
			))
			->where($qb->expr()->eq('l.house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('i.deleted_at'))
			->andWhere($qb->expr()->isNull('i.archived_at'));

		switch ($sortBy) {
			case 'oldest':
				$qb->orderBy('i.created_at', 'ASC');
				break;
			case 'name_asc':
				$qb->orderBy('i.name', 'ASC')
					->addOrderBy('i.created_at', 'ASC');
				break;
			case 'name_desc':
				$qb->orderBy('i.name', 'DESC')
					->addOrderBy('i.created_at', 'ASC');
				break;
			default: // newest
				$qb->orderBy('i.created_at', 'DESC');
				break;
		}

		return $this->findEntities($qb);
	}

	/**
	 * Aggregate the unchecked, in-scope items to shop for a session.
	 *
	 * Scope is the union of the given list ids. Active-store narrowing: when
	 * $activeStoreId is set, keep items assigned to that store, plus — when
	 * $includeUnassigned — items with no store assignment at all (buy-anywhere);
	 * items assigned only to other stores are hidden. With no active store, no
	 * narrowing is applied. The item_stores LEFT JOIN is only added when
	 * narrowing so multi-store items are not duplicated.
	 *
	 * Ordered by category sort_order then item sort_order, with uncategorized
	 * items trailing. Returns a flat array the caller groups by category.
	 *
	 * @param int[] $listIds
	 * @return ChecklistItem[]
	 */
	public function findForShoppingScope(array $listIds, ?int $activeStoreId, bool $includeUnassigned): array {
		$listIds = array_values(array_unique(array_map('intval', $listIds)));
		if ($listIds === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$items = $this->getTableName();
		$categories = Application::tableName('categories');

		$qb->select('i.*')
			->from($items, 'i')
			->leftJoin('i', $categories, 'c', $qb->expr()->eq('i.category_id', 'c.id'))
			->where($qb->expr()->in('i.list_id', $qb->createNamedParameter($listIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($qb->expr()->eq('i.done', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->isNull('i.deleted_at'))
			->andWhere($qb->expr()->isNull('i.archived_at'));

		if ($activeStoreId !== null) {
			$itemStores = Application::tableName('item_stores');
			$qb->leftJoin('i', $itemStores, 's', $qb->expr()->eq('i.id', 's.item_id'));
			$storeMatch = $qb->expr()->eq('s.store_id', $qb->createNamedParameter($activeStoreId, IQueryBuilder::PARAM_INT));
			if ($includeUnassigned) {
				// s.store_id IS NULL only occurs for items with no item_stores row
				// (the LEFT JOIN's null side), i.e. truly buy-anywhere items.
				$qb->andWhere($qb->expr()->orX($storeMatch, $qb->expr()->isNull('s.store_id')));
			} else {
				$qb->andWhere($storeMatch);
			}
		}

		$qb->orderBy(
			$qb->createFunction('CASE WHEN i.category_id IS NULL THEN 1 ELSE 0 END'),
			'ASC',
		)
			->addOrderBy('c.sort_order', 'ASC')
			->addOrderBy('i.sort_order', 'ASC')
			->addOrderBy('i.id', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * Fetch items by id regardless of their deleted/archived state. Used by the
	 * shopping review, which must still show items checked off during the trip
	 * even when a "delete on done" item was soft-deleted by that check.
	 *
	 * @param int[] $ids
	 * @return ChecklistItem[]
	 */
	public function findByIds(array $ids): array {
		$ids = array_values(array_unique(array_map('intval', $ids)));
		if ($ids === []) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->in('id', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)));
		return $this->findEntities($qb);
	}

	/**
	 * The highest sort_order among live (non-deleted, non-archived) items in the
	 * list, or null when the list has none. New items append at max + 1 so they
	 * land at the bottom of the custom order instead of colliding on 0.
	 */
	public function maxSortOrder(int $listId): ?int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->max('sort_order'))
			->from($this->getTableName())
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('deleted_at'))
			->andWhere($qb->expr()->isNull('archived_at'));
		$result = $qb->executeQuery();
		$max = $result->fetchOne();
		$result->closeCursor();
		return $max === null || $max === false ? null : (int)$max;
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findById(int $id, bool $includeDeleted = false): ChecklistItem {
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
	 * Find done recurring items whose occurrence day is today or earlier.
	 *
	 * $threshold is midnight (server timezone) of the day after "now", so the
	 * comparison is exclusive: any item due on the current day (at any time) or
	 * overdue is returned. The caller applies the per-house reopen-time gate.
	 *
	 * @return ChecklistItem[]
	 */
	public function findDueRecurring(int $threshold): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('done', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->isNotNull('next_due_at'))
			->andWhere($qb->expr()->lt('next_due_at', $qb->createNamedParameter($threshold, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('deleted_at'))
			->andWhere($qb->expr()->isNull('archived_at'));

		return $this->findEntities($qb);
	}

	/**
	 * Find undone fixed-schedule items whose occurrence day is today or earlier.
	 * These are items the user never checked off but whose schedule has ticked again.
	 *
	 * $threshold has the same meaning as in findDueRecurring().
	 *
	 * @return ChecklistItem[]
	 */
	public function findDueFixedScheduleUndone(int $threshold): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('done', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->eq('repeat_from_completion', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->isNotNull('rrule'))
			->andWhere($qb->expr()->isNotNull('next_due_at'))
			->andWhere($qb->expr()->lt('next_due_at', $qb->createNamedParameter($threshold, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('deleted_at'))
			->andWhere($qb->expr()->isNull('archived_at'));

		return $this->findEntities($qb);
	}

	/**
	 * Find soft-deleted items in a list, most recently deleted first.
	 *
	 * @return ChecklistItem[]
	 */
	public function findDeletedByList(int $listId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNotNull('deleted_at'))
			->orderBy('deleted_at', 'DESC');

		return $this->findEntities($qb);
	}

	/**
	 * Find archived items in a list, most recently archived first.
	 *
	 * @return ChecklistItem[]
	 */
	public function findArchivedByList(int $listId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNotNull('archived_at'))
			->orderBy('archived_at', 'DESC');

		return $this->findEntities($qb);
	}

	public function deleteByList(int $listId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * Hard-delete every soft-deleted item in the list.
	 */
	public function emptyTrashForList(int $listId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNotNull('deleted_at'));
		$qb->executeStatement();
	}

	/**
	 * Find soft-deleted items belonging to lists in the given house whose
	 * deleted_at is strictly before the cutoff (seconds since epoch). Joins
	 * the lists table so callers don't need to enumerate every list.
	 *
	 * @return ChecklistItem[]
	 */
	public function findExpiredTrashByHouse(int $houseId, int $cutoff): array {
		$qb = $this->db->getQueryBuilder();
		$items = $this->getTableName();
		$lists = Application::tableName('lists');
		$qb->select('i.*')
			->from($items, 'i')
			->innerJoin('i', $lists, 'l', $qb->expr()->eq('i.list_id', 'l.id'))
			->where($qb->expr()->eq('l.house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNotNull('i.deleted_at'))
			->andWhere($qb->expr()->lt('i.deleted_at', $qb->createNamedParameter($cutoff, IQueryBuilder::PARAM_INT)));

		return $this->findEntities($qb);
	}
}
