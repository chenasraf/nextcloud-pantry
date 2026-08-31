<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Db\CategoryMapper;
use OCA\Pantry\Db\Checklist;
use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Db\ChecklistItemMapper;
use OCA\Pantry\Db\ChecklistMapper;
use OCA\Pantry\Db\House;
use OCA\Pantry\Db\HouseMapper;
use OCA\Pantry\Db\ItemLabelMapper;
use OCA\Pantry\Db\ItemPriceMapper;
use OCA\Pantry\Db\ItemStoreMapper;
use OCA\Pantry\Db\LabelMapper;
use OCA\Pantry\Exception\NotFoundException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\TTransactional;
use OCP\IDBConnection;

class ChecklistService {
	use TTransactional;

	/**
	 * Per-list house lookups, memoized for the lifetime of the service so the
	 * recurring-item background job doesn't refetch the house for every item.
	 *
	 * @var array<int, House|null>
	 */
	private array $houseByList = [];

	public function __construct(
		private ChecklistMapper $listMapper,
		private ChecklistItemMapper $itemMapper,
		private RecurrenceService $recurrence,
		private \OCA\Pantry\Db\ListRoleMapper $listRoleMapper,
		private ItemStoreMapper $itemStoreMapper,
		private ItemPriceMapper $itemPriceMapper,
		private HouseMapper $houseMapper,
		private CategoryMapper $categoryMapper,
		private ItemLabelMapper $itemLabelMapper,
		private LabelMapper $labelMapper,
		private \OCA\Pantry\Db\FieldDefinitionMapper $fieldDefMapper,
		private \OCA\Pantry\Db\FieldOptionMapper $fieldOptionMapper,
		private IDBConnection $db,
	) {
	}

	// ----- Lists -----

	/**
	 * @return Checklist[]
	 */
	public function listForHouse(int $houseId, string $sortBy = 'custom'): array {
		return $this->listMapper->findByHouse($houseId, $sortBy);
	}

	/**
	 * Batch reorder lists within a house.
	 *
	 * @param int $houseId House id.
	 * @param array<array{id: int, sortOrder: int}> $items Reorder entries.
	 */
	public function reorderLists(int $houseId, array $items): void {
		foreach ($items as $entry) {
			$id = (int)($entry['id'] ?? 0);
			$sortOrder = (int)($entry['sortOrder'] ?? 0);
			if ($id <= 0) {
				continue;
			}
			try {
				$list = $this->listMapper->findById($id);
			} catch (DoesNotExistException) {
				continue;
			}
			if ($list->getHouseId() !== $houseId) {
				continue;
			}
			$list->setSortOrder($sortOrder);
			$list->setUpdatedAt(time());
			$this->listMapper->update($list);
		}
	}

	/**
	 * List soft-deleted checklists in a house. Most recently deleted first.
	 *
	 * @return Checklist[]
	 */
	public function listDeletedForHouse(int $houseId): array {
		return $this->listMapper->findDeletedByHouse($houseId);
	}

	public function getList(int $listId, bool $includeDeleted = false): Checklist {
		try {
			return $this->listMapper->findById($listId, $includeDeleted);
		} catch (DoesNotExistException) {
			throw new NotFoundException('List not found');
		}
	}

	public function createList(int $houseId, string $name, ?string $description, ?string $icon = null, ?string $color = null): Checklist {
		$name = trim($name);
		if ($name === '') {
			throw new \InvalidArgumentException('List name cannot be empty');
		}
		$now = time();
		$list = new Checklist();
		$list->setHouseId($houseId);
		$list->setName($name);
		$list->setDescription($description !== null && $description !== '' ? $description : null);
		if ($icon !== null && $icon !== '') {
			$list->setIcon($icon);
		}
		$list->setColor($color !== null && $color !== '' ? $color : null);
		$list->setSortOrder(0);
		$list->setCreatedAt($now);
		$list->setUpdatedAt($now);
		/** @var Checklist $saved */
		$saved = $this->listMapper->insert($list);
		return $saved;
	}

	public function updateList(int $listId, array $patch): Checklist {
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
		if (isset($patch['icon'])) {
			$icon = trim((string)$patch['icon']);
			if ($icon !== '') {
				$list->setIcon($icon);
			}
		}
		if (array_key_exists('color', $patch)) {
			$color = $patch['color'];
			$list->setColor(is_string($color) && $color !== '' ? $color : null);
		}
		if (isset($patch['sortOrder'])) {
			$list->setSortOrder((int)$patch['sortOrder']);
		}
		if (array_key_exists('deleteOnDoneDefault', $patch)) {
			$list->setDeleteOnDoneDefault((bool)$patch['deleteOnDoneDefault']);
		}
		$list->setUpdatedAt(time());
		$this->listMapper->update($list);
		return $list;
	}

