<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Db\ShoppingList;
use OCA\Pantry\Db\ShoppingListItem;
use OCA\Pantry\Db\ShoppingListItemMapper;
use OCA\Pantry\Db\ShoppingListMapper;
use OCA\Pantry\Exception\NotFoundException;
use OCP\AppFramework\Db\DoesNotExistException;

class ShoppingListService {
	public function __construct(
		private ShoppingListMapper $listMapper,
		private ShoppingListItemMapper $itemMapper,
		private RecurrenceService $recurrence,
	) {
	}

	// ----- Lists -----

	/**
	 * @return ShoppingList[]
	 */
	public function listForHouse(int $houseId): array {
		return $this->listMapper->findByHouse($houseId);
	}

	public function getList(int $listId): ShoppingList {
		try {
			return $this->listMapper->findById($listId);
		} catch (DoesNotExistException) {
			throw new NotFoundException('List not found');
		}
	}

	public function createList(int $houseId, string $name, ?string $description): ShoppingList {
		$name = trim($name);
		if ($name === '') {
			throw new \InvalidArgumentException('List name cannot be empty');
		}
		$now = time();
		$list = new ShoppingList();
		$list->setHouseId($houseId);
		$list->setName($name);
		$list->setDescription($description !== null && $description !== '' ? $description : null);
		$list->setSortOrder(0);
		$list->setCreatedAt($now);
		$list->setUpdatedAt($now);
		/** @var ShoppingList $saved */
		$saved = $this->listMapper->insert($list);
		return $saved;
	}

	public function updateList(int $listId, array $patch): ShoppingList {
		$list = $this->getList($listId);
		if (isset($patch['name'])) {
			$name = trim((string)$patch['name']);
			if ($name === '') {
				throw new \InvalidArgumentException('List name cannot be empty');
			}
			$list->setName($name);
		}
		if (array_key_exists('description', $patch)) {
			$desc = $patch['description'];
			$list->setDescription(is_string($desc) && $desc !== '' ? $desc : null);
		}
		if (isset($patch['sortOrder'])) {
			$list->setSortOrder((int)$patch['sortOrder']);
		}
		$list->setUpdatedAt(time());
		$this->listMapper->update($list);
		return $list;
	}

	public function deleteList(int $listId): void {
		$list = $this->getList($listId);
		$this->itemMapper->deleteByList((int)$list->getId());
		$this->listMapper->delete($list);
	}

	// ----- Items -----

	/**
	 * List items for a list, auto-unchecking any recurring items whose next_due_at has passed.
	 *
	 * @return ShoppingListItem[]
	 */
	public function listItems(int $listId, ?int $now = null): array {
		$now ??= time();
		$items = $this->itemMapper->findByList($listId);
		$refreshed = [];
		foreach ($items as $item) {
			if ($item->getBought() && $item->getNextDueAt() !== null && $item->getNextDueAt() <= $now) {
				$item->setBought(false);
				$item->setBoughtAt(null);
				$item->setBoughtBy(null);
				$item->setNextDueAt(null);
				$item->setUpdatedAt($now);
				$this->itemMapper->update($item);
			}
			$refreshed[] = $item;
		}
		return $refreshed;
	}

	public function getItem(int $itemId): ShoppingListItem {
		try {
			return $this->itemMapper->findById($itemId);
		} catch (DoesNotExistException) {
			throw new NotFoundException('Item not found');
		}
	}

	public function addItem(int $listId, array $data): ShoppingListItem {
		// Ensure the list exists.
		$this->getList($listId);

		$name = trim((string)($data['name'] ?? ''));
		if ($name === '') {
			throw new \InvalidArgumentException('Item name cannot be empty');
		}

		$rrule = isset($data['rrule']) && is_string($data['rrule']) && trim($data['rrule']) !== ''
			? trim($data['rrule'])
			: null;
		if ($rrule !== null) {
			$this->recurrence->validate($rrule);
		}

		$now = time();
		$item = new ShoppingListItem();
		$item->setListId($listId);
		$item->setName($name);
		$item->setCategoryId($this->intOrNull($data['categoryId'] ?? null));
		$item->setQuantity($this->strOrNull($data['quantity'] ?? null));
		$item->setBought(false);
		$item->setBoughtAt(null);
		$item->setBoughtBy(null);
		$item->setRrule($rrule);
		$item->setRepeatFromCompletion(!empty($data['repeatFromCompletion']));
		$item->setNextDueAt(null);
		$item->setSortOrder(isset($data['sortOrder']) ? (int)$data['sortOrder'] : 0);
		$item->setCreatedAt($now);
		$item->setUpdatedAt($now);
		/** @var ShoppingListItem $saved */
		$saved = $this->itemMapper->insert($item);
		return $saved;
	}

