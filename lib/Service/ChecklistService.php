<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Db\Checklist;
use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Db\ChecklistItemMapper;
use OCA\Pantry\Db\ChecklistMapper;
use OCA\Pantry\Exception\NotFoundException;
use OCA\Pantry\Latch\PantrySource;
use OCA\Pantry\Latch\Payload\CategorySuggestionContext;
use OCP\AppFramework\Db\DoesNotExistException;

class ChecklistService {
	public function __construct(
		private ChecklistMapper $listMapper,
		private ChecklistItemMapper $itemMapper,
		private RecurrenceService $recurrence,
		private PantrySource $hooks,
	) {
	}

	// ----- Lists -----

	/**
	 * @return Checklist[]
	 */
	public function listForHouse(int $houseId): array {
		return $this->listMapper->findByHouse($houseId);
	}

	public function getList(int $listId): Checklist {
		try {
			return $this->listMapper->findById($listId);
		} catch (DoesNotExistException) {
			throw new NotFoundException('List not found');
		}
	}

	public function createList(int $houseId, string $name, ?string $description, ?string $icon = null, ?string $actorUid = null): Checklist {
		$filtered = $this->hooks->filterListBeforeCreate($houseId, [
			'name' => $name,
			'description' => $description,
			'icon' => $icon,
		], $actorUid);
		$data = $filtered->data;
		$name = trim((string)($data['name'] ?? ''));
		if ($name === '') {
			throw new \InvalidArgumentException('List name cannot be empty');
		}
		$description = isset($data['description']) && is_string($data['description']) ? $data['description'] : null;
		$icon = isset($data['icon']) && is_string($data['icon']) ? $data['icon'] : null;
		$now = time();
		$list = new Checklist();
		$list->setHouseId($houseId);
		$list->setName($name);
		$list->setDescription($description !== null && $description !== '' ? $description : null);
		if ($icon !== null && $icon !== '') {
			$list->setIcon($icon);
		}
		$list->setSortOrder(0);
		$list->setCreatedAt($now);
		$list->setUpdatedAt($now);
		/** @var Checklist $saved */
		$saved = $this->listMapper->insert($list);
		$this->hooks->dispatchListCreated($saved, $actorUid);
		return $saved;
	}

	public function updateList(int $listId, array $patch, ?string $actorUid = null): Checklist {
		$list = $this->getList($listId);
		$previous = $list->jsonSerialize();
		$filtered = $this->hooks->filterListBeforeUpdate($list->getHouseId(), $patch, $previous, $actorUid);
		$patch = $filtered->data;
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
		if (isset($patch['sortOrder'])) {
			$list->setSortOrder((int)$patch['sortOrder']);
		}
		$list->setUpdatedAt(time());
		$this->listMapper->update($list);
		$this->hooks->dispatchListUpdated($list, $previous, $actorUid);
		return $list;
	}

	public function deleteList(int $listId, ?string $actorUid = null): void {
		$list = $this->getList($listId);
		$this->itemMapper->deleteByList((int)$list->getId());
		$this->listMapper->delete($list);
		$this->hooks->dispatchListDeleted($list, $actorUid);
	}

	// ----- Items -----