	/**
	 * Soft-delete a checklist. Items remain on the list (their own deleted_at
	 * state is untouched) so the list can be restored intact.
	 */
	public function deleteList(int $listId): void {
		$list = $this->getList($listId);
		$now = time();
		$list->setDeletedAt($now);
		$list->setUpdatedAt($now);
		$this->listMapper->update($list);
	}

	/**
	 * Restore a soft-deleted checklist by clearing its deleted_at marker.
	 */
	public function restoreList(int $listId): Checklist {
		$list = $this->getList($listId, includeDeleted: true);
		$list->setDeletedAt(null);
		$list->setUpdatedAt(time());
		$this->listMapper->update($list);
		return $list;
	}

	/**
	 * Permanently remove a checklist and every item that belongs to it,
	 * regardless of whether it is currently in trash.
	 */
	public function permanentlyDeleteList(int $listId): void {
		$list = $this->getList($listId, includeDeleted: true);
		$this->itemMapper->deleteByList((int)$list->getId());
		$this->listRoleMapper->deleteByList((int)$list->getId());
		$this->categoryMapper->deleteByList((int)$list->getId());
		$this->labelMapper->deleteByList((int)$list->getId());
		$this->fieldOptionMapper->deleteByList((int)$list->getId());
		$this->fieldDefMapper->deleteByList((int)$list->getId());
		$this->listMapper->delete($list);
	}

	/**
	 * Hard-delete every soft-deleted checklist in the house, along with any
	 * items those lists contained.
	 */
	public function emptyListsTrash(int $houseId): void {
		$removed = $this->listMapper->emptyTrashForHouse($houseId);
		foreach ($removed as $list) {
			$this->itemMapper->deleteByList((int)$list->getId());
			$this->categoryMapper->deleteByList((int)$list->getId());
			$this->labelMapper->deleteByList((int)$list->getId());
			$this->fieldOptionMapper->deleteByList((int)$list->getId());
			$this->fieldDefMapper->deleteByList((int)$list->getId());
		}
	}

	/**
	 * List archived checklists in a house. Most recently archived first.
	 *
	 * @return Checklist[]
	 */
	public function listArchivedForHouse(int $houseId): array {
		return $this->listMapper->findArchivedByHouse($houseId);
	}

	/**
	 * Archive a checklist by stamping its archived_at marker. Archived lists are
	 * hidden from the active index but, unlike trash, are never auto-purged and
	 * cannot be emptied in bulk.
	 */
	public function archiveList(int $listId): Checklist {
		$list = $this->getList($listId);
		$now = time();
		$list->setArchivedAt($now);
		$list->setUpdatedAt($now);
		$this->listMapper->update($list);
		return $list;
	}

	/**
	 * Unarchive a checklist by clearing its archived_at marker.
	 */
	public function unarchiveList(int $listId): Checklist {
		$list = $this->getList($listId, includeDeleted: true);
		$list->setArchivedAt(null);
		$list->setUpdatedAt(time());
		$this->listMapper->update($list);
		return $list;
	}

	// ----- Items -----

	/**
	 * List items for a list, auto-unchecking any recurring items whose next_due_at has passed.
	 *
	 * @return ChecklistItem[]
	 */
	public function listItems(int $listId, string $sortBy = 'custom', string $categorySort = 'name_asc', ?int $now = null): array {
		// Eagerly reopen any due recurring items in this list before returning.
		$this->reopenDueItems($now);
		return $this->itemMapper->findByList($listId, $sortBy, $categorySort);
	}

	/**
	 * List non-deleted items from every non-deleted list in the house. Used by
	 * the meta "All lists" view — sort_order is per-list, so "custom" is not
	 * a supported sort here.
	 *
	 * @return ChecklistItem[]
	 */
	public function listItemsForHouse(int $houseId, string $sortBy = 'newest', string $categorySort = 'name_asc', ?int $now = null): array {
		$this->reopenDueItems($now);
		return $this->itemMapper->findByHouse($houseId, $sortBy, $categorySort);
	}

