<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCA\Pantry\AppInfo\Application;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<ChecklistItem>
 */
class ChecklistItemMapper extends QBMapper {
	/** @var array<string, true>|null Cache of DB column names the entity declares. */
	private ?array $knownColumns = null;

	public function __construct(IDBConnection $db) {
		parent::__construct($db, Application::tableName('list_items'), ChecklistItem::class);
	}

	/**
	 * Items are read with `SELECT *`, so every table column is handed to
	 * {@see Entity::fromRow()} — which throws BadFunctionCallException for any
	 * column the entity does not declare. Schema drift can leave a column behind
	 * that a later migration was supposed to drop (the legacy price_* columns
	 * removed in Version26), which would otherwise take down the entire checklist
	 * on every request. Drop unknown columns here so reads survive the drift; the
	 * stray columns themselves are cleaned up by `occ pantry:repair-schema` (see
	 * {@see \OCA\Pantry\Migration\LegacyColumns}).
	 *
	 * @param array<array-key, mixed> $row
	 */
	#[\Override]
	protected function mapRowToEntity(array $row): ChecklistItem {
		return parent::mapRowToEntity(array_intersect_key($row, $this->knownColumns()));
	}

	/**
	 * DB column names the {@see ChecklistItem} entity declares. Derived from the
	 * entity's persisted (protected) properties, converted from camelCase to the
	 * snake_case column names, plus the inherited id.
	 *
	 * @return array<string, true>
	 */
	private function knownColumns(): array {
		if ($this->knownColumns === null) {
			// id is declared on the base Entity; every other column maps from a
			// persisted property on ChecklistItem.
			$columns = ['id' => true];
			$properties = (new \ReflectionClass(ChecklistItem::class))
				->getProperties(\ReflectionProperty::IS_PROTECTED);
			foreach ($properties as $property) {
				$name = $property->getName();
				// Skip the entity's internal bookkeeping fields (_updatedFields,
				// _fieldTypes, …); only persisted columns matter here.
				if (str_starts_with($name, '_')) {
					continue;
				}
				$column = strtolower((string)preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $name));
				$columns[$column] = true;
			}
			$this->knownColumns = $columns;
		}

