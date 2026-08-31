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
 * @template-extends QBMapper<FieldDefinition>
 */
class FieldDefinitionMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, Application::tableName('field_defs'), FieldDefinition::class);
	}

	/**
	 * Every live (non-soft-deleted) definition in a house, both house-wide and
	 * list-scoped, ordered for display.
	 *
	 * @return FieldDefinition[]
	 */
	public function findByHouse(int $houseId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('deleted_at'))
			->orderBy('sort_order', 'ASC')
			->addOrderBy('name', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findById(int $id): FieldDefinition {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * Find a live definition by name within a single scope (a house plus a list;
	 * a null $listId matches the house-wide scope). Used to enforce per-scope
	 * name uniqueness. Soft-deleted definitions do not collide.
	 */
	public function findByHouseListAndName(int $houseId, ?int $listId, string $name): ?FieldDefinition {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->isNull('deleted_at'));
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
	 * Highest sort_order currently used in a house, or -1 when the house has no
	 * definitions yet — so a caller can assign `max + 1` to append at the end.
	 */
	public function findMaxSortOrder(int $houseId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->max('sort_order'))
			->from($this->getTableName())
			->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$max = $result->fetchOne();
		$result->closeCursor();
		return $max === null || $max === false ? -1 : (int)$max;
	}

	/**
	 * Hard-delete every definition scoped to the given list. House-wide
	 * definitions (null list_id) are untouched. Used when a list is permanently
	 * removed.
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
}
