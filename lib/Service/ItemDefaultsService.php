<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Db\CategoryMapper;
use OCA\Pantry\Db\Checklist;
use OCA\Pantry\Db\FieldDefinition;
use OCA\Pantry\Db\FieldDefinitionMapper;
use OCA\Pantry\Db\FieldOptionMapper;
use OCA\Pantry\Db\LabelMapper;
use OCA\Pantry\Db\StoreMapper;
use OCA\Pantry\Exception\ForbiddenException;

/**
 * Validates writes to a list's item defaults and drops references that no
 * longer resolve when reading them.
 *
 * Stale ids are pruned on read rather than cascaded on delete, so removing a
 * store, label, category, field or option never has to touch list rows.
 *
 * @psalm-import-type ItemDefaultsEntry from ItemDefaults
 * @psalm-import-type ItemDefaultsField from ItemDefaults
 * @psalm-type HouseRefs = array{stores: array<int, true>, categories: array<int, int|null>, labels: array<int, int|null>, fields: array<int, FieldDefinition>, options: array<int, array<int, true>>}
 */
class ItemDefaultsService {
	/** @var array<int, HouseRefs> */
	private array $refsByHouse = [];

	public function __construct(
		private RecurrenceService $recurrence,
		private StoreMapper $storeMapper,
		private CategoryMapper $categoryMapper,
		private LabelMapper $labelMapper,
		private FieldDefinitionMapper $fieldDefMapper,
		private FieldOptionMapper $fieldOptionMapper,
	) {
	}

	/**
	 * Merge a patch into the list's defaults, key by key (custom fields by
	 * field id), and return the result.
	 *
	 * Without edit rights only a key already in "remember" mode may change, and
	 * only its value — that is the write-back the add-item form does for everyone
	 * who can add items.
	 *
	 * @param array<array-key, mixed> $patch
	 * @return array<string, mixed>
	 * @throws \InvalidArgumentException on an unknown key, mode or reference.
	 * @throws ForbiddenException when the caller may not change a key.
	 */
	public function merge(Checklist $list, array $patch, bool $canEdit): array {
		$houseId = $list->getHouseId();
		$listId = (int)$list->getId();
		$stored = ItemDefaults::decode($list->getItemDefaults());
		$defaults = $stored;

		foreach ($patch as $key => $incoming) {
			if ($key === ItemDefaults::KEY_FIELDS) {
				$fields = $this->mergeFields($houseId, $listId, $stored[ItemDefaults::KEY_FIELDS] ?? [], $incoming, $canEdit);
				if ($fields === []) {
					unset($defaults[ItemDefaults::KEY_FIELDS]);
				} else {
					$defaults[ItemDefaults::KEY_FIELDS] = $fields;
				}
				continue;
			}
			if (!in_array($key, ItemDefaults::ENTRY_KEYS, true)) {
				throw new \InvalidArgumentException('Unknown item default: ' . $key);
			}
			$incoming = $this->authorize($stored[$key] ?? null, $incoming, $canEdit);
			$entry = $this->normalizeEntry($houseId, $listId, $key, $incoming);
			if ($entry === null) {
				unset($defaults[$key]);
			} else {
				$defaults[$key] = $entry;
			}
		}

		return $defaults;
	}

	/**
	 * The list's defaults as clients see them: every key present, and anything
	 * pointing at a deleted or out-of-scope store, category, label, field or
	 * option dropped.
	 *
	 * @return array{recurrence: ItemDefaultsEntry, stores: ItemDefaultsEntry, category: ItemDefaultsEntry, labels: ItemDefaultsEntry, quantity: ItemDefaultsEntry, fields: list<ItemDefaultsField>}
	 */
	public function forList(Checklist $list): array {
		$refs = $this->refs($list->getHouseId());
		$listId = (int)$list->getId();
		$defaults = ItemDefaults::complete(ItemDefaults::decode($list->getItemDefaults()));

		$stores = $defaults[ItemDefaults::KEY_STORES]['value'] ?? null;
		if (is_array($stores)) {
			$defaults[ItemDefaults::KEY_STORES]['value'] = array_values(array_filter(
				$this->ints($stores),
				static fn (int $id): bool => isset($refs['stores'][$id]),
			));
		}

		$labels = $defaults[ItemDefaults::KEY_LABELS]['value'] ?? null;
		if (is_array($labels)) {
			$defaults[ItemDefaults::KEY_LABELS]['value'] = array_values(array_filter(
				$this->ints($labels),
				fn (int $id): bool => $this->inScope($refs['labels'], $id, $listId),
			));
		}

		$category = $defaults[ItemDefaults::KEY_CATEGORY]['value'] ?? null;
		if ($category !== null && !$this->inScope($refs['categories'], is_numeric($category) ? (int)$category : 0, $listId)) {
			$defaults[ItemDefaults::KEY_CATEGORY]['value'] = null;
		}

		$fields = [];
		foreach ($defaults[ItemDefaults::KEY_FIELDS] as $field) {
			$def = $refs['fields'][$field['fieldId']] ?? null;
			if ($def === null || !$this->fieldApplies($def, $listId)) {
				continue;
			}
			$value = $field['value'] ?? null;
			$optionId = is_array($value) ? ($value['valueOptionId'] ?? null) : null;
			if (is_int($optionId) && !isset($refs['options'][$field['fieldId']][$optionId])) {
				if ($field['mode'] !== ItemDefaults::MODE_REMEMBER) {
					continue;
				}
				$field['value'] = null;
			}
			$fields[] = $field;
		}
		$defaults[ItemDefaults::KEY_FIELDS] = $fields;

		return $defaults;
	}

