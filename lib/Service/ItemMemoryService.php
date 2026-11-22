<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Db\Checklist;
use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Db\ChecklistItemMapper;
use OCA\Pantry\Db\ItemCategoryMemoryMapper;
use OCA\Pantry\Db\ItemQuantityMemoryMapper;

/**
 * Remembers the category and amount a household picks for each item, so
 * clients can suggest them when the item is typed in again.
 *
 * Only decisions people make teach it: the item endpoints call in after a
 * create or edit. Writes the app makes on its own (restocking, template and
 * list copies, moves) never reach it, so they cannot drown out what people
 * actually chose.
 *
 * Clients pick a suggestion themselves from the synced memory: among the
 * categories usable on the target list, the one with the highest
 * uses - rejections above zero, the most recently changed on a tie; the amount
 * is the list's remembered quantity for the name, when there is one.
 */
class ItemMemoryService {
	public function __construct(
		private ItemCategoryMemoryMapper $categories,
		private ItemQuantityMemoryMapper $quantities,
		private ChecklistItemMapper $items,
	) {
	}

	/**
	 * @param array{categoryId?: int|null, quantity?: bool}|null $rejected Suggestions the person dropped before saving.
	 */
	public function learnCreated(int $houseId, ChecklistItem $item, ?string $quantity, ?array $rejected = null, ?int $now = null): void {
		$key = NameKey::of($item->getName());
		if ($key === '') {
			return;
		}
		$now ??= time();
		$categoryId = $item->getCategoryId();
		if ($categoryId !== null) {
			$this->categories->bump($houseId, $key, $categoryId, 1, 0, $now);
		}
		$this->learnRejected($houseId, $item->getListId(), $key, $categoryId, $rejected, $now);
		$quantity = self::nonEmpty($quantity);
		if ($quantity !== null && !($rejected['quantity'] ?? false)) {
			$this->quantities->put($houseId, $item->getListId(), $key, $quantity, $now);
		}
	}

	/**
	 * @param bool $quantitySent Whether the edit set the quantity.
	 * @param bool $quantityIsAmount Whether the item's quantity is an amount to
	 *                               remember. An inventory's is how much is on
	 *                               hand, which changes with every use and says
	 *                               nothing about how much people add.
	 * @param array{categoryId?: int|null, quantity?: bool}|null $rejected Suggestions the person dropped before saving.
	 */
	public function learnUpdated(
		int $houseId,
		ChecklistItem $before,
		ChecklistItem $after,
		bool $quantitySent,
		bool $quantityIsAmount,
		?array $rejected = null,
		?int $now = null,
	): void {
		$key = NameKey::of($after->getName());
		if ($key === '') {
			return;
		}
		$now ??= time();
		$this->learnCategoryChange($houseId, $before, $after, $now);
		$this->learnRejected($houseId, $after->getListId(), $key, $after->getCategoryId(), $rejected, $now);
		$quantity = self::nonEmpty($after->getQuantity());
		// A cleared quantity is not forgotten: people clear it after shopping.
		if ($quantitySent && $quantityIsAmount && $quantity !== null && !($rejected['quantity'] ?? false)) {
			$this->quantities->put($houseId, $after->getListId(), $key, $quantity, $now);
		}
	}

	/**
	 * A category changed from one to another counts for the new one and against
	 * the old one. A renamed item also counts its category for the new name.
	 */
	public function learnCategoryChange(int $houseId, ChecklistItem $before, ChecklistItem $after, ?int $now = null): void {
		$key = NameKey::of($after->getName());
		if ($key === '') {
			return;
		}
		$now ??= time();
		$from = $before->getCategoryId();
		$to = $after->getCategoryId();
		$renamed = NameKey::of($before->getName()) !== $key;
		if ($to !== null && ($to !== $from || $renamed)) {
			$this->categories->bump($houseId, $key, $to, 1, 0, $now);
		}
		if ($from !== null && $from !== $to) {
			$this->categories->bump($houseId, NameKey::of($before->getName()) ?: $key, $from, 0, 1, $now);
		}
	}

