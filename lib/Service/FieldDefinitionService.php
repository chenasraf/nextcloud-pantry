<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Db\ChecklistMapper;
use OCA\Pantry\Db\FieldDefinition;
use OCA\Pantry\Db\FieldDefinitionMapper;
use OCA\Pantry\Db\FieldOption;
use OCA\Pantry\Db\FieldOptionMapper;
use OCA\Pantry\Db\FieldValueMapper;
use OCA\Pantry\Exception\NotFoundException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;

/**
 * Custom-field definitions and their `select` options: creation, per-scope
 * uniqueness, type-specific config, soft-delete, and reorder. Values live on
 * items and are handled elsewhere.
 */
class FieldDefinitionService {
	public const ACTION_REMAP = 'remap';
	public const ACTION_CLEAR = 'clear';

	public function __construct(
		private FieldDefinitionMapper $mapper,
		private FieldOptionMapper $optionMapper,
		private FieldValueMapper $valueMapper,
		private ChecklistMapper $listMapper,
		private CustomFieldReminderService $reminders,
		private IDBConnection $db,
	) {
	}

	/**
	 * Every live definition in a house, serialized with its options attached.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function listForHouse(int $houseId): array {
		$defs = $this->mapper->findByHouse($houseId);
		$optionsByField = $this->optionMapper->findForFields(
			array_map(static fn (FieldDefinition $d): int => (int)$d->getId(), $defs),
		);
		$counts = $this->countOptions($optionsByField);
		return array_map(
			fn (FieldDefinition $d): array => $d->jsonSerialize(
				$this->attachCounts($optionsByField[(int)$d->getId()] ?? [], $counts),
			),
			$defs,
		);
	}

	/**
	 * Serialize a single definition with its options attached, each option
	 * carrying how many stored values reference it.
	 *
	 * @return array<string, mixed>
	 */
	public function serialize(FieldDefinition $def): array {
		$options = array_map(
			static fn (FieldOption $o): array => $o->jsonSerialize(),
			$this->optionMapper->findByField((int)$def->getId()),
		);
		$counts = $this->valueMapper->countByOptions(
			array_map(static fn (array $o): int => $o['id'], $options),
		);
		return $def->jsonSerialize($this->attachCounts($options, $counts));
	}

	/**
	 * Count value references across every option of a field→options map.
	 *
	 * @param array<int, list<array{id: int, label: string, sortOrder: int}>> $optionsByField
	 *
	 * @return array<int, int> option id → number of values referencing it
	 */
	private function countOptions(array $optionsByField): array {
		$ids = [];
		foreach ($optionsByField as $options) {
			foreach ($options as $option) {
				$ids[] = $option['id'];
			}
		}
		return $this->valueMapper->countByOptions($ids);
	}

	/**
	 * Add a `valueCount` to each serialized option from the counts map.
	 *
	 * @param list<array{id: int, label: string, sortOrder: int}> $options
	 * @param array<int, int> $counts
	 *
	 * @return list<array{id: int, label: string, sortOrder: int, valueCount: int}>
	 */
	private function attachCounts(array $options, array $counts): array {
		return array_map(
			static fn (array $o): array => $o + ['valueCount' => $counts[$o['id']] ?? 0],
			$options,
		);
	}

	/**
	 * Create a definition. `$data` carries the type plus the config relevant to
	 * that type; irrelevant columns are left at their defaults.
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return array<string, mixed> The serialized definition (with options).
	 */
	public function create(int $houseId, array $data): array {
		$name = trim((string)($data['name'] ?? ''));
		if ($name === '') {
			throw new \InvalidArgumentException('Field name cannot be empty');
		}
		$type = $this->normalizeType((string)($data['type'] ?? ''));
		$listId = $this->normalizeListId($houseId, $this->intOrNull($data['listId'] ?? null));

		if ($this->mapper->findByHouseListAndName($houseId, $listId, $name) !== null) {
			throw new \InvalidArgumentException('A field with this name already exists');
		}

		$now = time();
		$def = new FieldDefinition();
		$def->setHouseId($houseId);
		$def->setListId($listId);
		$def->setName($name);
		$def->setType($type);
		$def->setSortOrder($this->mapper->findMaxSortOrder($houseId) + 1);
		$def->setCreatedAt($now);
		$def->setUpdatedAt($now);
		$this->applyConfig($def, $type, $data);
		/** @var FieldDefinition $def */
		$def = $this->mapper->insert($def);

		if ($type === FieldDefinition::TYPE_SELECT) {
			$this->replaceOptions((int)$def->getId(), $this->readOptions($data['options'] ?? []));
			$this->applyDefaultOption($def, $data);
		}

		return $this->serialize($def);
	}