	/**
	 * @param list<ItemDefaultsField> $current
	 * @return list<array<string, mixed>>
	 */
	private function mergeFields(int $houseId, int $listId, array $current, mixed $incoming, bool $canEdit): array {
		if (!is_array($incoming)) {
			throw new \InvalidArgumentException('Custom field defaults must be a list');
		}
		$byId = [];
		foreach ($current as $field) {
			$byId[$field['fieldId']] = $field;
		}
		foreach ($incoming as $field) {
			if (!is_array($field) || (int)($field['fieldId'] ?? 0) <= 0) {
				throw new \InvalidArgumentException('Custom field default needs a fieldId');
			}
			$fieldId = (int)$field['fieldId'];
			$field = $this->authorize($byId[$fieldId] ?? null, $field, $canEdit);
			$entry = $this->normalizeField($houseId, $listId, $fieldId, $field);
			if ($entry === null) {
				unset($byId[$fieldId]);
			} else {
				$byId[$fieldId] = $entry;
			}
		}
		return array_values($byId);
	}

	/**
	 * @param array<array-key, mixed>|null $current
	 * @return array<array-key, mixed>
	 * @throws ForbiddenException
	 */
	private function authorize(?array $current, mixed $incoming, bool $canEdit): array {
		if ($canEdit) {
			return is_array($incoming) ? $incoming : ['mode' => ItemDefaults::MODE_NONE];
		}
		if (($current['mode'] ?? null) !== ItemDefaults::MODE_REMEMBER
			|| !is_array($incoming)
			|| (isset($incoming['mode']) && $incoming['mode'] !== ItemDefaults::MODE_REMEMBER)) {
			throw new ForbiddenException('Only remembered item defaults can be updated without edit rights');
		}
		$incoming['mode'] = ItemDefaults::MODE_REMEMBER;
		return $incoming;
	}

	/**
	 * @param array<array-key, mixed> $entry
	 * @return array<string, mixed>|null Null when the key falls back to the composer's empty state.
	 */
	private function normalizeEntry(int $houseId, int $listId, string $key, array $entry): ?array {
		$mode = $this->mode($entry, $key === ItemDefaults::KEY_QUANTITY
			? [ItemDefaults::MODE_NONE, ItemDefaults::MODE_FIXED]
			: ItemDefaults::MODES);
		if ($mode === ItemDefaults::MODE_NONE) {
			return null;
		}
		if (!array_key_exists('value', $entry) || $entry['value'] === null) {
			// "Remember" without anything remembered yet starts empty; "fixed"
			// to nothing is the same as not setting a default.
			return $mode === ItemDefaults::MODE_REMEMBER ? ['mode' => $mode] : null;
		}
		$refs = $this->refs($houseId);
		$value = $entry['value'];

		switch ($key) {
			case ItemDefaults::KEY_RECURRENCE:
				if (!is_array($value)) {
					throw new \InvalidArgumentException('Recurrence default must be an object');
				}
				$value = ItemDefaults::normalizeRecurrence($value, $this->recurrence);
				break;
			case ItemDefaults::KEY_STORES:
				$value = $this->idList($value);
				foreach ($value as $id) {
					if (!isset($refs['stores'][$id])) {
						throw new \InvalidArgumentException('Store does not belong to this house: ' . $id);
					}
				}
				break;
			case ItemDefaults::KEY_LABELS:
				$value = $this->idList($value);
				foreach ($value as $id) {
					if (!$this->inScope($refs['labels'], $id, $listId)) {
						throw new \InvalidArgumentException('Label does not apply to this list: ' . $id);
					}
				}
				break;
			case ItemDefaults::KEY_CATEGORY:
				$value = (int)$value;
				if (!$this->inScope($refs['categories'], $value, $listId)) {
					throw new \InvalidArgumentException('Category does not apply to this list: ' . $value);
				}
				break;
			case ItemDefaults::KEY_QUANTITY:
				$value = trim((string)$value);
				if ($value === '') {
					return null;
				}
				break;
		}

		return ['mode' => $mode, 'value' => $value];
	}