	public function updateItem(int $itemId, array $patch): ShoppingListItem {
		$item = $this->getItem($itemId);

		if (isset($patch['name'])) {
			$name = trim((string)$patch['name']);
			if ($name === '') {
				throw new \InvalidArgumentException('Item name cannot be empty');
			}
			$item->setName($name);
		}
		if (array_key_exists('categoryId', $patch)) {
			$item->setCategoryId($this->intOrNull($patch['categoryId']));
		}
		if (array_key_exists('quantity', $patch)) {
			$item->setQuantity($this->strOrNull($patch['quantity']));
		}
		if (array_key_exists('rrule', $patch)) {
			$rrule = $patch['rrule'];
			if ($rrule === null || (is_string($rrule) && trim($rrule) === '')) {
				$item->setRrule(null);
				// Clearing recurrence also clears any scheduled re-open.
				if ($item->getBought()) {
					$item->setNextDueAt(null);
				}
			} else {
				$rrule = trim((string)$rrule);
				$this->recurrence->validate($rrule);
				$item->setRrule($rrule);
			}
		}
		if (array_key_exists('repeatFromCompletion', $patch)) {
			$item->setRepeatFromCompletion((bool)$patch['repeatFromCompletion']);
		}
		// If already bought and rrule or mode changed, recompute next due.
		if ($item->getBought() && $item->getRrule() !== null
			&& (array_key_exists('rrule', $patch) || array_key_exists('repeatFromCompletion', $patch))) {
			$item->setNextDueAt($this->computeNextDueAt($item, time())?->getTimestamp());
		}
		if (isset($patch['sortOrder'])) {
			$item->setSortOrder((int)$patch['sortOrder']);
		}

		$item->setUpdatedAt(time());
		$this->itemMapper->update($item);
		return $item;
	}

	public function toggleItem(int $itemId, string $uid, ?int $now = null): ShoppingListItem {
		$item = $this->getItem($itemId);
		$now ??= time();

		if (!$item->getBought()) {
			$item->setBought(true);
			$item->setBoughtAt($now);
			$item->setBoughtBy($uid);
			if ($item->getRrule() !== null) {
				$item->setNextDueAt($this->computeNextDueAt($item, $now)?->getTimestamp());
			}
		} else {
			$item->setBought(false);
			$item->setBoughtAt(null);
			$item->setBoughtBy(null);
			$item->setNextDueAt(null);
		}
		$item->setUpdatedAt($now);
		$this->itemMapper->update($item);
		return $item;
	}

	/**
	 * Compute the next due time for an item that was just marked bought.
	 *
	 * - "from completion" mode: interval counts from now — next occurrence = now + one step.
	 * - "fixed schedule" mode: the schedule is anchored at the item's creation time; next
	 *   occurrence is the first one strictly after now on that anchored series.
	 */
	private function computeNextDueAt(ShoppingListItem $item, int $now): ?\DateTimeImmutable {
		$rrule = $item->getRrule();
		if ($rrule === null) {
			return null;
		}
		$nowDt = (new \DateTimeImmutable())->setTimestamp($now);
		if ($item->getRepeatFromCompletion()) {
			return $this->recurrence->computeNextOccurrence($rrule, $nowDt);
		}
		$anchor = (new \DateTimeImmutable())->setTimestamp($item->getCreatedAt() ?: $now);
		return $this->recurrence->nextOccurrenceAfter($rrule, $anchor, $nowDt);
	}

	public function deleteItem(int $itemId): void {
		$item = $this->getItem($itemId);
		$this->itemMapper->delete($item);
	}

	private function strOrNull(mixed $v): ?string {
		if (!is_string($v)) {
			return null;
		}
		$t = trim($v);
		return $t === '' ? null : $t;
	}

	private function intOrNull(mixed $v): ?int {
		if ($v === null || $v === '' || $v === false) {
			return null;
		}
		if (is_int($v)) {
			return $v;
		}
		if (is_string($v) && ctype_digit($v)) {
			return (int)$v;
		}
		if (is_numeric($v)) {
			return (int)$v;
		}
		return null;
	}
}