	/**
	 * Update a definition. Only keys present in `$patch` are applied. For a
	 * `select` field, an `options` key replaces the option set, diffed by id
	 * (existing id → rename/reorder, no id → create, missing → delete).
	 *
	 * @param array<string, mixed> $patch
	 *
	 * @return array<string, mixed>
	 */
	public function update(int $fieldId, array $patch): array {
		$def = $this->get($fieldId);

		$targetName = $def->getName();
		if (array_key_exists('name', $patch)) {
			$targetName = trim((string)$patch['name']);
			if ($targetName === '') {
				throw new \InvalidArgumentException('Field name cannot be empty');
			}
		}
		$targetListId = $def->getListId();
		if (array_key_exists('listId', $patch)) {
			$targetListId = $this->normalizeListId($def->getHouseId(), $this->intOrNull($patch['listId']));
		}
		if ($targetName !== $def->getName() || $targetListId !== $def->getListId()) {
			$existing = $this->mapper->findByHouseListAndName($def->getHouseId(), $targetListId, $targetName);
			if ($existing !== null && (int)$existing->getId() !== $fieldId) {
				throw new \InvalidArgumentException('A field with this name already exists');
			}
		}
		$def->setName($targetName);
		$def->setListId($targetListId);

		// Only enabling/disabling the reminder or changing its lead-time invalidates
		// live reminders; `stop_when_done` takes effect when the item is toggled done,
		// not when the field is edited.
		$reminderBefore = [$def->getNotifyDefault(), $def->getLeadDays()];
		$this->applyConfig($def, $def->getType(), $patch, partial: true);
		$reminderChanged = $reminderBefore !== [$def->getNotifyDefault(), $def->getLeadDays()];

		if ($def->getType() === FieldDefinition::TYPE_SELECT && array_key_exists('options', $patch)) {
			$this->replaceOptions($fieldId, $this->readOptions($patch['options']), diff: true);
		}
		if (array_key_exists('defaultOptionId', $patch)) {
			$this->applyDefaultOption($def, $patch);
		}

		$def->setUpdatedAt(time());
		$this->mapper->update($def);
		// A reminder that is now off, re-lead, or newly stops-when-done must
		// withdraw its live reminders; the scan re-fires whatever still qualifies.
		if ($def->getType() === FieldDefinition::TYPE_DATE && $reminderChanged) {
			$this->reminders->onFieldRemindersInvalidated($fieldId);
		}
		return $this->serialize($def);
	}

	/**
	 * Soft-delete a definition: its values are kept but hidden. The options rows
	 * remain so any kept values still resolve their labels.
	 */
	public function delete(int $fieldId): void {
		$def = $this->get($fieldId);
		$def->setDeletedAt(time());
		$def->setUpdatedAt(time());
		$this->mapper->update($def);
		if ($def->getType() === FieldDefinition::TYPE_DATE) {
			$this->reminders->onFieldRemindersInvalidated($fieldId);
		}
	}

	/**
	 * Delete a single `select` option. An option with no stored values is removed
	 * outright. An option in use requires an action: `remap` rewrites every
	 * affected value to another option of the same field (`$remapToId`), `clear`
	 * nulls them. The value rewrite and the option delete run in one transaction.
	 *
	 * @return array<string, mixed> The reserialized definition.
	 */
	public function deleteOption(int $fieldId, int $optionId, ?string $action, ?int $remapToId): array {
		$def = $this->get($fieldId);
		$options = $this->optionMapper->findByField($fieldId);
		$target = null;
		foreach ($options as $option) {
			if ($option->getId() === $optionId) {
				$target = $option;
				break;
			}
		}
		if ($target === null) {
			throw new NotFoundException('Option not found');
		}

		$inUse = ($this->valueMapper->countByOptions([$optionId])[$optionId] ?? 0) > 0;

		$remapTo = null;
		if ($inUse) {
			if ($action === self::ACTION_REMAP) {
				if ($remapToId === null || $remapToId === $optionId) {
					throw new \InvalidArgumentException('Choose another option to remap the values to');
				}
				foreach ($options as $option) {
					if ($option->getId() === $remapToId) {
						$remapTo = $option;
						break;
					}
				}
				if ($remapTo === null) {
					throw new \InvalidArgumentException('Remap target does not belong to this field');
				}
			} elseif ($action !== self::ACTION_CLEAR) {
				throw new \InvalidArgumentException('This option is in use; choose to remap or clear its values');
			}
		}

		$this->db->beginTransaction();
		try {
			if ($remapTo !== null) {
				$this->valueMapper->remapOption($optionId, $remapTo->getId());
			} elseif ($inUse) {
				$this->valueMapper->clearOption($optionId);
			}
			if ($def->getDefaultOptionId() === $optionId) {
				$def->setDefaultOptionId($remapTo?->getId());
				$def->setUpdatedAt(time());
				$this->mapper->update($def);
			}
			$this->optionMapper->delete($target);
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}

		return $this->serialize($this->get($fieldId));
	}

