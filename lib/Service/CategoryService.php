<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Db\Category;
use OCA\Pantry\Db\CategoryMapper;
use OCA\Pantry\Db\ChecklistMapper;
use OCA\Pantry\Exception\NotFoundException;
use OCP\AppFramework\Db\DoesNotExistException;

class CategoryService {
	public function __construct(
		private CategoryMapper $mapper,
		private ChecklistMapper $listMapper,
	) {
	}

	/**
	 * @return Category[]
	 */
	public function listForHouse(int $houseId, string $sortBy = 'name_asc'): array {
		return $this->mapper->findByHouse($houseId, $sortBy);
	}

	/**
	 * Batch reorder categories in a house.
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
				$cat = $this->mapper->findById($id);
			} catch (DoesNotExistException) {
				continue;
			}
			if ($cat->getHouseId() !== $houseId) {
				continue;
			}
			$cat->setSortOrder($sortOrder);
			$cat->setUpdatedAt(time());
			$this->mapper->update($cat);
		}
	}

	public function get(int $categoryId): Category {
		try {
			return $this->mapper->findById($categoryId);
		} catch (DoesNotExistException) {
			throw new NotFoundException('Category not found');
		}
	}

	public function create(int $houseId, string $name, string $icon, string $color, ?int $listId = null): Category {
		$name = trim($name);
		if ($name === '') {
			throw new \InvalidArgumentException('Category name cannot be empty');
		}
		$icon = $this->normalizeIcon($icon);
		$color = $this->normalizeColor($color);
		$listId = $this->normalizeListId($houseId, $listId);

		if ($this->mapper->findByHouseListAndName($houseId, $listId, $name) !== null) {
			throw new \InvalidArgumentException('A category with this name already exists');
		}

		$now = time();
		$cat = new Category();
		$cat->setHouseId($houseId);
		$cat->setListId($listId);
		$cat->setName($name);
		$cat->setIcon($icon);
		$cat->setColor($color);
		$position = $this->appendPositionInScope($houseId, $listId);
		$this->mapper->shiftSortOrderFrom($houseId, $position);
		$cat->setSortOrder($position);
		$cat->setCreatedAt($now);
		$cat->setUpdatedAt($now);
		/** @var Category $saved */
		$saved = $this->mapper->insert($cat);
		return $saved;
	}

	public function update(int $categoryId, array $patch): Category {
		$cat = $this->get($categoryId);

		$targetName = $cat->getName();
		if (isset($patch['name'])) {
			$targetName = trim((string)$patch['name']);
			if ($targetName === '') {
				throw new \InvalidArgumentException('Category name cannot be empty');
			}
		}
		$originalListId = $cat->getListId();
		$targetListId = $cat->getListId();
		// A key present with a null value moves the category to the global scope,
		// so key existence (not isset) decides whether the scope is being changed.
		if (array_key_exists('listId', $patch)) {
			$targetListId = $this->normalizeListId(
				$cat->getHouseId(),
				$patch['listId'] === null ? null : (int)$patch['listId'],
			);
		}

		if ($targetName !== $cat->getName() || $targetListId !== $cat->getListId()) {
			$existing = $this->mapper->findByHouseListAndName($cat->getHouseId(), $targetListId, $targetName);
			if ($existing !== null && (int)$existing->getId() !== $categoryId) {
				throw new \InvalidArgumentException('A category with this name already exists');
			}
		}
		$cat->setName($targetName);
		$cat->setListId($targetListId);

		if (isset($patch['icon'])) {
			$cat->setIcon($this->normalizeIcon((string)$patch['icon']));
		}
		if (isset($patch['color'])) {
			$cat->setColor($this->normalizeColor((string)$patch['color']));
		}
		if (isset($patch['sortOrder'])) {
			$cat->setSortOrder((int)$patch['sortOrder']);
		} elseif ($targetListId !== $originalListId) {
			// The old position sits inside the run the category is leaving, so
			// carrying it over would drop the category into the middle of a
			// group it no longer belongs to.
			$position = $this->appendPositionInScope($cat->getHouseId(), $targetListId);
			$this->mapper->shiftSortOrderFrom($cat->getHouseId(), $position);
			$cat->setSortOrder($position);
		}
		$cat->setUpdatedAt(time());
		$this->mapper->update($cat);

		// Scoping a category to a single list orphans it from items on other
		// lists: those items can no longer carry it, so clear their category.
		// (Becoming global is always valid everywhere, so nothing to detach.)
		if ($targetListId !== null && $targetListId !== $originalListId) {
			$this->mapper->detachFromItemsNotInList($categoryId, $targetListId);
		}
		return $cat;
	}

	public function delete(int $categoryId): void {
		$cat = $this->get($categoryId);
		// Detach from any items first, then delete the row.
		$this->mapper->detachFromItems((int)$cat->getId());
		$this->mapper->delete($cat);
	}

	/**
	 * Asserts that the given category belongs to the given house. Returns the loaded entity.
	 *
	 * @throws NotFoundException when missing or mismatched.
	 */
	public function assertInHouse(int $categoryId, int $houseId): Category {
		$cat = $this->get($categoryId);
		if ($cat->getHouseId() !== $houseId) {
			throw new NotFoundException('Category does not belong to this house');
		}
		return $cat;
	}

	/**
	 * The position just past the last category of a scope, which is where the
	 * category manager shows a newcomer: sort_order is one house-wide sequence,
	 * but it runs group by group, so appending to the house instead would land
	 * the category past every other group.
	 *
	 * An empty scope has no run to append to. Global categories lead the
	 * sequence, so the first of them opens it; a list's first category starts a
	 * run of its own at the end.
	 */
	private function appendPositionInScope(int $houseId, ?int $listId): int {
		$maxInScope = $this->mapper->findMaxSortOrderInScope($houseId, $listId);
		if ($maxInScope >= 0) {
			return $maxInScope + 1;
		}
		return $listId === null ? 0 : $this->mapper->findMaxSortOrder($houseId) + 1;
	}

	/**
	 * A null list keeps the category global. A set list must belong to the same
	 * house, otherwise a category could leak across houses.
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
		if (!in_array($icon, ConstantsService::CATEGORY_ICON_KEYS, true)) {
			throw new \InvalidArgumentException('Unsupported category icon: ' . $icon);
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
