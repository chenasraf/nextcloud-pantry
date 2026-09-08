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
 * @template-extends QBMapper<Category>
 */
class CategoryMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, Application::tableName('categories'), Category::class);
	}

	/**
	 * @param string $sortBy One of: name_asc, name_desc, custom.
	 *
	 * @return Category[]
	 */
	public function findByHouse(int $houseId, string $sortBy = 'name_asc'): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)));

		switch ($sortBy) {
			case 'name_desc':
				$qb->orderBy('name', 'DESC');
				break;
			case 'custom':
				$qb->orderBy('sort_order', 'ASC')
					->addOrderBy('name', 'ASC');
				break;
			default: // name_asc
				$qb->orderBy('name', 'ASC');
				break;
		}
		return $this->findEntities($qb);
	}

	/**
	 * Highest sort_order currently used in a house, or -1 when the house has
	 * no categories yet — so a caller can assign `max + 1` to append a new
	 * category at the end of the custom order.
	 */
	public function findMaxSortOrder(int $houseId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->max('sort_order'))
			->from($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)));
		return $this->fetchMaxSortOrder($qb);
	}

	/**
	 * Highest sort_order within one scope — the global categories when $listId
	 * is null, otherwise the ones bound to that list — or -1 when the scope is
	 * empty. Categories of a scope occupy a contiguous run of the house's
	 * sequence, so `max + 1` appends to the end of that run.
	 */
	public function findMaxSortOrderInScope(int $houseId, ?int $listId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->max('sort_order'))
			->from($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)));
		if ($listId === null) {
			$qb->andWhere($qb->expr()->isNull('list_id'));
		} else {
			$qb->andWhere($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)));
		}
		return $this->fetchMaxSortOrder($qb);
	}

	/**
	 * Runs a MAX(sort_order) query, reporting an empty result as -1 so callers
	 * can append with `max + 1` without special-casing it.
	 */
	private function fetchMaxSortOrder(IQueryBuilder $qb): int {
		$result = $qb->executeQuery();
		$max = $result->fetchOne();
		$result->closeCursor();
		return $max === null || $max === false ? -1 : (int)$max;
	}

	/**
	 * Open a slot at $from by pushing every category at or past it one step
	 * down, so an insert there lands between two runs instead of tying with
	 * whichever category already holds the position.
	 */
	public function shiftSortOrderFrom(int $houseId, int $from): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('sort_order', $qb->createFunction('sort_order + 1'))
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->gte('sort_order', $qb->createNamedParameter($from, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findById(int $id): Category {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * Find a category by name within a single scope. The scope is a house plus
	 * a list: a null $listId matches the house-global scope, a set $listId
	 * matches that list's own scope. Used to enforce per-scope name uniqueness.
	 */
	public function findByHouseListAndName(int $houseId, ?int $listId, string $name): ?Category {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR)));
		if ($listId === null) {
			$qb->andWhere($qb->expr()->isNull('list_id'));
		} else {
			$qb->andWhere($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)));
		}
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * Delete every category scoped to the given list. Global categories
	 * (null list_id) are untouched. Used when a list is permanently removed.
	 */
	public function deleteByList(int $listId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	public function deleteByHouse(int $houseId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * Clear category_id on any items that reference the given category.
	 */
	public function detachFromItems(int $categoryId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update(Application::tableName('list_items'))
			->set('category_id', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
			->where($qb->expr()->eq('category_id', $qb->createNamedParameter($categoryId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * Clear category_id on items that reference the category but live on a
	 * different list than the given one. Used when a category becomes scoped to
	 * a single list: items on other lists can no longer carry it.
	 */
	public function detachFromItemsNotInList(int $categoryId, int $listId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update(Application::tableName('list_items'))
			->set('category_id', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
			->where($qb->expr()->eq('category_id', $qb->createNamedParameter($categoryId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->neq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