	/**
	 * Batch reorder definitions in a house.
	 *
	 * @param list<array{id: int, sortOrder: int}> $items
	 */
	public function reorder(int $houseId, array $items): void {
		foreach ($items as $entry) {
			$id = (int)($entry['id'] ?? 0);
			$sortOrder = (int)($entry['sortOrder'] ?? 0);
			if ($id <= 0) {
				continue;
			}
			try {
				$def = $this->mapper->findById($id);
			} catch (DoesNotExistException) {
				continue;
			}
			if ($def->getHouseId() !== $houseId) {
				continue;
			}
			$def->setSortOrder($sortOrder);
			$def->setUpdatedAt(time());
			$this->mapper->update($def);
		}
	}

	public function get(int $fieldId): FieldDefinition {
		try {
			return $this->mapper->findById($fieldId);
		} catch (DoesNotExistException) {
			throw new NotFoundException('Field not found');
		}
	}

	/**
	 * Assert the definition belongs to the house. Returns the loaded entity.
	 *
	 * @throws NotFoundException when missing or from another house.
	 */
	public function assertInHouse(int $fieldId, int $houseId): FieldDefinition {
		$def = $this->get($fieldId);
		if ($def->getHouseId() !== $houseId) {
			throw new NotFoundException('Field does not belong to this house');
		}
		return $def;
	}

	/**
	 * Validate and normalize a set of per-item custom-field values for an item on
	 * the given list. Each entry's field must be a live definition of the house
	 * that applies to the list (house-wide or scoped to this list); the typed
	 * value is coerced to the columns the field's type uses. Entries are keyed by
	 * field id (last wins).
	 *
	 * @param array<array-key, mixed> $values
	 *
	 * @return list<array{fieldId: int, valueText: ?string, valueNumber: ?float, valueBool: bool, valueDate: ?int, valueOptionId: ?int, offsetDays: ?int, notifyOverride: bool, notifyEnabled: bool, notifyLeadDays: ?int}>
	 */
	public function sanitizeValuesForItem(int $houseId, ?int $listId, array $values): array {
		$defsById = [];
		foreach ($this->mapper->findByHouse($houseId) as $def) {
			$defsById[$def->getId()] = $def;
		}

		$byField = [];
		foreach ($values as $entry) {
			if (!is_array($entry)) {
				continue;
			}
			$fieldId = (int)($entry['fieldId'] ?? 0);
			if ($fieldId <= 0) {
				continue;
			}
			$def = $defsById[$fieldId] ?? null;
			if ($def === null) {
				throw new \InvalidArgumentException('Unknown custom field: ' . $fieldId);
			}
			$scopeList = $def->getListId();
			if ($scopeList !== null && $scopeList !== $listId) {
				throw new \InvalidArgumentException('Custom field does not apply to this list: ' . $fieldId);
			}
			$byField[$fieldId] = $this->coerceValue($def, $fieldId, $entry);
		}
		return array_values($byField);
	}