	/**
	 * @param \Closure(int): bool $canAccessList Whether the caller may see a list's remembered amounts.
	 * @return array{categories: list<array{nameKey: string, categoryId: int, uses: int, rejections: int, updatedAt: int}>, quantities: list<array{listId: int, nameKey: string, quantity: string|null, updatedAt: int}>, serverTime: int}
	 */
	public function snapshot(int $houseId, ?int $since, \Closure $canAccessList, ?int $now = null): array {
		$now ??= time();
		$quantities = [];
		foreach ($this->quantities->findForHouse($houseId, $since) as $row) {
			// A full dump has nothing to forget, so it leaves out forgotten amounts.
			if (($since === null && $row->getQuantity() === null) || !$canAccessList($row->getListId())) {
				continue;
			}
			$quantities[] = $row->jsonSerialize();
		}
		return [
			'categories' => array_map(
				static fn ($row) => $row->jsonSerialize(),
				$this->categories->findForHouse($houseId, $since),
			),
			'quantities' => $quantities,
			'serverTime' => $now,
		];
	}

	/**
	 * @param list<int> $categoryIds
	 */
	public function forgetCategories(array $categoryIds): void {
		if ($categoryIds !== []) {
			$this->categories->deleteByCategories($categoryIds);
		}
	}

	/**
	 * @param list<int> $scopedCategoryIds The categories that belonged to the list alone.
	 */
	public function forgetList(int $listId, array $scopedCategoryIds): void {
		$this->quantities->deleteByList($listId);
		$this->forgetCategories($scopedCategoryIds);
	}

	public function forgetHouse(int $houseId): void {
		$this->categories->deleteByHouse($houseId);
		$this->quantities->deleteByHouse($houseId);
	}

	/**
	 * Build a house's memory from the items it already has, unless it has
	 * memory of its own already.
	 *
	 * @return bool Whether anything was seeded.
	 */
	public function seedIfEmpty(int $houseId, ?int $now = null): bool {
		if ($this->categories->hasAnyForHouse($houseId) || $this->quantities->hasAnyForHouse($houseId)) {
			return false;
		}
		$now ??= time();
		$seed = self::seedFrom($this->items->findMemorySeedRows($houseId));
		foreach ($seed['categories'] as $row) {
			$this->categories->bump($houseId, $row['nameKey'], $row['categoryId'], $row['uses'], 0, $now);
		}
		foreach ($seed['quantities'] as $row) {
			$this->quantities->put($houseId, $row['listId'], $row['nameKey'], $row['quantity'], $now);
		}
		return $seed['categories'] !== [] || $seed['quantities'] !== [];
	}

	/**
	 * Every item counts one use of its category for its name. Each list
	 * remembers the amount on its most recently changed item of that name;
	 * inventories are skipped, since their amounts are what is on hand.
	 *
	 * @param list<array{name: string, categoryId: int|null, listId: int, listKind: string, quantity: string|null}> $rows Oldest change first.
	 * @return array{categories: list<array{nameKey: string, categoryId: int, uses: int}>, quantities: list<array{listId: int, nameKey: string, quantity: string}>}
	 */
	public static function seedFrom(array $rows): array {
		// Keyed by composite strings: a bare name key like "7" would turn into an int.
		$categories = [];
		$quantities = [];
		foreach ($rows as $row) {
			$key = NameKey::of($row['name']);
			if ($key === '') {
				continue;
			}
			if ($row['categoryId'] !== null) {
				$slot = $row['categoryId'] . "\0" . $key;
				$categories[$slot] ??= ['nameKey' => $key, 'categoryId' => $row['categoryId'], 'uses' => 0];
				$categories[$slot]['uses']++;
			}
			$quantity = self::nonEmpty($row['quantity']);
			if ($quantity !== null && $row['listKind'] !== Checklist::KIND_INVENTORY) {
				$quantities[$row['listId'] . "\0" . $key] = ['listId' => $row['listId'], 'nameKey' => $key, 'quantity' => $quantity];
			}
		}
		return ['categories' => array_values($categories), 'quantities' => array_values($quantities)];
	}

	/**
	 * @param array{categoryId?: int|null, quantity?: bool}|null $rejected
	 */
	private function learnRejected(int $houseId, int $listId, string $key, ?int $savedCategoryId, ?array $rejected, int $now): void {
		if ($rejected === null) {
			return;
		}
		$categoryId = $rejected['categoryId'] ?? null;
		// A guess dropped and then picked by hand anyway was not turned down.
		if ($categoryId !== null && $categoryId !== $savedCategoryId) {
			$this->categories->bump($houseId, $key, $categoryId, 0, 1, $now);
		}
		if ($rejected['quantity'] ?? false) {
			$this->quantities->put($houseId, $listId, $key, null, $now);
		}
	}

	private static function nonEmpty(?string $value): ?string {
		$value = $value !== null ? trim($value) : null;
		return $value === '' ? null : $value;
	}
}