	/**
	 * List soft-deleted items for a list. Most recently deleted first.
	 *
	 * @return ChecklistItem[]
	 */
	public function listDeletedItems(int $listId): array {
		return $this->itemMapper->findDeletedByList($listId);
	}

	public function getItem(int $itemId, bool $includeDeleted = false): ChecklistItem {
		try {
			return $this->itemMapper->findById($itemId, $includeDeleted);
		} catch (DoesNotExistException) {
			throw new NotFoundException('Item not found');
		}
	}

	public function addItem(int $listId, array $data, ?string $addedBy = null): ChecklistItem {
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
		$item = new ChecklistItem();
		$item->setListId($listId);
		$item->setName($name);
		$item->setDescription($this->strOrNull($data['description'] ?? null));
		$item->setCategoryId($this->intOrNull($data['categoryId'] ?? null));
		$item->setQuantity($this->strOrNull($data['quantity'] ?? null));
		$item->setDone(false);
		$item->setDoneAt(null);
		$item->setDoneBy(null);
		$item->setRrule($rrule);
		$repeatFromCompletion = !empty($data['repeatFromCompletion']);
		$item->setRepeatFromCompletion($repeatFromCompletion);
		$item->setDeleteOnDone(!empty($data['deleteOnDone']));
		// For fixed-schedule items, compute the first due time immediately.
		if ($rrule !== null && !$repeatFromCompletion) {
			$item->setNextDueAt($this->computeNextDueAt($item, $now)?->getTimestamp());
		} else {
			$item->setNextDueAt(null);
		}
		$item->setImageFileId($this->intOrNull($data['imageFileId'] ?? null));
		$item->setAddedBy($addedBy);
		$item->setBarcode($this->strOrNull($data['barcode'] ?? null));
		if (isset($data['sortOrder'])) {
			$item->setSortOrder((int)$data['sortOrder']);
		} else {
			// Append at the bottom of the list's custom order rather than colliding
			// on 0, so a new item lands predictably last even after a manual reorder.
			$max = $this->itemMapper->maxSortOrder($listId);
			$item->setSortOrder($max === null ? 0 : $max + 1);
		}
		$item->setCreatedAt($now);
		$item->setUpdatedAt($now);
		/** @var ChecklistItem $saved */
		$saved = $this->itemMapper->insert($item);
		if (array_key_exists('storeIds', $data)) {
			$this->itemStoreMapper->setStoresForItem((int)$saved->getId(), (array)$data['storeIds']);
		}
		if (array_key_exists('labelIds', $data)) {
			$this->itemLabelMapper->setLabelsForItem((int)$saved->getId(), (array)$data['labelIds']);
		}
		if (array_key_exists('prices', $data)) {
			$this->itemPriceMapper->setPricesForItem((int)$saved->getId(), $this->normalizePrices($data['prices']));
		}
		return $saved;
	}