	/**
	 * @param array<array-key, mixed> $entry
	 *
	 * @return array{fieldId: int, valueText: ?string, valueNumber: ?float, valueBool: bool, valueDate: ?int, valueOptionId: ?int, offsetDays: ?int, notifyOverride: bool, notifyEnabled: bool, notifyLeadDays: ?int}
	 */
	private function coerceValue(FieldDefinition $def, int $fieldId, array $entry): array {
		$out = [
			'fieldId' => $fieldId,
			'valueText' => null,
			'valueNumber' => null,
			'valueBool' => false,
			'valueDate' => null,
			'valueOptionId' => null,
			'offsetDays' => null,
			'notifyOverride' => false,
			'notifyEnabled' => false,
			'notifyLeadDays' => null,
		];
		switch ($def->getType()) {
			case FieldDefinition::TYPE_TEXT:
				$out['valueText'] = isset($entry['valueText']) ? (string)$entry['valueText'] : null;
				break;
			case FieldDefinition::TYPE_NUMBER:
				$out['valueNumber'] = $this->floatOrNull($entry['valueNumber'] ?? null);
				break;
			case FieldDefinition::TYPE_CHECKBOX:
				$out['valueBool'] = (bool)($entry['valueBool'] ?? false);
				break;
			case FieldDefinition::TYPE_SELECT:
				$out['valueOptionId'] = $this->intOrNull($entry['valueOptionId'] ?? null);
				break;
			case FieldDefinition::TYPE_DATE:
				$out['valueDate'] = $this->intOrNull($entry['valueDate'] ?? null);
				$out['offsetDays'] = $this->intOrNull($entry['offsetDays'] ?? null);
				$out['notifyOverride'] = (bool)($entry['notifyOverride'] ?? false);
				$out['notifyEnabled'] = (bool)($entry['notifyEnabled'] ?? false);
				$out['notifyLeadDays'] = $this->intOrNull($entry['notifyLeadDays'] ?? null);
				break;
		}
		return $out;
	}

	private function normalizeType(string $type): string {
		if (!in_array($type, FieldDefinition::TYPES, true)) {
			throw new \InvalidArgumentException('Unsupported field type: ' . $type);
		}
		return $type;
	}

	/**
	 * Map the type-relevant keys of `$data` onto the definition. In partial mode
	 * (update) only keys present in `$data` are touched; otherwise absent keys
	 * reset to the type's defaults.
	 *
	 * @param array<string, mixed> $data
	 */
	private function applyConfig(FieldDefinition $def, string $type, array $data, bool $partial = false): void {
		$has = static fn (string $k): bool => array_key_exists($k, $data);
		$set = function (string $key, callable $apply) use ($data, $has, $partial): void {
			if ($has($key)) {
				$apply($data[$key]);
			} elseif (!$partial) {
				$apply(null);
			}
		};

		$set('hint', fn ($v) => $def->setHint($v === null ? null : (string)$v));

		if ($type === FieldDefinition::TYPE_TEXT) {
			$set('multiline', fn ($v) => $def->setMultiline((bool)$v));
			$set('defaultText', fn ($v) => $def->setDefaultText($v === null ? null : (string)$v));
		}
		if ($type === FieldDefinition::TYPE_NUMBER) {
			$set('defaultNumber', fn ($v) => $def->setDefaultNumber($v === null ? null : (float)$v));
		}
		if ($type === FieldDefinition::TYPE_CHECKBOX) {
			$set('defaultBool', fn ($v) => $def->setDefaultBool((bool)$v));
		}
		if ($type === FieldDefinition::TYPE_DATE) {
			$set('dateMode', fn ($v) => $def->setDateMode($this->normalizeDateMode($v)));
			$set('defaultOffsetDays', fn ($v) => $def->setDefaultOffsetDays($v === null ? null : (int)$v));
			$set('notifyDefault', fn ($v) => $def->setNotifyDefault((bool)$v));
			$set('leadDays', fn ($v) => $def->setLeadDays($v === null ? 0 : max(0, (int)$v)));
			$set('overridePolicy', fn ($v) => $def->setOverridePolicy($this->normalizeOverridePolicy($v)));
			$set('stopWhenDone', fn ($v) => $def->setStopWhenDone((bool)$v));
		}
	}

	private function normalizeDateMode(mixed $v): string {
		$mode = ($v === null || $v === '') ? FieldDefinition::DATE_ABSOLUTE : (string)$v;
		if (!in_array($mode, FieldDefinition::DATE_MODES, true)) {
			throw new \InvalidArgumentException('Unsupported date mode: ' . $mode);
		}
		return $mode;
	}

	private function normalizeOverridePolicy(mixed $v): string {
		$policy = ($v === null || $v === '') ? FieldDefinition::OVERRIDE_FIELD_ONLY : (string)$v;
		if (!in_array($policy, FieldDefinition::OVERRIDE_POLICIES, true)) {
			throw new \InvalidArgumentException('Unsupported override policy: ' . $policy);
		}
		return $policy;
	}