	/**
	 * List items for a list, auto-unchecking any recurring items whose next_due_at has passed.
	 *
	 * @return ChecklistItem[]
	 */
	public function listItems(int $listId, string $sortBy = 'custom', ?int $now = null): array {
		// Eagerly reopen any due recurring items in this list before returning.
		$this->reopenDueItems($now);
		return $this->itemMapper->findByList($listId, $sortBy);
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

	public function addItem(int $listId, array $data, ?string $actorUid = null): ChecklistItem {
		// Ensure the list exists.
		$list = $this->getList($listId);

		$filtered = $this->hooks->filterItemBeforeCreate($listId, $data, $actorUid);
		$data = $filtered->data;

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

		// If no category was supplied, ask category.suggestions handlers.
		$categoryId = $this->intOrNull($data['categoryId'] ?? null);
		if ($categoryId === null) {
			$categoryId = $this->hooks->collectCategorySuggestion(new CategorySuggestionContext(
				$list->getHouseId(),
				$listId,
				$name,
				$this->strOrNull($data['quantity'] ?? null),
				$this->strOrNull($data['description'] ?? null),
			));
		}

		$now = time();
		$item = new ChecklistItem();
		$item->setListId($listId);
		$item->setName($name);
		$item->setDescription($this->strOrNull($data['description'] ?? null));
		$item->setCategoryId($categoryId);
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
			$item->setNextDueAt($this->resolveNextDueAt($item, $now));
		} else {
			$item->setNextDueAt(null);
		}
		$item->setImageFileId($this->intOrNull($data['imageFileId'] ?? null));
		$item->setSortOrder(isset($data['sortOrder']) ? (int)$data['sortOrder'] : 0);
		$item->setCreatedAt($now);
		$item->setUpdatedAt($now);
		/** @var ChecklistItem $saved */
		$saved = $this->itemMapper->insert($item);
		$this->hooks->dispatchItemCreated($saved, $actorUid);
		return $saved;
	}

	public function updateItem(int $itemId, array $patch, ?string $actorUid = null): ChecklistItem {
		$item = $this->getItem($itemId);
		$previous = $item->jsonSerialize();
		$filtered = $this->hooks->filterItemBeforeUpdate($item->getListId(), $patch, $previous, $actorUid);
		$patch = $filtered->data;

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
		if (array_key_exists('imageUploadedBy', $patch)) {
			$v = $patch['imageUploadedBy'];
			$item->setImageUploadedBy(is_string($v) && $v !== '' ? $v : null);
		}
		// If already done and rrule or mode changed, recompute next due.
		if ($item->getDone() && $item->getRrule() !== null
			&& (array_key_exists('rrule', $patch) || array_key_exists('repeatFromCompletion', $patch))) {
			$item->setNextDueAt($this->resolveNextDueAt($item, time()));
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
		$this->hooks->dispatchItemUpdated($item, $previous, $actorUid);
		return $item;
	}

	/**
	 * Batch reorder items within a list.
	 *
	 * @param int $listId List id.
	 * @param array<array{id: int, sortOrder: int}> $items Reorder entries.
	 */
	public function reorderItems(int $listId, array $items): void {
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
	}

	public function toggleItem(int $itemId, string $uid, ?int $now = null): ChecklistItem {
		$item = $this->getItem($itemId);
		$now ??= time();
		$wasDone = $item->getDone();

		if (!$wasDone) {
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
				$this->hooks->dispatchItemCompleted($item, $uid);
				return $item;
			}
			if ($item->getRrule() !== null) {
				$item->setNextDueAt($this->resolveNextDueAt($item, $now));
			}
		} else {
			$item->setDone(false);
			$item->setDoneAt(null);
			$item->setDoneBy(null);
			$item->setNextDueAt(null);
		}
		$item->setUpdatedAt($now);
		$this->itemMapper->update($item);
		if (!$wasDone) {
			$this->hooks->dispatchItemCompleted($item, $uid);
		} else {
			$this->hooks->dispatchItemReopened($item, $uid);
		}
		return $item;
	}