	public function updateItem(int $itemId, array $patch): ChecklistItem {
		$item = $this->getItem($itemId);

		if (isset($patch['name'])) {
			$name = trim((string)$patch['name']);
			if ($name === '') {
				throw new \InvalidArgumentException('Item name cannot be empty');
			}
			$item->setName($name);
		}
		if (array_key_exists('description', $patch)) {
			$item->setDescription($this->strOrNull($patch['description']));
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
				if ($item->getDone()) {
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
		if (array_key_exists('deleteOnDone', $patch)) {
			$item->setDeleteOnDone((bool)$patch['deleteOnDone']);
		}
		if (array_key_exists('imageFileId', $patch)) {
			$item->setImageFileId($this->intOrNull($patch['imageFileId']));
		}
		if (array_key_exists('barcode', $patch)) {
			$item->setBarcode($this->strOrNull($patch['barcode']));
		}
		if (array_key_exists('imageUploadedBy', $patch)) {
			$v = $patch['imageUploadedBy'];
			$item->setImageUploadedBy(is_string($v) && $v !== '' ? $v : null);
		}
		// If already done and rrule or mode changed, recompute next due.
		if ($item->getDone() && $item->getRrule() !== null
			&& (array_key_exists('rrule', $patch) || array_key_exists('repeatFromCompletion', $patch))) {
			$item->setNextDueAt($this->computeNextDueAt($item, time())?->getTimestamp());
		}
		if (isset($patch['listId'])) {
			$targetListId = (int)$patch['listId'];
			// Ensure the target list exists.
			$this->getList($targetListId);
			$item->setListId($targetListId);
		}
		if (isset($patch['sortOrder'])) {
			$item->setSortOrder((int)$patch['sortOrder']);
		}

		$item->setUpdatedAt(time());
		$this->itemMapper->update($item);
		if (array_key_exists('storeIds', $patch)) {
			$this->itemStoreMapper->setStoresForItem((int)$item->getId(), (array)$patch['storeIds']);
		}
		if (array_key_exists('labelIds', $patch)) {
			$this->itemLabelMapper->setLabelsForItem((int)$item->getId(), (array)$patch['labelIds']);
		}
		// The whole price set is replaced when 'prices' is present in the patch.
		if (array_key_exists('prices', $patch)) {
			$this->itemPriceMapper->setPricesForItem((int)$item->getId(), $this->normalizePrices($patch['prices']));
		}
		return $item;
	}

	/**
	 * Batch reorder items within a list.
	 *
	 * @param int $listId List id.
	 * @param array<array{id: int, sortOrder: int}> $items Reorder entries.
	 */
	public function reorderItems(int $listId, array $items): void {
		// A reseed rewrites every row's sort_order, so run the per-row updates in
		// one transaction rather than issuing each UPDATE unguarded.
		$this->atomic(function () use ($listId, $items): void {
			foreach ($items as $entry) {
				$id = (int)($entry['id'] ?? 0);
				$sortOrder = (int)($entry['sortOrder'] ?? 0);
				if ($id <= 0) {
					continue;
				}
				try {
					$item = $this->itemMapper->findById($id);
				} catch (DoesNotExistException) {
					continue;
				}
				if ($item->getListId() !== $listId) {
					continue;
				}
				$item->setSortOrder($sortOrder);
				$item->setUpdatedAt(time());
				$this->itemMapper->update($item);
			}
		}, $this->db);
	}

	/**
	 * Move a batch of items to a target list. Missing items are skipped.
	 * Target-list existence is validated once. Returns the updated items in the
	 * same order as the input ids (skipping any that no longer exist).
	 *
	 * @param list<int> $itemIds
	 * @return ChecklistItem[]
	 */
	public function moveItems(array $itemIds, int $targetListId): array {
		// Validate the destination once; per-item updates then only touch list_id.
		$this->getList($targetListId);
		$updated = [];
		foreach ($itemIds as $itemId) {
			try {
				$updated[] = $this->updateItem((int)$itemId, ['listId' => $targetListId]);
			} catch (NotFoundException) {
				continue;
			}
		}
		return $updated;
	}

	/**
	 * Assign (or clear, when $categoryId is null) a category on a batch of
	 * items. Missing items are skipped. Returns the updated items.
	 *
	 * @param list<int> $itemIds
	 * @return ChecklistItem[]
	 */
	public function setItemsCategory(array $itemIds, ?int $categoryId): array {
		$updated = [];
		foreach ($itemIds as $itemId) {
			try {
				$updated[] = $this->updateItem((int)$itemId, ['categoryId' => $categoryId]);
			} catch (NotFoundException) {
				continue;
			}
		}
		return $updated;
	}

	/**
	 * Replace the set of stores attached to a batch of items. Missing items are
	 * skipped. Returns the updated items.
	 *
	 * @param list<int> $itemIds
	 * @param int[] $storeIds
	 * @return ChecklistItem[]
	 */
	public function setItemsStore(array $itemIds, array $storeIds): array {
		$updated = [];
		foreach ($itemIds as $itemId) {
			try {
				$updated[] = $this->updateItem((int)$itemId, ['storeIds' => $storeIds]);
			} catch (NotFoundException) {
				continue;
			}
		}
		return $updated;
	}

	/**
	 * Replace the set of labels attached to a batch of items. Missing items are
	 * skipped. Returns the updated items.
	 *
	 * @param list<int> $itemIds
	 * @param int[] $labelIds
	 * @return ChecklistItem[]
	 */
	public function setItemsLabels(array $itemIds, array $labelIds): array {
		$updated = [];
		foreach ($itemIds as $itemId) {
			try {
				$updated[] = $this->updateItem((int)$itemId, ['labelIds' => $labelIds]);
			} catch (NotFoundException) {
				continue;
			}
		}
		return $updated;
	}

	/**
	 * Soft-delete a batch of items. Missing items are skipped.
	 *
	 * @param list<int> $itemIds
	 */
	public function deleteItems(array $itemIds): void {
		foreach ($itemIds as $itemId) {
			try {
				$this->deleteItem((int)$itemId);
			} catch (NotFoundException) {
				continue;
			}
		}
	}

	/**
	 * Permanently delete a batch of items, bypassing the trash. Missing items
	 * are skipped.
	 *
	 * @param list<int> $itemIds
	 */
	public function permanentlyDeleteItems(array $itemIds): void {
		foreach ($itemIds as $itemId) {
			try {
				$this->permanentlyDeleteItem((int)$itemId);
			} catch (NotFoundException) {
				continue;
			}
		}
	}

	public function toggleItem(int $itemId, string $uid, ?int $now = null): ChecklistItem {
		$item = $this->getItem($itemId);
		$now ??= time();

		if (!$item->getDone()) {
			$item->setDone(true);
			$item->setDoneAt($now);
			$item->setDoneBy($uid);
			// "Once" items are soft-deleted from the list when marked done. The
			// row stays in the DB (deleted_at set) so it can be surfaced in a
			// trash view, while default queries hide it.
			if ($item->getDeleteOnDone()) {
				$item->setDeletedAt($now);
				$item->setUpdatedAt($now);
				$this->itemMapper->update($item);
				return $item;
			}
			if ($item->getRrule() !== null) {
				$item->setNextDueAt($this->computeNextDueAt($item, $now)?->getTimestamp());
			}
		} else {
			$item->setDone(false);
			$item->setDoneAt(null);
			$item->setDoneBy(null);
			$item->setNextDueAt(null);
		}
		$item->setUpdatedAt($now);
		$this->itemMapper->update($item);
		return $item;
	}

	/**
	 * Clear the done-state on a batch of items (bulk "uncheck all"). Mirrors the
	 * uncheck branch of {@see toggleItem} per item: done → not done, clearing
	 * done_at / done_by / next_due_at. Items that are already unchecked are left
	 * untouched (idempotent). "Once" items were soft-deleted on completion so they
	 * are not among a list's checked items and are never resurrected here. Runs in
	 * one transaction; returns only the items that actually changed.
	 *
	 * @param int[] $itemIds
	 * @return ChecklistItem[]
	 */
	public function uncheckItems(array $itemIds, ?int $now = null): array {
		$now ??= time();
		return $this->atomic(function () use ($itemIds, $now): array {
			$changed = [];
			foreach ($itemIds as $rawId) {
				try {
					$item = $this->getItem((int)$rawId);
				} catch (NotFoundException) {
					continue;
				}
				if (!$item->getDone()) {
					continue;
				}
				$item->setDone(false);
				$item->setDoneAt(null);
				$item->setDoneBy(null);
				$item->setNextDueAt(null);
				$item->setUpdatedAt($now);
				$this->itemMapper->update($item);
				$changed[] = $item;
			}
			return $changed;
		}, $this->db);
	}

	/**
	 * Compute the next due time for an item that was just marked done, reopened,
	 * or copied.
	 *
	 * The stored next_due_at is the raw RRULE occurrence — the house reopen time
	 * of day is NOT baked in here. The background job applies the reopen time
	 * live (see isDueToReopen), so a change to the setting takes effect on the
	 * next run instead of after a full recurrence cycle.
	 *
	 * - "from completion" mode: interval counts from now.
	 * - "fixed schedule", sub-daily rule (hourly/minutely): next occurrence
	 *   strictly after now.
	 * - "fixed schedule", day-granular rule: next occurrence strictly after the
	 *   end of today, so an early reopen at the reopen time does not re-fire the
	 *   same day.
	 */
	private function computeNextDueAt(ChecklistItem $item, int $now): ?\DateTimeImmutable {
		$rrule = $item->getRrule();
		if ($rrule === null) {
			return null;
		}
		if ($item->getRepeatFromCompletion()) {
			$nowDt = (new \DateTimeImmutable())->setTimestamp($now);
			return $this->recurrence->computeNextOccurrence($rrule, $nowDt);
		}
		$anchor = (new \DateTimeImmutable())->setTimestamp($item->getCreatedAt() ?: $now);
		if ($this->recurrence->isSubDaily($rrule)) {
			$after = (new \DateTimeImmutable())->setTimestamp($now);
			return $this->recurrence->nextOccurrenceAfter($rrule, $anchor, $after);
		}
		$endOfToday = $this->startOfDay($now)->modify('+1 day')->modify('-1 second');
		return $this->recurrence->nextOccurrenceAfter($rrule, $anchor, $endOfToday);
	}

	/**
	 * Whether a due-candidate item should reopen (or re-nudge) at $now.
	 *
	 * Sub-daily rules fire at their exact next_due_at. Day-granular rules fire
	 * once their occurrence day has arrived AND $now is past the house's
	 * configured reopen time — applied live here, never baked into next_due_at,
	 * so changing the setting takes effect immediately.
	 */
	private function isDueToReopen(ChecklistItem $item, int $now): bool {
		$nextDue = $item->getNextDueAt();
		$rrule = $item->getRrule();
		if ($nextDue === null || $rrule === null) {
			return false;
		}
		if ($this->recurrence->isSubDaily($rrule)) {
			return $nextDue <= $now;
		}
		$startOfToday = $this->startOfDay($now);
		$startOfTomorrow = $startOfToday->modify('+1 day')->getTimestamp();
		if ($nextDue >= $startOfTomorrow) {
			// The occurrence day has not arrived yet.
			return false;
		}
		$minutes = $this->reopenTimeMinutes($item);
		$reopenMoment = $startOfToday->setTime(intdiv($minutes, 60), $minutes % 60, 0)->getTimestamp();
		return $now >= $reopenMoment;
	}

	/**
	 * The house reopen time (minutes since midnight) for an item's list, falling
	 * back to the default when the house cannot be resolved.
	 */
	private function reopenTimeMinutes(ChecklistItem $item): int {
		$house = $this->houseForItem($item);
		return $house !== null ? $house->getRecurrenceTime() : House::DEFAULT_RECURRENCE_TIME;
	}

	/**
	 * Midnight (server timezone) of the day containing $timestamp.
	 */
	private function startOfDay(int $timestamp): \DateTimeImmutable {
		return (new \DateTimeImmutable())->setTimestamp($timestamp)->setTime(0, 0, 0);
	}

	/**
	 * Resolve the house owning an item's list, memoized per list. Returns null
	 * when the list or house no longer exists.
	 */
	private function houseForItem(ChecklistItem $item): ?House {
		$listId = $item->getListId();
		if (array_key_exists($listId, $this->houseByList)) {
			return $this->houseByList[$listId];
		}
		$house = null;
		try {
			$list = $this->listMapper->findById($listId, true);
			$house = $this->houseMapper->findById($list->getHouseId());
		} catch (\Throwable) {
			$house = null;
		}
		$this->houseByList[$listId] = $house;
		return $house;
	}

	/**
	 * Reopen recurring items that are due today, once $now is past each house's
	 * reopen time.
	 *
	 * Candidates are fetched broadly (occurrence day today or earlier); the
	 * reopen time is applied live per house in isDueToReopen, so a setting change
	 * takes effect on the next run without waiting for a full cycle.
	 *
	 * Called both lazily from listItems() and periodically by the background job.
	 *
	 * @return ChecklistItem[] The items that were reopened.
	 */
	public function reopenDueItems(?int $now = null): array {
		$now ??= time();
		$candidates = $this->itemMapper->findDueRecurring($this->startOfDay($now)->modify('+1 day')->getTimestamp());
		$reopened = [];
		foreach ($candidates as $item) {
			if (!$this->isDueToReopen($item, $now)) {
				continue;
			}
			$item->setDone(false);
			$item->setDoneAt(null);
			$item->setDoneBy(null);
			if ($item->getRepeatFromCompletion()) {
				// Completion-based: next interval starts when the user checks
				// the item off again, so clear the schedule for now.
				$item->setNextDueAt(null);
			} else {
				// Fixed schedule: advance to the next occurrence so the item
				// keeps cycling even if the user never interacts with it.
				$item->setNextDueAt($this->computeNextDueAt($item, $now)?->getTimestamp());
			}
			$item->setUpdatedAt($now);
			$this->itemMapper->update($item);
			$reopened[] = $item;
		}
		return $reopened;
	}

	/**
	 * Advance fixed-schedule undone items whose occurrence day has arrived and
	 * are past their house reopen time.
	 *
	 * Unlike reopenDueItems(), these items are already undone — nothing to
	 * reopen. We just bump next_due_at to the next occurrence so the background
	 * job can re-notify on the next cycle. Gated by the same live reopen-time
	 * check as reopenDueItems().
	 *
	 * @return ChecklistItem[] The items whose schedule was advanced.
	 */
	public function advanceDueReminders(?int $now = null): array {
		$now ??= time();
		$candidates = $this->itemMapper->findDueFixedScheduleUndone($this->startOfDay($now)->modify('+1 day')->getTimestamp());
		$advanced = [];
		foreach ($candidates as $item) {
			if (!$this->isDueToReopen($item, $now)) {
				continue;
			}
			$item->setNextDueAt($this->computeNextDueAt($item, $now)?->getTimestamp());
			$item->setUpdatedAt($now);
			$this->itemMapper->update($item);
			$advanced[] = $item;
		}
		return $advanced;
	}

	/**
	 * Create a fresh copy of an item on a target list. The copy carries over
	 * name, description, category, quantity, recurrence, delete-on-done and
	 * image references, but starts undone with no completion metadata. The
	 * caller is responsible for duplicating the image file (if any) and
	 * passing the new file id/owner.
	 */
	public function copyItem(
		int $itemId,
		int $targetListId,
		?string $addedBy,
		?int $imageFileId,
		?string $imageUploadedBy,
	): ChecklistItem {
		$source = $this->getItem($itemId);
		$this->getList($targetListId);

		$now = time();
		$copy = new ChecklistItem();
		$copy->setListId($targetListId);
		$copy->setName($source->getName());
		$copy->setDescription($source->getDescription());
		$copy->setCategoryId($source->getCategoryId());
		$copy->setQuantity($source->getQuantity());
		$copy->setDone(false);
		$copy->setDoneAt(null);
		$copy->setDoneBy(null);
		$copy->setRrule($source->getRrule());
		$copy->setRepeatFromCompletion($source->getRepeatFromCompletion());
		$copy->setDeleteOnDone($source->getDeleteOnDone());
		if ($source->getRrule() !== null && !$source->getRepeatFromCompletion()) {
			$copy->setNextDueAt($this->computeNextDueAt($copy, $now)?->getTimestamp());
		} else {
			$copy->setNextDueAt(null);
		}
		$copy->setImageFileId($imageFileId);
		$copy->setImageUploadedBy($imageUploadedBy);
		$copy->setAddedBy($addedBy);
		$copy->setSortOrder(0);
		$copy->setCreatedAt($now);
		$copy->setUpdatedAt($now);
		/** @var ChecklistItem $saved */
		$saved = $this->itemMapper->insert($copy);
		$this->itemStoreMapper->setStoresForItem(
			(int)$saved->getId(),
			$this->itemStoreMapper->findStoreIdsForItem((int)$source->getId()),
		);
		$this->itemLabelMapper->setLabelsForItem(
			(int)$saved->getId(),
			$this->itemLabelMapper->findLabelIdsForItem((int)$source->getId()),
		);
		$this->itemPriceMapper->setPricesForItem(
			(int)$saved->getId(),
			$this->itemPriceMapper->findForItem((int)$source->getId()),
		);
		return $saved;
	}

	public function deleteItem(int $itemId): void {
		$item = $this->getItem($itemId);
		$now = time();
		$item->setDeletedAt($now);
		$item->setUpdatedAt($now);
		$this->itemMapper->update($item);
	}

	/**
	 * Permanently remove an item, regardless of whether it is currently in
	 * trash. Bypasses the soft-delete row and erases it from the table.
	 */
	public function permanentlyDeleteItem(int $itemId): void {
		$item = $this->getItem($itemId, includeDeleted: true);
		$this->itemStoreMapper->deleteByItem((int)$item->getId());
		$this->itemLabelMapper->deleteByItem((int)$item->getId());
		$this->itemPriceMapper->deleteByItem((int)$item->getId());
		$this->itemMapper->delete($item);
	}

	/**
	 * Restore a soft-deleted item by clearing its deleted_at marker.
	 */
	public function restoreItem(int $itemId): ChecklistItem {
		$item = $this->getItem($itemId, includeDeleted: true);
		$item->setDeletedAt(null);
		$item->setUpdatedAt(time());
		$this->itemMapper->update($item);
		return $item;
	}

	/**
	 * Hard-delete every soft-deleted item in the list.
	 */
	public function emptyTrash(int $listId): void {
		$this->itemMapper->emptyTrashForList($listId);
	}

	/**
	 * List archived items for a list. Most recently archived first.
	 *
	 * @return ChecklistItem[]
	 */
	public function listArchivedItems(int $listId): array {
		return $this->itemMapper->findArchivedByList($listId);
	}

	/**
	 * Archive an item by stamping its archived_at marker. Archived items are
	 * hidden from the active view but, unlike trash, are never auto-purged.
	 */
	public function archiveItem(int $itemId): ChecklistItem {
		$item = $this->getItem($itemId);
		$now = time();
		$item->setArchivedAt($now);
		$item->setUpdatedAt($now);
		$this->itemMapper->update($item);
		return $item;
	}

	/**
	 * Unarchive an item by clearing its archived_at marker.
	 */
	public function unarchiveItem(int $itemId): ChecklistItem {
		$item = $this->getItem($itemId);
		$item->setArchivedAt(null);
		$item->setUpdatedAt(time());
		$this->itemMapper->update($item);
		return $item;
	}

	/**
	 * Archive a batch of items. Missing items are skipped.
	 *
	 * @param list<int> $itemIds
	 */
	public function archiveItems(array $itemIds): void {
		foreach ($itemIds as $itemId) {
			try {
				$this->archiveItem((int)$itemId);
			} catch (NotFoundException) {
				continue;
			}
		}
	}

	/**
	 * Unarchive a batch of items. Missing items are skipped.
	 *
	 * @param list<int> $itemIds
	 */
	public function unarchiveItems(array $itemIds): void {
		foreach ($itemIds as $itemId) {
			try {
				$this->unarchiveItem((int)$itemId);
			} catch (NotFoundException) {
				continue;
			}
		}
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

	private function floatOrNull(mixed $v): ?float {
		if ($v === null || $v === '' || $v === false) {
			return null;
		}
		if (is_numeric($v)) {
			$f = (float)$v;
			return $f < 0 ? null : $f;
		}
		return null;
	}

	/**
	 * Normalize a list of raw price entries into their stored form.
	 *
	 * Each entry is normalized individually (see {@see normalizePriceEntry}); an
	 * entry that collapses to "no price" is dropped. Entries are keyed by store id
	 * so at most one price survives per store, and at most one store-less price
	 * (null store id).
	 *
	 * @param mixed $input a list of raw price entries
	 * @return list<array{storeId: ?int, priceType: ?string, priceMin: ?float, priceMax: ?float, priceCurrency: ?string}>
	 */
	private function normalizePrices(mixed $input): array {
		if (!is_array($input)) {
			return [];
		}
		$byStore = [];
		foreach ($input as $entry) {
			if (!is_array($entry)) {
				continue;
			}
			$price = $this->normalizePriceEntry($entry);
			if ($price['priceType'] === null) {
				continue;
			}
			$storeId = isset($entry['storeId']) && $entry['storeId'] !== null ? (int)$entry['storeId'] : null;
			$byStore[$storeId === null ? 'none' : $storeId] = ['storeId' => $storeId] + $price;
		}
		return array_values($byStore);
	}

	/**
	 * Normalize a single price entry into its stored form.
	 *
	 * A missing/blank amount collapses the price to "none" (all null). 'set' keeps
	 * only the min; 'range' keeps both, and falls back to 'set' if no max is given.
	 * The currency is retained only when a price actually exists.
	 *
	 * @param array<string, mixed> $data
	 * @return array{priceType: ?string, priceMin: ?float, priceMax: ?float, priceCurrency: ?string}
	 */
	private function normalizePriceEntry(array $data): array {
		$none = ['priceType' => null, 'priceMin' => null, 'priceMax' => null, 'priceCurrency' => null];

		$type = is_string($data['priceType'] ?? null) ? $data['priceType'] : null;
		if ($type !== 'set' && $type !== 'range') {
			return $none;
		}
		$min = $this->floatOrNull($data['priceMin'] ?? null);
		if ($min === null) {
			return $none;
		}
		$currency = $this->strOrNull($data['priceCurrency'] ?? null);
		$currency = $currency !== null ? strtoupper($currency) : null;

		if ($type === 'range') {
			$max = $this->floatOrNull($data['priceMax'] ?? null);
			if ($max === null) {
				// Range without an upper bound is just a single price.
				return ['priceType' => 'set', 'priceMin' => $min, 'priceMax' => null, 'priceCurrency' => $currency];
			}
			// Keep min <= max regardless of the order they came in.
			if ($max < $min) {
				[$min, $max] = [$max, $min];
			}
			return ['priceType' => 'range', 'priceMin' => $min, 'priceMax' => $max, 'priceCurrency' => $currency];
		}

		return ['priceType' => 'set', 'priceMin' => $min, 'priceMax' => null, 'priceCurrency' => $currency];
	}
}
