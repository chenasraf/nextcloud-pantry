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
use OCA\Pantry\Exception\NotFoundException;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * Custom-field definitions and their `select` options: creation, per-scope
 * uniqueness, type-specific config, soft-delete, and reorder. Values live on
 * items and are handled elsewhere.
 */
class FieldDefinitionService {
	public function __construct(
		private FieldDefinitionMapper $mapper,
		private FieldOptionMapper $optionMapper,
		private ChecklistMapper $listMapper,
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
		return array_map(
			fn (FieldDefinition $d): array => $d->jsonSerialize($optionsByField[(int)$d->getId()] ?? []),
			$defs,
		);
	}

	/**
	 * Serialize a single definition with its options attached.
	 *
	 * @return array<string, mixed>
	 */
	public function serialize(FieldDefinition $def): array {
		$options = array_map(
			static fn (FieldOption $o): array => $o->jsonSerialize(),
			$this->optionMapper->findByField((int)$def->getId()),
		);
		return $def->jsonSerialize($options);
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

		$this->applyConfig($def, $def->getType(), $patch, partial: true);

		if ($def->getType() === FieldDefinition::TYPE_SELECT && array_key_exists('options', $patch)) {
			$this->replaceOptions($fieldId, $this->readOptions($patch['options']), diff: true);
		}
		if (array_key_exists('defaultOptionId', $patch)) {
			$this->applyDefaultOption($def, $patch);
		}

		$def->setUpdatedAt(time());
		$this->mapper->update($def);
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
		foreach ($existing as $id => $row) {
			if (!isset($seen[$id])) {
				$this->optionMapper->delete($row);
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
}
