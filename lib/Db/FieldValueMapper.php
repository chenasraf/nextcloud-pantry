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
 * @template-extends QBMapper<FieldValue>
 */
class FieldValueMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, Application::tableName('field_values'), FieldValue::class);
	}

	/**
	 * The item's custom-field values, each in serialized shape.
	 *
	 * @return list<array{fieldId: int, valueText: ?string, valueNumber: ?float, valueBool: bool, valueDate: ?int, valueOptionId: ?int, offsetDays: ?int, notifyOverride: bool, notifyEnabled: bool, notifyLeadDays: ?int}>
	 */
	public function findForItem(int $itemId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('item_id', $qb->createNamedParameter($itemId, IQueryBuilder::PARAM_INT)));
		return array_map(
			static fn (FieldValue $v) => $v->jsonSerialize(),
			$this->findEntities($qb),
		);
	}

	/**
	 * Resolve the custom-field values of each of the given items in a single
	 * query. Items with no values are absent from the returned map.
	 *
	 * @param int[] $itemIds
	 *
	 * @return array<int, list<array{fieldId: int, valueText: ?string, valueNumber: ?float, valueBool: bool, valueDate: ?int, valueOptionId: ?int, offsetDays: ?int, notifyOverride: bool, notifyEnabled: bool, notifyLeadDays: ?int}>>
	 */
	public function findForItems(array $itemIds): array {
		if ($itemIds === []) {
			return [];
		}
		$itemIds = array_values(array_unique(array_map('intval', $itemIds)));
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->in('item_id', $qb->createNamedParameter($itemIds, IQueryBuilder::PARAM_INT_ARRAY)));
		$map = [];
		foreach ($this->findEntities($qb) as $value) {
			$map[$value->getItemId()][] = $value->jsonSerialize();
		}
		return $map;
	}

	/**
	 * Replace the item's full set of custom-field values. Entries are keyed by
	 * field id (last wins), so at most one value survives per field. Fields
	 * absent from the new set have their value row removed.
	 *
	 * Rows are upserted rather than delete-then-inserted so an unchanged value
	 * keeps its `notified_for_date` reminder stamp. A changed (or newly set)
	 * `value_date` clears the stamp, re-arming the reminder for the new date.
	 *
	 * @param array<array-key, mixed> $values Each entry is a value array keyed as
	 *                                        {fieldId, valueText?, valueNumber?, valueBool?, valueDate?, valueOptionId?,
	 *                                        offsetDays?, notifyOverride?, notifyEnabled?, notifyLeadDays?}.
	 */
	public function setValuesForItem(int $itemId, array $values): void {
		$existing = [];
		foreach ($this->findEntitiesForItem($itemId) as $row) {
			$existing[$row->getFieldId()] = $row;
		}

		$byField = [];
		foreach ($values as $value) {
			if (!is_array($value)) {
				continue;
			}
			$fieldId = (int)($value['fieldId'] ?? 0);
			if ($fieldId <= 0) {
				continue;
			}
			$byField[$fieldId] = $value;
		}

		foreach ($byField as $fieldId => $value) {
			$row = $existing[$fieldId] ?? null;
			$newDate = isset($value['valueDate']) ? $this->intOrNull($value['valueDate']) : null;
			if ($row === null) {
				$row = new FieldValue();
				$row->setItemId($itemId);
				$row->setFieldId($fieldId);
				$this->assignValue($row, $value, $newDate);
				$this->insert($row);
				continue;
			}
			// Re-arm only when the date actually changes, so an unchanged value
			// keeps its stamp and does not re-notify.
			if ($row->getValueDate() !== $newDate) {
				$row->setNotifiedForDate(null);
			}
			$this->assignValue($row, $value, $newDate);
			$this->update($row);
		}

		foreach ($existing as $fieldId => $row) {
			if (!array_key_exists($fieldId, $byField)) {
				$this->delete($row);
			}
		}
	}

	/**
	 * @param array<array-key, mixed> $value
	 */
	private function assignValue(FieldValue $row, array $value, ?int $valueDate): void {
		$row->setValueText(isset($value['valueText']) ? $this->stringOrNull($value['valueText']) : null);
		$row->setValueNumber(isset($value['valueNumber']) ? $this->floatOrNull($value['valueNumber']) : null);
		$row->setValueBool((bool)($value['valueBool'] ?? false));
		$row->setValueDate($valueDate);
		$row->setValueOptionId(isset($value['valueOptionId']) ? $this->intOrNull($value['valueOptionId']) : null);
		$row->setOffsetDays(isset($value['offsetDays']) ? $this->intOrNull($value['offsetDays']) : null);
		$row->setNotifyOverride((bool)($value['notifyOverride'] ?? false));
		$row->setNotifyEnabled((bool)($value['notifyEnabled'] ?? false));
		$row->setNotifyLeadDays(isset($value['notifyLeadDays']) ? $this->intOrNull($value['notifyLeadDays']) : null);
	}

	public function deleteByItem(int $itemId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('item_id', $qb->createNamedParameter($itemId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	public function deleteByField(int $fieldId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('field_id', $qb->createNamedParameter($fieldId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * Remove every value row for the items belonging to a house. Used when a
	 * house is deleted (its lists and items are removed wholesale).
	 */
	public function deleteByHouse(int $houseId): void {
		$items = Application::tableName('list_items');
		$lists = Application::tableName('lists');
		$lookup = $this->db->getQueryBuilder();
		$lookup->select('i.id')
			->from($items, 'i')
			->innerJoin('i', $lists, 'l', $lookup->expr()->eq('i.list_id', 'l.id'))
			->where($lookup->expr()->eq('l.house_id', $lookup->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)));
		$itemIds = array_map('intval', $lookup->executeQuery()->fetchAll(\PDO::FETCH_COLUMN));
		if ($itemIds === []) {
			return;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->in('item_id', $qb->createNamedParameter($itemIds, IQueryBuilder::PARAM_INT_ARRAY)));
		$qb->executeStatement();
	}

	/**
	 * @return FieldValue[]
	 */
	private function findEntitiesForItem(int $itemId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('item_id', $qb->createNamedParameter($itemId, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	private function intOrNull(mixed $v): ?int {
		return $v === null || $v === '' ? null : (int)$v;
	}

	private function floatOrNull(mixed $v): ?float {
		return $v === null || $v === '' ? null : (float)$v;
	}

	private function stringOrNull(mixed $v): ?string {
		return $v === null ? null : (string)$v;
	}
}
