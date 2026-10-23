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
 * @template-extends QBMapper<ListTemplate>
 */
class ListTemplateMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, Application::tableName('list_templates'), ListTemplate::class);
	}

	/**
	 * Templates explicitly attached to a list.
	 *
	 * @return list<int>
	 */
	public function findTemplateIdsForList(int $listId): array {
		return $this->findColumn('template_id', 'list_id', $listId);
	}

	/**
	 * Lists a template is explicitly attached to.
	 *
	 * @return list<int>
	 */
	public function findListIdsForTemplate(int $templateId): array {
		return $this->findColumn('list_id', 'template_id', $templateId);
	}

	/**
	 * Replace the templates explicitly attached to a list.
	 *
	 * @param int[] $templateIds
	 */
	public function setTemplatesForList(int $houseId, int $listId, array $templateIds): void {
		$this->deleteWhere('list_id', $listId);
		foreach (array_values(array_unique(array_map('intval', $templateIds))) as $templateId) {
			$this->insertRow($houseId, $listId, $templateId);
		}
	}

	/**
	 * Replace the lists a template is explicitly attached to.
	 *
	 * @param int[] $listIds
	 */
	public function setListsForTemplate(int $houseId, int $templateId, array $listIds): void {
		$this->deleteWhere('template_id', $templateId);
		foreach (array_values(array_unique(array_map('intval', $listIds))) as $listId) {
			$this->insertRow($houseId, $listId, $templateId);
		}
	}

	/**
	 * Drop every attachment a list takes part in, on either side.
	 */
	public function deleteByList(int $listId): void {
		$this->deleteWhere('list_id', $listId);
		$this->deleteWhere('template_id', $listId);
	}

	public function deleteByHouse(int $houseId): void {
		$this->deleteWhere('house_id', $houseId);
	}

	private function insertRow(int $houseId, int $listId, int $templateId): void {
		$row = new ListTemplate();
		$row->setHouseId($houseId);
		$row->setListId($listId);
		$row->setTemplateId($templateId);
		$this->insert($row);
	}

	/**
	 * @return list<int>
	 */
	private function findColumn(string $select, string $whereColumn, int $value): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select($select)
			->from($this->getTableName())
			->where($qb->expr()->eq($whereColumn, $qb->createNamedParameter($value, IQueryBuilder::PARAM_INT)))
			->orderBy('id', 'ASC');
		return array_values(array_map('intval', $qb->executeQuery()->fetchAll(\PDO::FETCH_COLUMN)));
	}

	private function deleteWhere(string $column, int $value): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq($column, $qb->createNamedParameter($value, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
