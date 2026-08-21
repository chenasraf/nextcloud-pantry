<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Db\ChecklistMapper;
use OCA\Pantry\Db\ItemLabelMapper;
use OCA\Pantry\Db\Label;
use OCA\Pantry\Db\LabelMapper;
use OCA\Pantry\Exception\NotFoundException;
use OCP\AppFramework\Db\DoesNotExistException;

class LabelService {
	public function __construct(
		private LabelMapper $mapper,
		private ItemLabelMapper $itemLabelMapper,
		private ChecklistMapper $listMapper,
	) {
	}

	/**
	 * @return Label[]
	 */
	public function listForHouse(int $houseId, string $sortBy = 'name_asc'): array {
		return $this->mapper->findByHouse($houseId, $sortBy);
	}

	/**
	 * Batch reorder labels in a house.
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
				$label = $this->mapper->findById($id);
			} catch (DoesNotExistException) {
				continue;
			}
			if ($label->getHouseId() !== $houseId) {
				continue;
			}
			$label->setSortOrder($sortOrder);
			$label->setUpdatedAt(time());
			$this->mapper->update($label);
		}
	}

	public function get(int $labelId): Label {
		try {
			return $this->mapper->findById($labelId);
		} catch (DoesNotExistException) {
			throw new NotFoundException('Label not found');
		}
	}

	public function create(int $houseId, string $name, string $icon, string $color, ?int $listId = null): Label {
		$name = trim($name);
		if ($name === '') {
			throw new \InvalidArgumentException('Label name cannot be empty');
		}
		$icon = $this->normalizeIcon($icon);
		$color = $this->normalizeColor($color);
		$listId = $this->normalizeListId($houseId, $listId);

		if ($this->mapper->findByHouseListAndName($houseId, $listId, $name) !== null) {
			throw new \InvalidArgumentException('A label with this name already exists');
		}

		$now = time();
		$label = new Label();
		$label->setHouseId($houseId);
		$label->setListId($listId);
		$label->setName($name);
		$label->setIcon($icon);
		$label->setColor($color);
		// Append at the end of the custom order rather than colliding on 0.
		$label->setSortOrder($this->mapper->findMaxSortOrder($houseId) + 1);
		$label->setCreatedAt($now);
		$label->setUpdatedAt($now);
		/** @var Label $saved */
		$saved = $this->mapper->insert($label);
		return $saved;
	}

	public function update(int $labelId, array $patch): Label {
		$label = $this->get($labelId);

		$targetName = $label->getName();
		if (isset($patch['name'])) {
			$targetName = trim((string)$patch['name']);
			if ($targetName === '') {
				throw new \InvalidArgumentException('Label name cannot be empty');
			}
		}
		$originalListId = $label->getListId();
		$targetListId = $label->getListId();
		// A key present with a null value moves the label to the global scope,
		// so key existence (not isset) decides whether the scope is being changed.
		if (array_key_exists('listId', $patch)) {
			$targetListId = $this->normalizeListId(
				$label->getHouseId(),
				$patch['listId'] === null ? null : (int)$patch['listId'],
			);
		}

		if ($targetName !== $label->getName() || $targetListId !== $label->getListId()) {
			$existing = $this->mapper->findByHouseListAndName($label->getHouseId(), $targetListId, $targetName);
			if ($existing !== null && (int)$existing->getId() !== $labelId) {
				throw new \InvalidArgumentException('A label with this name already exists');
			}
		}
		$label->setName($targetName);
		$label->setListId($targetListId);

		if (isset($patch['icon'])) {
			$label->setIcon($this->normalizeIcon((string)$patch['icon']));
		}
		if (isset($patch['color'])) {
			$label->setColor($this->normalizeColor((string)$patch['color']));
		}
		if (isset($patch['sortOrder'])) {
			$label->setSortOrder((int)$patch['sortOrder']);
		}
		$label->setUpdatedAt(time());
		$this->mapper->update($label);

		// Scoping a label to a single list orphans it from items on other lists:
		// those items can no longer carry it, so detach the join rows. (Becoming
		// global is always valid everywhere, so nothing to detach.)
		if ($targetListId !== null && $targetListId !== $originalListId) {
			$this->itemLabelMapper->detachFromItemsNotInList($labelId, $targetListId);
		}
		return $label;
	}

	public function delete(int $labelId): void {
		$label = $this->get($labelId);
		// Detach from any items first, then delete the row.
		$this->itemLabelMapper->deleteByLabel((int)$label->getId());
		$this->mapper->delete($label);
	}

	/**
	 * Asserts that the given label belongs to the given house. Returns the loaded entity.
	 *
	 * @throws NotFoundException when missing or mismatched.
	 */
	public function assertInHouse(int $labelId, int $houseId): Label {
		$label = $this->get($labelId);
		if ($label->getHouseId() !== $houseId) {
			throw new NotFoundException('Label does not belong to this house');
		}
		return $label;
	}

	/**
	 * Assert that every given label id belongs to the house. Returns the unique,
	 * integer-cast set of ids. An empty input is valid (clears labels).
	 *
	 * @param int[] $labelIds
	 * @return int[]
	 *
	 * @throws NotFoundException when any label is missing or from another house.
	 */
	public function assertLabelsInHouse(int $houseId, array $labelIds): array {
		$unique = array_values(array_unique(array_map('intval', $labelIds)));
		foreach ($unique as $labelId) {
			$this->assertInHouse($labelId, $houseId);
		}
		return $unique;
	}

	/**
	 * A null list keeps the label global. A set list must belong to the same
	 * house, otherwise a label could leak across houses.
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

	private function normalizeIcon(string $icon): string {
		$icon = strtolower(trim($icon));
		if (!in_array($icon, ConstantsService::LABEL_ICON_KEYS, true)) {
			throw new \InvalidArgumentException('Unsupported label icon: ' . $icon);
		}
		return $icon;
	}

	private function normalizeColor(string $color): string {
		$color = trim($color);
		if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
			throw new \InvalidArgumentException('Color must be a 6-digit hex string like "#4caf50"');
		}
		return strtolower($color);
	}
}