	/**
	 * Set the default option to the given id, or null to clear. The id must
	 * belong to this field.
	 *
	 * @param array<string, mixed> $data
	 */
	private function applyDefaultOption(FieldDefinition $def, array $data): void {
		$optionId = $this->intOrNull($data['defaultOptionId'] ?? null);
		if ($optionId === null) {
			$def->setDefaultOptionId(null);
			return;
		}
		$owned = array_map(
			static fn (FieldOption $o): int => $o->getId(),
			$this->optionMapper->findByField($def->getId()),
		);
		if (!in_array($optionId, $owned, true)) {
			throw new \InvalidArgumentException('Default option does not belong to this field');
		}
		$def->setDefaultOptionId($optionId);
	}

	/**
	 * Replace a field's options. In diff mode existing rows are matched by id
	 * (rename/reorder), unmatched incoming rows are inserted, and existing rows
	 * absent from the payload are deleted. Without diff mode all options are
	 * inserted fresh (used at create time).
	 *
	 * @param list<array{id: ?int, label: string, sortOrder: int}> $options
	 */
	private function replaceOptions(int $fieldId, array $options, bool $diff = false): void {
		if (!$diff) {
			foreach ($options as $opt) {
				$this->insertOption($fieldId, $opt['label'], $opt['sortOrder']);
			}
			return;
		}

		$existing = [];
		foreach ($this->optionMapper->findByField($fieldId) as $row) {
			$existing[$row->getId()] = $row;
		}
		$seen = [];
		foreach ($options as $opt) {
			$id = $opt['id'];
			if ($id !== null && isset($existing[$id])) {
				$row = $existing[$id];
				$row->setLabel($opt['label']);
				$row->setSortOrder($opt['sortOrder']);
				$this->optionMapper->update($row);
				$seen[$id] = true;
			} else {
				$this->insertOption($fieldId, $opt['label'], $opt['sortOrder']);
			}
		}
		$dropped = [];
		foreach (array_keys($existing) as $id) {
			if (!isset($seen[$id])) {
				$dropped[] = $id;
			}
		}
		if ($dropped !== []) {
			$counts = $this->valueMapper->countByOptions($dropped);
			foreach ($dropped as $id) {
				if (($counts[$id] ?? 0) > 0) {
					throw new \InvalidArgumentException('Remove an option that has values individually so they can be remapped or cleared');
				}
				$this->optionMapper->delete($existing[$id]);
			}
		}
	}

	private function insertOption(int $fieldId, string $label, int $sortOrder): void {
		$row = new FieldOption();
		$row->setFieldId($fieldId);
		$row->setLabel($label);
		$row->setSortOrder($sortOrder);
		$this->optionMapper->insert($row);
	}

	/**
	 * Coerce the raw options payload into a clean, ordered list. Blank labels are
	 * dropped; a missing sort_order falls back to the entry's position.
	 *
	 * @return list<array{id: ?int, label: string, sortOrder: int}>
	 */
	private function readOptions(mixed $raw): array {
		if (!is_array($raw)) {
			return [];
		}
		$out = [];
		$i = 0;
		foreach ($raw as $entry) {
			if (!is_array($entry)) {
				continue;
			}
			$label = trim((string)($entry['label'] ?? ''));
			if ($label === '') {
				continue;
			}
			$out[] = [
				'id' => $this->intOrNull($entry['id'] ?? null),
				'label' => $label,
				'sortOrder' => isset($entry['sortOrder']) ? (int)$entry['sortOrder'] : $i,
			];
			$i++;
		}
		return $out;
	}

	/**
	 * A null keeps the field house-wide. A set list must belong to the same
	 * house, otherwise a field could leak across houses.
	 */
	private function normalizeListId(int $houseId, ?int $listId): ?int {
		if ($listId === null) {
			return null;
		}
		try {
			$list = $this->listMapper->findById($listId, includeDeleted: true);
		} catch (DoesNotExistException) {
			throw new \InvalidArgumentException('List not found');
		}
		if ($list->getHouseId() !== $houseId) {
			throw new \InvalidArgumentException('List does not belong to this house');
		}
		return $listId;
	}

	private function intOrNull(mixed $v): ?int {
		if ($v === null || $v === '') {
			return null;
		}
		return (int)$v;
	}

	private function floatOrNull(mixed $v): ?float {
		if ($v === null || $v === '') {
			return null;
		}
		return (float)$v;
	}
}
