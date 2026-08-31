<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCA\Pantry\AppInfo\Application;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<FieldOption>
 */
class FieldOptionMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, Application::tableName('field_options'), FieldOption::class);
	}

	/**
	 * A field's options in display order.
	 *
	 * @return FieldOption[]
	 */
	public function findByField(int $fieldId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('field_id', $qb->createNamedParameter($fieldId, IQueryBuilder::PARAM_INT)))
			->orderBy('sort_order', 'ASC')
			->addOrderBy('id', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * Resolve the options of each of the given fields in a single query. Fields
	 * with no options are absent from the returned map.
	 *
	 * @param int[] $fieldIds
	 *
	 * @return array<int, list<array{id: int, label: string, sortOrder: int}>>
	 */
	public function findForFields(array $fieldIds): array {
		if ($fieldIds === []) {
			return [];
		}
		$fieldIds = array_values(array_unique(array_map('intval', $fieldIds)));
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->in('field_id', $qb->createNamedParameter($fieldIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->orderBy('sort_order', 'ASC')
			->addOrderBy('id', 'ASC');
		$map = [];
		foreach ($this->findEntities($qb) as $option) {
			$map[(int)$option->getFieldId()][] = $option->jsonSerialize();
		}
		return $map;
	}

	public function deleteByField(int $fieldId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('field_id', $qb->createNamedParameter($fieldId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * Remove every option of the list-scoped definitions of a list. Used when a
	 * list is permanently removed (its definitions are deleted alongside).
	 */
	public function deleteByList(int $listId): void {
		$this->deleteWhereFieldMatches('list_id', $listId);
	}

	/**
	 * Remove every option of a house's definitions. Used when a house is deleted.
	 */
	public function deleteByHouse(int $houseId): void {
		$this->deleteWhereFieldMatches('house_id', $houseId);
	}

	private function deleteWhereFieldMatches(string $column, int $value): void {
		$defs = Application::tableName('field_defs');
		$lookup = $this->db->getQueryBuilder();
		$lookup->select('id')
			->from($defs)
			->where($lookup->expr()->eq($column, $lookup->createNamedParameter($value, IQueryBuilder::PARAM_INT)));
		$fieldIds = array_map('intval', $lookup->executeQuery()->fetchAll(\PDO::FETCH_COLUMN));
		if ($fieldIds === []) {
			return;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->in('field_id', $qb->createNamedParameter($fieldIds, IQueryBuilder::PARAM_INT_ARRAY)));
		$qb->executeStatement();
	}
}