	/**
	 * Compute the next due time for an item that was just marked done.
	 *
	 * - "from completion" mode: interval counts from now — next occurrence = now + one step.
	 * - "fixed schedule" mode: the schedule is anchored at the item's creation time; next
	 *   occurrence is the first one strictly after now on that anchored series.
	 */
	private function computeNextDueAt(ChecklistItem $item, int $now): ?\DateTimeImmutable {
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

	/**
	 * Compute the next-due timestamp for an item and pass it through the
	 * item.next-due-at filter so external schedulers can override Pantry's
	 * default rrule computation.
	 */
	private function resolveNextDueAt(ChecklistItem $item, int $now): ?int {
		$default = $this->computeNextDueAt($item, $now)?->getTimestamp();
		return $this->hooks->filterItemNextDueAt($item, $default, $now);
	}

	/**
	 * Reopen all recurring items whose next_due_at has passed.
	 *
	 * Called both lazily from listItems() and periodically by the background job.
	 *
	 * @return ChecklistItem[] The items that were reopened.
	 */
	public function reopenDueItems(?int $now = null): array {
		$now ??= time();
		$items = $this->itemMapper->findDueRecurring($now);
		foreach ($items as $item) {
			$item->setDone(false);
			$item->setDoneAt(null);
			$item->setDoneBy(null);
			if ($item->getRepeatFromCompletion()) {
				// Completion-based: next interval starts when the user checks
				// the item off again, so clear the schedule for now.
				$item->setNextDueAt(null);
			} else {
				// Fixed schedule: immediately compute the next occurrence so the
				// item keeps cycling even if the user never interacts with it.
				$item->setNextDueAt($this->resolveNextDueAt($item, $now));
			}
			$item->setUpdatedAt($now);
			$this->itemMapper->update($item);
			$this->hooks->dispatchItemReopened($item, null);
		}
		return $items;
	}

	/**
	 * Advance fixed-schedule undone items whose next_due_at has passed.
	 *
	 * Unlike reopenDueItems(), these items are already undone — nothing to
	 * reopen. We just bump next_due_at to the next occurrence so the
	 * background job can re-notify on the next cycle.
	 *
	 * @return ChecklistItem[] The items whose schedule was advanced.
	 */
	public function advanceDueReminders(?int $now = null): array {
		$now ??= time();
		$items = $this->itemMapper->findDueFixedScheduleUndone($now);
		foreach ($items as $item) {
			$item->setNextDueAt($this->resolveNextDueAt($item, $now));
			$item->setUpdatedAt($now);
			$this->itemMapper->update($item);
		}
		return $items;
	}

	public function deleteItem(int $itemId, ?string $actorUid = null): void {
		$item = $this->getItem($itemId);
		$now = time();
		$item->setDeletedAt($now);
		$item->setUpdatedAt($now);
		$this->itemMapper->update($item);
		$this->hooks->dispatchItemDeleted($item, $actorUid);
	}

	/**
	 * Permanently remove an item, regardless of whether it is currently in
	 * trash. Bypasses the soft-delete row and erases it from the table.
	 */
	public function permanentlyDeleteItem(int $itemId, ?string $actorUid = null): void {
		$item = $this->getItem($itemId, includeDeleted: true);
		$this->itemMapper->delete($item);
		$this->hooks->dispatchItemPermanentlyDeleted($item, $actorUid);
	}

	/**
	 * Restore a soft-deleted item by clearing its deleted_at marker.
	 */
	public function restoreItem(int $itemId, ?string $actorUid = null): ChecklistItem {
		$item = $this->getItem($itemId, includeDeleted: true);
		$item->setDeletedAt(null);
		$item->setUpdatedAt(time());
		$this->itemMapper->update($item);
		$this->hooks->dispatchItemRestored($item, $actorUid);
		return $item;
	}

	/**
	 * Hard-delete every soft-deleted item in the list.
	 */
	public function emptyTrash(int $listId): void {
		$this->itemMapper->emptyTrashForList($listId);
	}

	/**
	 * Serialize a single item for API responses, applying the
	 * `item.render-name` filter and merging `extraActions` / `badges` from
	 * the corresponding collect points.
	 *
	 * @return array<string,mixed>
	 */
	public function serializeItem(ChecklistItem $item, ?string $viewerUid = null): array {
		$base = $item->jsonSerialize();
		$base['name'] = $this->hooks->filterItemRenderName($item, $viewerUid);
		$base['extraActions'] = $this->hooks->collectItemExtraActions($item, $viewerUid);
		$base['badges'] = $this->hooks->collectItemBadges($item, $viewerUid);
		$base['contributedBy'] = null;
		return $base;
	}

	/**
	 * Serialize a list-of-items response, merging contributed items from
	 * the `list.contributed-items` collect point. Each contributed item is
	 * tagged with `contributedBy` so the frontend can skip edit/delete UI
	 * for rows not backed by a stored ChecklistItem.
	 *
	 * @param ChecklistItem[] $items
	 * @return list<array<string,mixed>>
	 */
	public function serializeListItems(int $listId, int $houseId, array $items, ?string $viewerUid = null): array {
		$out = [];
		foreach ($items as $item) {
			$out[] = $this->serializeItem($item, $viewerUid);
		}
		foreach ($this->hooks->collectContributedItems($listId, $houseId, $viewerUid) as $contrib) {
			$row = $this->normalizeContributedItem($contrib, $listId);
			$row['contributedBy'] = isset($contrib['contributedBy']) && is_string($contrib['contributedBy'])
				? $contrib['contributedBy']
				: 'external';
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * Coerce a handler-supplied contributed item into the PantryListItem
	 * shape so the response stays consistent.
	 *
	 * @param array<string,mixed> $contrib
	 * @return array<string,mixed>
	 */
	private function normalizeContributedItem(array $contrib, int $defaultListId): array {
		return [
			'id' => isset($contrib['id']) && is_int($contrib['id']) ? $contrib['id'] : 0,
			'listId' => isset($contrib['listId']) && is_int($contrib['listId']) ? $contrib['listId'] : $defaultListId,
			'name' => isset($contrib['name']) && is_string($contrib['name']) ? $contrib['name'] : '',
			'description' => isset($contrib['description']) && is_string($contrib['description']) ? $contrib['description'] : null,
			'categoryId' => isset($contrib['categoryId']) && is_int($contrib['categoryId']) ? $contrib['categoryId'] : null,
			'quantity' => isset($contrib['quantity']) && is_string($contrib['quantity']) ? $contrib['quantity'] : null,
			'done' => !empty($contrib['done']),
			'doneAt' => isset($contrib['doneAt']) && is_int($contrib['doneAt']) ? $contrib['doneAt'] : null,
			'doneBy' => isset($contrib['doneBy']) && is_string($contrib['doneBy']) ? $contrib['doneBy'] : null,
			'rrule' => isset($contrib['rrule']) && is_string($contrib['rrule']) ? $contrib['rrule'] : null,
			'repeatFromCompletion' => !empty($contrib['repeatFromCompletion']),
			'deleteOnDone' => !empty($contrib['deleteOnDone']),
			'nextDueAt' => isset($contrib['nextDueAt']) && is_int($contrib['nextDueAt']) ? $contrib['nextDueAt'] : null,
			'imageFileId' => isset($contrib['imageFileId']) && is_int($contrib['imageFileId']) ? $contrib['imageFileId'] : null,
			'imageUploadedBy' => isset($contrib['imageUploadedBy']) && is_string($contrib['imageUploadedBy']) ? $contrib['imageUploadedBy'] : null,
			'sortOrder' => isset($contrib['sortOrder']) && is_int($contrib['sortOrder']) ? $contrib['sortOrder'] : 0,
			'createdAt' => isset($contrib['createdAt']) && is_int($contrib['createdAt']) ? $contrib['createdAt'] : 0,
			'updatedAt' => isset($contrib['updatedAt']) && is_int($contrib['updatedAt']) ? $contrib['updatedAt'] : 0,
			'deletedAt' => isset($contrib['deletedAt']) && is_int($contrib['deletedAt']) ? $contrib['deletedAt'] : null,
			'extraActions' => isset($contrib['extraActions']) && is_array($contrib['extraActions']) ? $contrib['extraActions'] : [],
			'badges' => isset($contrib['badges']) && is_array($contrib['badges']) ? $contrib['badges'] : [],
		];
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