		return $this->knownColumns;
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
	 * Find non-deleted items across every non-deleted checklist in the house.
	 * The "custom" sort mode is not supported here because sort_order is scoped
	 * to a single list — callers should fall back to a deterministic sort.
	 *
	 * @return ChecklistItem[]
	 */
	public function findByHouse(int $houseId, string $sortBy = 'newest', string $categorySort = 'name_asc'): array {
		$qb = $this->db->getQueryBuilder();
		$items = $this->getTableName();
		$lists = Application::tableName('lists');
		$isChecklist = $qb->expr()->orX(
			$qb->expr()->eq('l.kind', $qb->createNamedParameter(Checklist::KIND_CHECKLIST)),
			$qb->expr()->isNull('l.kind'),
		);

		if ($sortBy === 'category') {
			$categories = Application::tableName('categories');
			$qb->select('i.*')
				->from($items, 'i')
				->innerJoin('i', $lists, 'l', $qb->expr()->andX(
					$qb->expr()->eq('i.list_id', 'l.id'),
					$qb->expr()->isNull('l.deleted_at'),
					$isChecklist,
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
				$isChecklist,
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
	 * Ordered by category sort_order then category name — the name keeps items
	 * of categories sharing a sort_order from interleaving — then item
	 * sort_order, with uncategorized items trailing. An active store that has
	 * been arranged (store_cat_order) leads with the categories it arranges, in
	 * its own order, so the list follows the aisles of the shop being walked;
	 * categories it does not name fall in behind by the house-wide order.
	 * Returns a flat array the caller groups by category.
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
			$storeParam = $qb->createNamedParameter($activeStoreId, IQueryBuilder::PARAM_INT);
			$qb->leftJoin(
				'c',
				Application::tableName('store_cat_order'),
				'sco',
				$qb->expr()->andX(
					$qb->expr()->eq('sco.category_id', 'c.id'),
					$qb->expr()->eq('sco.store_id', $storeParam),
				),
			);

			$itemStores = Application::tableName('item_stores');
			$qb->leftJoin('i', $itemStores, 's', $qb->expr()->eq('i.id', 's.item_id'));
			$storeMatch = $qb->expr()->eq('s.store_id', $storeParam);
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
		);
		if ($activeStoreId !== null) {
			// An unarranged store leaves every sco.sort_order null, so both of
			// these collapse to constants and the house-wide order below takes
			// over unchanged. Splitting the nulls out first also keeps the
			// databases that disagree on where nulls sort out of it.
			$qb->addOrderBy($qb->createFunction('CASE WHEN sco.sort_order IS NULL THEN 1 ELSE 0 END'), 'ASC')
				->addOrderBy('sco.sort_order', 'ASC');
		}
		$qb->addOrderBy('c.sort_order', 'ASC')
			->addOrderBy('c.name', 'ASC')
			->addOrderBy('i.sort_order', 'ASC')
			->addOrderBy('i.id', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * Unchecked, in-scope items for a trip that shops several stores from one
	 * list: items assigned to any of $storeIds, plus — when $includeUnassigned —
	 * items with no store assignment at all. Store membership is tested with
	 * subqueries rather than a join, so an item assigned to more than one of the
	 * stores still appears once.
	 *
	 * No single store's aisle order applies to a combined list, so items follow
	 * the house-wide category order, uncategorized trailing.
	 *
	 * @param int[] $listIds
	 * @param int[] $storeIds
	 * @return ChecklistItem[]
	 */
	public function findForShoppingStores(array $listIds, array $storeIds, bool $includeUnassigned): array {
		$listIds = array_values(array_unique(array_map('intval', $listIds)));
		$storeIds = array_values(array_unique(array_map('intval', $storeIds)));
		if ($listIds === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$categories = Application::tableName('categories');
		$itemStores = Application::tableName('item_stores');

		$qb->select('i.*')
			->from($this->getTableName(), 'i')
			->leftJoin('i', $categories, 'c', $qb->expr()->eq('i.category_id', 'c.id'))
			->where($qb->expr()->in('i.list_id', $qb->createNamedParameter($listIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($qb->expr()->eq('i.done', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->isNull('i.deleted_at'))
			->andWhere($qb->expr()->isNull('i.archived_at'));

		$matches = [];
		if ($storeIds !== []) {
			$inStores = $this->db->getQueryBuilder();
			$inStores->select('ist.item_id')
				->from($itemStores, 'ist')
				->where($inStores->expr()->in('ist.store_id', $qb->createNamedParameter($storeIds, IQueryBuilder::PARAM_INT_ARRAY)));
			$matches[] = $qb->expr()->in('i.id', $qb->createFunction($inStores->getSQL()));
		}
		if ($includeUnassigned) {
			$anyStore = $this->db->getQueryBuilder();
			$anyStore->select('isa.item_id')->from($itemStores, 'isa');
			$matches[] = $qb->expr()->notIn('i.id', $qb->createFunction($anyStore->getSQL()));
		}
		if ($matches === []) {
			return [];
		}
		$qb->andWhere($qb->expr()->orX(...$matches));

		$qb->orderBy($qb->createFunction('CASE WHEN i.category_id IS NULL THEN 1 ELSE 0 END'), 'ASC')
			->addOrderBy('c.sort_order', 'ASC')
			->addOrderBy('c.name', 'ASC')
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
	 * The stock columns of every live item on the given lists, grouped by list.
	 *
	 * @param int[] $listIds
	 * @return array<int, list<array{stockMode: ?string, stockLevel: ?string, quantity: ?string, target: ?string, reorderPoint: ?string, expiresAt: ?int, shelfLifeDays: ?int}>>
	 */
	public function findStockByLists(array $listIds): array {
		$listIds = array_values(array_unique(array_map('intval', $listIds)));
		if ($listIds === []) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('list_id', 'stock_mode', 'stock_level', 'quantity', 'target', 'reorder_point', 'expires_at', 'shelf_life_days')
			->from($this->getTableName())
			->where($qb->expr()->in('list_id', $qb->createNamedParameter($listIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($qb->expr()->isNull('deleted_at'))
			->andWhere($qb->expr()->isNull('archived_at'));
		$result = $qb->executeQuery();
		$out = [];
		while ($row = $result->fetch()) {
			$out[(int)$row['list_id']][] = [
				'stockMode' => $row['stock_mode'] !== null ? (string)$row['stock_mode'] : null,
				'stockLevel' => $row['stock_level'] !== null ? (string)$row['stock_level'] : null,
				'quantity' => $row['quantity'] !== null ? (string)$row['quantity'] : null,
				'target' => $row['target'] !== null ? (string)$row['target'] : null,
				'reorderPoint' => $row['reorder_point'] !== null ? (string)$row['reorder_point'] : null,
				'expiresAt' => $row['expires_at'] !== null ? (int)$row['expires_at'] : null,
				'shelfLifeDays' => $row['shelf_life_days'] !== null ? (int)$row['shelf_life_days'] : null,
			];
		}
		$result->closeCursor();
		return $out;
	}

	/**
	 * How many live items in the list are still unchecked. Archived and
	 * soft-deleted items are not open — they have left the list's working set.
	 */
	public function countOpenByList(int $listId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*'))
			->from($this->getTableName())
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('done', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->isNull('deleted_at'))
			->andWhere($qb->expr()->isNull('archived_at'));
		$result = $qb->executeQuery();
		$count = $result->fetchOne();
		$result->closeCursor();
		return $count === false ? 0 : (int)$count;
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

	/**
	 * Every hard delete of an item goes through this mapper, so this is where
	 * the stock links pointing at it are dropped. A link survives the trash
	 * and the archive, but not the row itself.
	 */
	#[\Override]
	public function delete(Entity $entity): Entity {
		$this->clearLinksTo([(int)$entity->getId()]);
		return parent::delete($entity);
	}

	public function deleteByList(int $listId): void {
		$this->clearLinksTo($this->findIdsByList($listId, trashedOnly: false));
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * Hard-delete every soft-deleted item in the list.
	 */
	public function emptyTrashForList(int $listId): void {
		$this->clearLinksTo($this->findIdsByList($listId, trashedOnly: true));
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNotNull('deleted_at'));
		$qb->executeStatement();
	}

	/**
	 * @return list<int>
	 */
	public function findIdsByList(int $listId, bool $trashedOnly): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($this->getTableName())
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)));
		if ($trashedOnly) {
			$qb->andWhere($qb->expr()->isNotNull('deleted_at'));
		}
		$result = $qb->executeQuery();
		$ids = [];
		while (($id = $result->fetchOne()) !== false) {
			$ids[] = (int)$id;
		}
		$result->closeCursor();
		return $ids;
	}

	/**
	 * Hold the item's row until the open transaction ends. A write that
	 * changes nothing still takes the row lock, on every supported database,
	 * where SELECT … FOR UPDATE is not portable.
	 */
	public function lock(int $id): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('updated_at', 'updated_at')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * Unlink every inventory item linked to one of the given items. Auto-link
	 * is left as it is, so a dropped link can be found again by name.
	 *
	 * @param int[] $itemIds
	 */
	public function clearLinksTo(array $itemIds): void {
		$itemIds = array_values(array_unique(array_map('intval', $itemIds)));
		foreach (array_chunk($itemIds, 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->update($this->getTableName())
				->set('linked_item_id', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
				->set('linked_at', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
				->where($qb->expr()->in('linked_item_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			$qb->executeStatement();
		}
	}

	/**
	 * Live items on live inventories whose expiry status the daily job may
	 * see move: those with an expiry date, or with a synced status still to
	 * clear.
	 *
	 * @return list<ChecklistItem>
	 */
	public function findExpiryTracked(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('i.*')
			->from($this->getTableName(), 'i')
			->innerJoin('i', Application::tableName('lists'), 'l', $qb->expr()->eq('l.id', 'i.list_id'))
			->where($qb->expr()->eq('l.kind', $qb->createNamedParameter(Checklist::KIND_INVENTORY)))
			->andWhere($qb->expr()->isNull('l.deleted_at'))
			->andWhere($qb->expr()->isNull('l.archived_at'))
			->andWhere($qb->expr()->isNull('i.deleted_at'))
			->andWhere($qb->expr()->isNull('i.archived_at'))
			->andWhere($qb->expr()->orX(
				$qb->expr()->isNotNull('i.expires_at'),
				$qb->expr()->andX(
					$qb->expr()->isNotNull('i.expiry_synced_status'),
					$qb->expr()->neq('i.expiry_synced_status', $qb->createNamedParameter('none')),
				),
			))
			->orderBy('i.list_id', 'ASC')
			->addOrderBy('i.id', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * Record the expiry status the restock sync acted on, leaving the rest of
	 * the item as it is.
	 */
	public function setExpirySyncedStatus(int $id, string $status): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('expiry_synced_status', $qb->createNamedParameter($status))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * Items linked to any of the given items, trashed and archived ones
	 * included, earliest linked first.
	 *
	 * @param int[] $itemIds
	 * @return ChecklistItem[]
	 */
	public function findLinkedTo(array $itemIds): array {
		$itemIds = array_values(array_unique(array_map('intval', $itemIds)));
		if ($itemIds === []) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->in('linked_item_id', $qb->createNamedParameter($itemIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->orderBy('linked_at', 'ASC')
			->addOrderBy('id', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * The items the list's items are linked to, trashed and archived items
	 * included.
	 *
	 * @return list<int>
	 */
	public function findLinkedIdsFromList(int $listId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('linked_item_id')
			->from($this->getTableName())
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNotNull('linked_item_id'));
		$result = $qb->executeQuery();
		$ids = [];
		while (($id = $result->fetchOne()) !== false) {
			$ids[] = (int)$id;
		}
		$result->closeCursor();
		return array_values(array_unique($ids));
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