	/**
	 * @param array<array-key, mixed> $entry
	 * @return array<string, mixed>|null Null when the field inherits its definition's default.
	 */
	private function normalizeField(int $houseId, int $listId, int $fieldId, array $entry): ?array {
		$mode = $this->mode($entry, ItemDefaults::MODES);
		if ($mode === ItemDefaults::MODE_NONE) {
			return null;
		}
		$refs = $this->refs($houseId);
		$def = $refs['fields'][$fieldId] ?? null;
		if ($def === null || !$this->fieldApplies($def, $listId)) {
			throw new \InvalidArgumentException('Custom field does not apply to this list: ' . $fieldId);
		}
		$value = $entry['value'] ?? null;
		if (!is_array($value)) {
			return $mode === ItemDefaults::MODE_REMEMBER ? ['fieldId' => $fieldId, 'mode' => $mode] : null;
		}
		return ['fieldId' => $fieldId, 'mode' => $mode, 'value' => $this->fieldValue($def, $value, $refs)];
	}

	/**
	 * Keep only the value column the field's type uses. Reminder settings are
	 * per item and never part of a default.
	 *
	 * @param array<array-key, mixed> $value
	 * @param HouseRefs $refs
	 * @return array<string, mixed>
	 */
	private function fieldValue(FieldDefinition $def, array $value, array $refs): array {
		switch ($def->getType()) {
			case FieldDefinition::TYPE_TEXT:
				return ['valueText' => isset($value['valueText']) ? (string)$value['valueText'] : null];
			case FieldDefinition::TYPE_NUMBER:
				$number = $value['valueNumber'] ?? null;
				return ['valueNumber' => is_numeric($number) ? (float)$number : null];
			case FieldDefinition::TYPE_CHECKBOX:
				return ['valueBool' => (bool)($value['valueBool'] ?? false)];
			case FieldDefinition::TYPE_SELECT:
				$optionId = isset($value['valueOptionId']) ? (int)$value['valueOptionId'] : null;
				if ($optionId !== null && !isset($refs['options'][(int)$def->getId()][$optionId])) {
					throw new \InvalidArgumentException('Option does not belong to this field: ' . $optionId);
				}
				return ['valueOptionId' => $optionId];
			case FieldDefinition::TYPE_DATE:
				if ($def->getDateMode() === FieldDefinition::DATE_RELATIVE) {
					return ['offsetDays' => isset($value['offsetDays']) ? (int)$value['offsetDays'] : null];
				}
				return ['valueDate' => isset($value['valueDate']) ? (int)$value['valueDate'] : null];
		}
		return [];
	}

	/**
	 * @param array<array-key, mixed> $entry
	 * @param list<string> $allowed
	 */
	private function mode(array $entry, array $allowed): string {
		$mode = (string)($entry['mode'] ?? ItemDefaults::MODE_NONE);
		if (!in_array($mode, $allowed, true)) {
			throw new \InvalidArgumentException('Unknown item default mode: ' . $mode);
		}
		return $mode;
	}

	/**
	 * @return list<int>
	 */
	private function idList(mixed $value): array {
		if (!is_array($value)) {
			throw new \InvalidArgumentException('Expected a list of ids');
		}
		return array_values(array_unique($this->ints($value)));
	}

	/**
	 * @param array<array-key, mixed> $values
	 * @return list<int>
	 */
	private function ints(array $values): array {
		return array_values(array_map(static fn (mixed $v): int => is_numeric($v) ? (int)$v : 0, $values));
	}

	/**
	 * @param array<int, int|null> $scoped id → the list it is scoped to (null = house-wide)
	 */
	private function inScope(array $scoped, int $id, int $listId): bool {
		if (!array_key_exists($id, $scoped)) {
			return false;
		}
		return $scoped[$id] === null || $scoped[$id] === $listId;
	}

	private function fieldApplies(FieldDefinition $def, int $listId): bool {
		$scope = $def->getListId();
		return $scope === null || $scope === $listId;
	}

	/**
	 * Everything a default can point at in the house, loaded once per request
	 * so serializing a whole list index costs a fixed number of queries.
	 *
	 * @return HouseRefs
	 */
	private function refs(int $houseId): array {
		if (isset($this->refsByHouse[$houseId])) {
			return $this->refsByHouse[$houseId];
		}
		$refs = ['stores' => [], 'categories' => [], 'labels' => [], 'fields' => [], 'options' => []];
		foreach ($this->storeMapper->findByHouse($houseId) as $store) {
			$refs['stores'][(int)$store->getId()] = true;
		}
		foreach ($this->categoryMapper->findByHouse($houseId) as $category) {
			$refs['categories'][(int)$category->getId()] = $category->getListId();
		}
		foreach ($this->labelMapper->findByHouse($houseId) as $label) {
			$refs['labels'][(int)$label->getId()] = $label->getListId();
		}
		foreach ($this->fieldDefMapper->findByHouse($houseId) as $def) {
			$refs['fields'][(int)$def->getId()] = $def;
		}
		foreach ($this->fieldOptionMapper->findForFields(array_keys($refs['fields'])) as $fieldId => $options) {
			foreach ($options as $option) {
				$refs['options'][$fieldId][(int)$option['id']] = true;
			}
		}
		return $this->refsByHouse[$houseId] = $refs;
	}
}
