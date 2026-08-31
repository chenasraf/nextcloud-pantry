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
	 *
	 * @return int[] Field ids whose reminder was re-armed (a date value newly set
	 *               or changed, clearing the notified stamp) — the caller
	 *               withdraws any stale reminder for these so the scan re-fires it.
	 */
	public function setValuesForItem(int $itemId, array $values): array {
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

		$rearmed = [];
		foreach ($byField as $fieldId => $value) {
			$row = $existing[$fieldId] ?? null;
			$newDate = isset($value['valueDate']) ? $this->intOrNull($value['valueDate']) : null;
			if ($row === null) {
				$row = new FieldValue();
				$row->setItemId($itemId);
				$row->setFieldId($fieldId);
				$this->assignValue($row, $value, $newDate);
				$this->insert($row);
				if ($newDate !== null) {
					$rearmed[] = $fieldId;
				}
				continue;
			}
			// Re-arm only when the date actually changes, so an unchanged value
			// keeps its stamp and does not re-notify.
			if ($row->getValueDate() !== $newDate) {
				$row->setNotifiedForDate(null);
				$rearmed[] = $fieldId;
			}
			$this->assignValue($row, $value, $newDate);
			$this->update($row);
		}

		foreach ($existing as $fieldId => $row) {
			if (!array_key_exists($fieldId, $byField)) {
				$this->delete($row);
			}
		}

		return $rearmed;
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

	/**
	 * Count how many stored values reference each of the given options. Options
	 * with no values are absent from the returned map.
	 *
	 * @param int[] $optionIds
	 *
	 * @return array<int, int> option id → number of values referencing it
	 */
	public function countByOptions(array $optionIds): array {
		if ($optionIds === []) {
			return [];
		}
		$optionIds = array_values(array_unique(array_map('intval', $optionIds)));
		$qb = $this->db->getQueryBuilder();
		$qb->select('value_option_id')
			->selectAlias($qb->func()->count('*'), 'cnt')
			->from($this->getTableName())
			->where($qb->expr()->in('value_option_id', $qb->createNamedParameter($optionIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->groupBy('value_option_id');
		$out = [];
		$result = $qb->executeQuery();
		/** @var array{value_option_id: int|string, cnt: int|string} $row */
		foreach ($result->fetchAll() as $row) {
			$out[(int)$row['value_option_id']] = (int)$row['cnt'];
		}
		$result->closeCursor();
		return $out;
	}

	/**
	 * Rewrite every value referencing one option to reference another. Used when
	 * a `select` option in use is deleted with the remap action.
	 */
	public function remapOption(int $fromOptionId, int $toOptionId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('value_option_id', $qb->createNamedParameter($toOptionId, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('value_option_id', $qb->createNamedParameter($fromOptionId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * Null the reference of every value pointing at one option. Used when a
	 * `select` option in use is deleted with the clear action.
	 */
	public function clearOption(int $optionId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('value_option_id', $qb->createNamedParameter(null))
			->where($qb->expr()->eq('value_option_id', $qb->createNamedParameter($optionId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * Date values whose field-level reminder is due and not yet notified for the
	 * current date. A value qualifies when its field is an enabled (`notify_default`)
	 * date field, neither the item nor the field is soft-deleted, the lead window has
	 * opened (`value_date - lead*86400 <= now`, no upper bound so overdue still
	 * fires), the value is not already stamped for this date, and the item is not
	 * done when the field stops reminding on done.
	 *
	 * @return list<array{itemId: int, fieldId: int, valueDate: int, listId: int, houseId: int, itemName: string, fieldName: string}>
	 */
	public function findDueReminders(int $now): array {
		$defs = Application::tableName('field_defs');
		$items = Application::tableName('list_items');
		$lists = Application::tableName('lists');

		$qb = $this->db->getQueryBuilder();
		$qb->select('fv.item_id', 'fv.field_id', 'fv.value_date', 'i.list_id', 'i.name')
			->selectAlias('fd.name', 'field_name')
			->selectAlias('l.house_id', 'house_id')
			->from($this->getTableName(), 'fv')
			->innerJoin('fv', $defs, 'fd', $qb->expr()->eq('fd.id', 'fv.field_id'))
			->innerJoin('fv', $items, 'i', $qb->expr()->eq('i.id', 'fv.item_id'))
			->innerJoin('i', $lists, 'l', $qb->expr()->eq('l.id', 'i.list_id'))
			->where($qb->expr()->eq('fd.type', $qb->createNamedParameter(FieldDefinition::TYPE_DATE)))
			->andWhere($qb->expr()->isNotNull('fv.value_date'))
			->andWhere($qb->expr()->eq('fd.notify_default', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->isNull('fd.deleted_at'))
			->andWhere($qb->expr()->isNull('i.deleted_at'))
			->andWhere($qb->expr()->orX(
				$qb->expr()->isNull('fv.notified_for_date'),
				$qb->expr()->neq('fv.notified_for_date', 'fv.value_date'),
			))
			->andWhere('fv.value_date - (fd.lead_days * 86400) <= ' . $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT))
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('fd.stop_when_done', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)),
				$qb->expr()->eq('i.done', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)),
			));

		$out = [];
		$result = $qb->executeQuery();
		/** @var array{item_id: int|string, field_id: int|string, value_date: int|string, list_id: int|string, name: ?string, field_name: ?string, house_id: int|string} $row */
		foreach ($result->fetchAll() as $row) {
			$out[] = [
				'itemId' => (int)$row['item_id'],
				'fieldId' => (int)$row['field_id'],
				'valueDate' => (int)$row['value_date'],
				'listId' => (int)$row['list_id'],
				'houseId' => (int)$row['house_id'],
				'itemName' => $row['name'] ?? '',
				'fieldName' => $row['field_name'] ?? '',
			];
		}
		$result->closeCursor();
		return $out;
	}

	/**
	 * Stamp one value as notified for the given date, making the reminder
	 * one-shot until the date changes.
	 */
	public function stampNotified(int $itemId, int $fieldId, int $valueDate): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('notified_for_date', $qb->createNamedParameter($valueDate, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('item_id', $qb->createNamedParameter($itemId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('field_id', $qb->createNamedParameter($fieldId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * Clear the notified stamp of the given fields on one item, re-arming their
	 * reminders so the scan fires them again (used when an item is un-done and a
	 * `stop_when_done` field must resume reminding).
	 *
	 * @param int[] $fieldIds
	 */
	public function clearStamp(int $itemId, array $fieldIds): void {
		if ($fieldIds === []) {
			return;
		}
		$fieldIds = array_values(array_unique(array_map('intval', $fieldIds)));
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('notified_for_date', $qb->createNamedParameter(null))
			->where($qb->expr()->eq('item_id', $qb->createNamedParameter($itemId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->in('field_id', $qb->createNamedParameter($fieldIds, IQueryBuilder::PARAM_INT_ARRAY)));
		$qb->executeStatement();
	}

	/**
	 * The field ids of every date-typed value on one item. Used to withdraw all
	 * of an item's reminders when it is soft-deleted.
	 *
	 * @return int[]
	 */
	public function findDateFieldIdsForItem(int $itemId): array {
		return $this->dateFieldIds($itemId, null);
	}

	/**
	 * The field ids of the item's date values whose field stops reminding once
	 * the item is done. Used to withdraw (on done) or re-arm (on un-done) exactly
	 * those reminders.
	 *
	 * @return int[]
	 */
	public function findStopWhenDoneFieldIdsForItem(int $itemId): array {
		return $this->dateFieldIds($itemId, true);
	}

	/**
	 * Item ids that hold a date value for the given field. Used to withdraw a
	 * field's reminders across items when it is disabled, re-lead, or deleted.
	 *
	 * @return int[]
	 */
	public function findItemIdsWithDateValueForField(int $fieldId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('item_id')
			->from($this->getTableName())
			->where($qb->expr()->eq('field_id', $qb->createNamedParameter($fieldId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNotNull('value_date'));
		return array_map('intval', $qb->executeQuery()->fetchAll(\PDO::FETCH_COLUMN));
	}

	/**
	 * @return int[]
	 */
	private function dateFieldIds(int $itemId, ?bool $stopWhenDone): array {
		$defs = Application::tableName('field_defs');
		$qb = $this->db->getQueryBuilder();
		$qb->select('fv.field_id')
			->from($this->getTableName(), 'fv')
			->innerJoin('fv', $defs, 'fd', $qb->expr()->eq('fd.id', 'fv.field_id'))
			->where($qb->expr()->eq('fv.item_id', $qb->createNamedParameter($itemId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('fd.type', $qb->createNamedParameter(FieldDefinition::TYPE_DATE)));
		if ($stopWhenDone !== null) {
			$qb->andWhere($qb->expr()->eq('fd.stop_when_done', $qb->createNamedParameter($stopWhenDone, IQueryBuilder::PARAM_BOOL)));
		}
		return array_map('intval', $qb->executeQuery()->fetchAll(\PDO::FETCH_COLUMN));
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
