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
		$item->setCategory($this->strOrNull($data['category'] ?? null));
		$item->setQuantity($this->strOrNull($data['quantity'] ?? null));
		$item->setBought(false);
		$item->setBoughtAt(null);
		$item->setBoughtBy(null);
		$item->setRrule($rrule);
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
		if (array_key_exists('category', $patch)) {
			$item->setCategory($this->strOrNull($patch['category']));
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
				// If already bought, recompute next due from now.
				if ($item->getBought()) {
					$next = $this->recurrence->computeNextOccurrence($rrule, new \DateTimeImmutable('@' . time()));
					$item->setNextDueAt($next?->getTimestamp());
				}
			}
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
				$next = $this->recurrence->computeNextOccurrence(
					$item->getRrule(),
					(new \DateTimeImmutable())->setTimestamp($now),
				);
				$item->setNextDueAt($next?->getTimestamp());
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
}
