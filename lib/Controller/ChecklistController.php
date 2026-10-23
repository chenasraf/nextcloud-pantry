<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Controller;

use OCA\Pantry\Db\Checklist;
use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Exception\ForbiddenException;
use OCA\Pantry\Exception\NotFoundException;
use OCA\Pantry\Permission\Permission;
use OCA\Pantry\ResponseDefinitions;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;

/**
 * The checklist resource. `/lists` only ever serves checklists: clients that
 * predate list kinds treat everything it returns as one.
 *
 * @psalm-import-type PantryList from ResponseDefinitions
 * @psalm-import-type PantryListItem from ResponseDefinitions
 * @psalm-import-type PantrySuccess from ResponseDefinitions
 * @psalm-import-type PantryBatchResult from ResponseDefinitions
 */
final class ChecklistController extends ListKindOCSController {
	use TranslatesDomainExceptions;

	protected function kind(): string {
		return Checklist::KIND_CHECKLIST;
	}

	/**
	 * List all checklists in a house
	 *
	 * @param int $houseId House id.
	 * @param string $sortBy Sort mode (custom, name_asc, name_desc).
	 * @param int<1, 500> $limit Maximum number of lists to return.
	 * @param int<0, max> $offset Number of lists to skip.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryList>, array{}>
	 *
	 * 200: Lists returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/lists')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function indexLists(int $houseId, string $sortBy = 'custom', int $limit = 100, int $offset = 0): DataResponse {
		return $this->doIndexLists($houseId, $sortBy, $limit, $offset);
	}

	/**
	 * Batch reorder lists in a house
	 *
	 * Lists of every kind share one sort order, so ids of any kind are accepted.
	 *
	 * @param int $houseId House id.
	 * @param list<array{id: int, sortOrder: int}> $items Reorder entries.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Lists reordered
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/lists/reorder')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function reorderLists(int $houseId, array $items = []): DataResponse {
		return $this->runAction(function () use ($houseId, $items): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->lists->reorderLists($houseId, $items);
			return new DataResponse(['success' => true]);
		});
	}

	/**
	 * Create a checklist in a house
	 *
	 * @param int $houseId House id.
	 * @param string $name List name.
	 * @param string|null $description Optional description.
	 * @param string|null $icon Optional icon key.
	 * @param string|null $color Optional accent color (hex, e.g. "#03a9f4"). Null clears.
	 * @param 'remember'|'none'|'once'|'recurring'|null $defaultRecurrenceMode Recurrence new items start with, or "remember" to follow the last one added.
	 * @param string|null $defaultRrule Rule new items repeat by when the mode is "recurring".
	 * @param bool|null $defaultRepeatFromCompletion Whether that rule counts its interval from completion.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryList, array{}>
	 *
	 * 200: List created
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/lists')]
	#[NoAdminRequired]
	#[Permission(['canCreateLists'])]
	public function createList(int $houseId, string $name, ?string $description = null, ?string $icon = null, ?string $color = null, ?string $defaultRecurrenceMode = null, ?string $defaultRrule = null, ?bool $defaultRepeatFromCompletion = null): DataResponse {
		$recurrenceDefault = array_filter([
			'defaultRecurrenceMode' => $defaultRecurrenceMode,
			'defaultRrule' => $defaultRrule,
			'defaultRepeatFromCompletion' => $defaultRepeatFromCompletion,
		], static fn ($v) => $v !== null);
		return $this->doCreateList($houseId, $name, $description, $icon, $color, $recurrenceDefault);
	}

	/**
	 * List soft-deleted checklists in a house (trash)
	 *
	 * Returns lists whose deleted_at is set, most recently deleted first.
	 *
	 * @param int $houseId House id.
	 * @param int<1, 500> $limit Maximum number of lists to return.
	 * @param int<0, max> $offset Number of lists to skip.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryList>, array{}>
	 *
	 * 200: Deleted lists returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/lists/trash')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function indexDeletedLists(int $houseId, int $limit = 200, int $offset = 0): DataResponse {
		return $this->doIndexDeletedLists($houseId, $limit, $offset);
	}

	/**
	 * List archived checklists in a house
	 *
	 * Returns lists whose archived_at is set, most recently archived first.
	 *
	 * @param int $houseId House id.
	 * @param int<1, 500> $limit Maximum number of lists to return.
	 * @param int<0, max> $offset Number of lists to skip.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryList>, array{}>
	 *
	 * 200: Archived lists returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/lists/archive')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function indexArchivedLists(int $houseId, int $limit = 200, int $offset = 0): DataResponse {
		return $this->doIndexArchivedLists($houseId, $limit, $offset);
	}

	/**
	 * Empty the house's checklists trash, permanently deleting every soft-deleted list
	 *
	 * @param int $houseId House id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Trash emptied
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/lists/trash')]
	#[NoAdminRequired]
	#[Permission(['canDeleteLists'])]
	public function emptyListsTrash(int $houseId): DataResponse {
		return $this->doEmptyListsTrash($houseId);
	}

	/**
	 * Get a checklist
	 *
	 * @param int $houseId House id.
	 * @param int $listId List id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryList, array{}>
	 *
	 * 200: List returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/lists/{listId}', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function showList(int $houseId, int $listId): DataResponse {
		return $this->doShowList($houseId, $listId);
	}

	/**
	 * Update a checklist
	 *
	 * @param int $houseId House id.
	 * @param int $listId List id.
	 * @param string|null $name New name.
	 * @param string|null $description New description.
	 * @param string|null $icon New icon key.
	 * @param string|null $color New accent color (hex). Pass an empty string to clear.
	 * @param int|null $sortOrder New sort order.
	 * @param bool|null $deleteOnDoneDefault New default for the "Once" toggle on the add-item form.
	 * @param 'remember'|'none'|'once'|'recurring'|null $defaultRecurrenceMode Recurrence new items start with, or "remember" to follow the last one added.
	 * @param 'none'|'once'|'recurring'|null $defaultRecurrenceKind Recurrence new items start with right now, rewritten by the add-item form while the mode is "remember".
	 * @param string|null $defaultRrule Rule new items repeat by when the recurrence is "recurring". Pass an empty string to clear.
	 * @param bool|null $defaultRepeatFromCompletion Whether that rule counts its interval from completion.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryList, array{}>
	 *
	 * 200: List updated
	 */
	#[ApiRoute(verb: 'PATCH', url: '/api/houses/{houseId}/lists/{listId}', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function updateList(int $houseId, int $listId, ?string $name = null, ?string $description = null, ?string $icon = null, ?string $color = null, ?int $sortOrder = null, ?bool $deleteOnDoneDefault = null, ?string $defaultRecurrenceMode = null, ?string $defaultRecurrenceKind = null, ?string $defaultRrule = null, ?bool $defaultRepeatFromCompletion = null): DataResponse {
		$patch = array_filter([
			'name' => $name,
			'description' => $description,
			'icon' => $icon,
			'color' => $color,
			'sortOrder' => $sortOrder,
			'deleteOnDoneDefault' => $deleteOnDoneDefault,
			'defaultRecurrenceMode' => $defaultRecurrenceMode,
			'defaultRecurrenceKind' => $defaultRecurrenceKind,
			'defaultRrule' => $defaultRrule,
			'defaultRepeatFromCompletion' => $defaultRepeatFromCompletion,
		], static fn ($v) => $v !== null);
		return $this->doUpdateList($houseId, $listId, $patch);
	}

	/**
	 * Update the values new items on a list start with
	 *
	 * Each key is merged on its own; omitted keys are left as they are, and a
	 * mode of "none" clears a key. Custom fields merge by field id, where "none"
	 * returns the field to its own default. Without edit rights on the list, only
	 * keys already in "remember" mode can be updated, and only their value.
	 *
	 * @param int $houseId House id.
	 * @param int $listId List id.
	 * @param array{mode: 'none'|'fixed'|'remember', value?: array{kind: 'none'|'once'|'recurring', rrule?: string|null, repeatFromCompletion?: bool}|null}|null $recurrence Recurrence new items start with.
	 * @param array{mode: 'none'|'fixed'|'remember', value?: list<int>|null}|null $stores Stores new items start with.
	 * @param array{mode: 'none'|'fixed'|'remember', value?: int|null}|null $category Category new items start with.
	 * @param array{mode: 'none'|'fixed'|'remember', value?: list<int>|null}|null $labels Labels new items start with.
	 * @param array{mode: 'none'|'fixed', value?: string|null}|null $quantity Quantity new items start with.
	 * @param list<array{fieldId: int, mode: 'none'|'fixed'|'remember', value?: array{valueText?: string|null, valueNumber?: float|null, valueBool?: bool, valueDate?: int|null, valueOptionId?: int|null, offsetDays?: int|null}|null}>|null $fields Custom-field values new items start with.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryList, array{}>
	 *
	 * 200: Item defaults updated
	 */
	#[ApiRoute(verb: 'PATCH', url: '/api/houses/{houseId}/lists/{listId}/item-defaults', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canAddItems'])]
	public function updateItemDefaults(int $houseId, int $listId, ?array $recurrence = null, ?array $stores = null, ?array $category = null, ?array $labels = null, ?array $quantity = null, ?array $fields = null): DataResponse {
		$patch = array_filter([
			'recurrence' => $recurrence,
			'stores' => $stores,
			'category' => $category,
			'labels' => $labels,
			'quantity' => $quantity,
			'fields' => $fields,
		], static fn (?array $entry): bool => $entry !== null);
		return $this->doUpdateItemDefaults($houseId, $listId, $patch);
	}

	/**
	 * Delete a checklist
	 *
	 * @param int $houseId House id.
	 * @param int $listId List id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: List deleted
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/lists/{listId}', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canDeleteLists'])]
	public function deleteList(int $houseId, int $listId): DataResponse {
		return $this->doDeleteList($houseId, $listId);
	}

	/**
	 * Restore a soft-deleted checklist back into the active list index
	 *
	 * @param int $houseId House id.
	 * @param int $listId List id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryList, array{}>
	 *
	 * 200: List restored
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/lists/{listId}/restore', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canDeleteLists'])]
	public function restoreList(int $houseId, int $listId): DataResponse {
		return $this->doRestoreList($houseId, $listId);
	}

	/**
	 * Archive a checklist, moving it out of the active index into the archive view
	 *
	 * @param int $houseId House id.
	 * @param int $listId List id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryList, array{}>
	 *
	 * 200: List archived
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/lists/{listId}/archive', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function archiveList(int $houseId, int $listId): DataResponse {
		return $this->doArchiveList($houseId, $listId);
	}

	/**
	 * Unarchive a checklist back into the active list index
	 *
	 * @param int $houseId House id.
	 * @param int $listId List id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryList, array{}>
	 *
	 * 200: List unarchived
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/lists/{listId}/unarchive', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function unarchiveList(int $houseId, int $listId): DataResponse {
		return $this->doUnarchiveList($houseId, $listId);
	}

	/**
	 * Duplicate a checklist, copying its items into a new list
	 *
	 * Items in the list's trash or archive are not copied. Each copied item's
	 * image file is duplicated so the two lists never share a file. Categories,
	 * labels and custom fields scoped to the source list are duplicated too, and
	 * the copied items point at the new ones.
	 *
	 * @param int $houseId House id.
	 * @param int $listId Id of the list to duplicate.
	 * @param string $name Name for the new list.
	 * @param bool $resetDone Whether every copied item starts unchecked.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryList, array{}>
	 *
	 * 200: List duplicated
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/lists/{listId}/duplicate', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canCreateLists'])]
	public function duplicateList(int $houseId, int $listId, string $name, bool $resetDone = true): DataResponse {
		return $this->doDuplicateList($houseId, $listId, $name, $resetDone);
	}

	/**
	 * Duplicate a list of any kind as a new checklist
	 *
	 * Copies what checklist items have and defaults the rest. Items from a
	 * template or an inventory start unchecked, and their recurrence starts
	 * running; `resetDone` only applies when the source is a checklist.
	 * Otherwise copies like duplicating a checklist.
	 *
	 * @param int $houseId House id.
	 * @param int $listId Id of the list to duplicate, of any kind.
	 * @param string $name Name for the new checklist.
	 * @param bool $resetDone Whether every item copied from a checklist starts unchecked.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryList, array{}>
	 *
	 * 200: Checklist created from the list
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/lists/from/{listId}', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canCreateLists'])]
	public function duplicateAsChecklist(int $houseId, int $listId, string $name, bool $resetDone = true): DataResponse {
		return $this->doDuplicateFrom($houseId, $listId, $name, $resetDone);
	}

	/**
	 * Permanently delete a checklist, bypassing the trash
	 *
	 * Works on both live lists and lists already in trash. Also wipes every
	 * item that belongs to the list, including items in the list's own item-trash.
	 *
	 * @param int $houseId House id.
	 * @param int $listId List id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: List permanently deleted
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/lists/{listId}/permanent', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canDeleteLists'])]
	public function permanentlyDeleteList(int $houseId, int $listId): DataResponse {
		return $this->doPermanentlyDeleteList($houseId, $listId);
	}

	/**
	 * List items across every checklist in a house (meta "All checklists" view)
	 *
	 * Sort modes: newest, oldest, name_asc, name_desc, category. "custom" is
	 * not supported because sort_order is per-list.
	 *
	 * @param int $houseId House id.
	 * @param string $sortBy Sort mode (newest, oldest, name_asc, name_desc, category).
	 * @param int<1, 1000> $limit Maximum number of items to return.
	 * @param int<0, max> $offset Number of items to skip.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryListItem>, array{}>
	 *
	 * 200: Items returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/items')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function indexHouseItems(int $houseId, string $sortBy = 'newest', int $limit = 1000, int $offset = 0): DataResponse {
		return $this->runAction(function () use ($houseId, $sortBy, $limit, $offset): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$categorySort = $this->prefs->getCategorySort($uid, $houseId);
			$all = $this->lists->listItemsForHouse($houseId, $sortBy, $categorySort);
			// Items inherit their parent list's access; hide items in lists the
			// caller cannot reach. Cache the verdict per list id.
			$listAccess = [];
			$all = array_values(array_filter($all, function ($i) use ($houseId, $uid, &$listAccess) {
				$listId = (int)$i->getListId();
				if (!isset($listAccess[$listId])) {
					$listAccess[$listId] = $this->permissions->canAccessList($houseId, $uid, $listId);
				}
				return $listAccess[$listId];
			}));
			$sliced = array_slice($all, max(0, $offset), max(0, $limit));
			return new DataResponse($this->serializeItems($sliced));
		});
	}

	/**
	 * List items in a checklist
	 *
	 * Auto-reopens recurring items whose next occurrence has arrived.
	 *
	 * @param int $houseId House id.
	 * @param int $listId List id.
	 * @param string $sortBy Sort mode (custom, newest, oldest, name_asc, name_desc, category).
	 * @param int<1, 1000> $limit Maximum number of items to return.
	 * @param int<0, max> $offset Number of items to skip.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryListItem>, array{}>
	 *
	 * 200: Items returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/lists/{listId}/items')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function indexItems(int $houseId, int $listId, string $sortBy = 'custom', int $limit = 200, int $offset = 0): DataResponse {
		return $this->doIndexItems($houseId, $listId, $sortBy, $limit, $offset);
	}

	/**
	 * List soft-deleted items in a checklist (trash)
	 *
	 * Returns items whose deleted_at is set, most recently deleted first.
	 *
	 * @param int $houseId House id.
	 * @param int $listId List id.
	 * @param int<1, 1000> $limit Maximum number of items to return.
	 * @param int<0, max> $offset Number of items to skip.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryListItem>, array{}>
	 *
	 * 200: Deleted items returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/lists/{listId}/items/trash')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function indexDeletedItems(int $houseId, int $listId, int $limit = 200, int $offset = 0): DataResponse {
		return $this->doIndexDeletedItems($houseId, $listId, $limit, $offset);
	}

	/**
	 * List archived items in a checklist
	 *
	 * Returns items whose archived_at is set, most recently archived first.
	 *
	 * @param int $houseId House id.
	 * @param int $listId List id.
	 * @param int<1, 1000> $limit Maximum number of items to return.
	 * @param int<0, max> $offset Number of items to skip.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryListItem>, array{}>
	 *
	 * 200: Archived items returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/lists/{listId}/items/archive')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function indexArchivedItems(int $houseId, int $listId, int $limit = 200, int $offset = 0): DataResponse {
		return $this->doIndexArchivedItems($houseId, $listId, $limit, $offset);
	}

	/**
	 * Add an item to a list
	 *
	 * @param int $houseId House id.
	 * @param int $listId List id.
	 * @param string $name Item name.
	 * @param string|null $description Optional description.
	 * @param int|null $categoryId Optional category id (must belong to the same house).
	 * @param string|null $quantity Optional quantity string.
	 * @param string|null $rrule Optional RFC 5545 RRULE for recurrence.
	 * @param bool $repeatFromCompletion If true, the next occurrence is measured from when the item is marked done; if false, the schedule is anchored at item creation.
	 * @param bool $deleteOnDone If true, the item is deleted when marked done.
	 * @param int|null $sortOrder Optional sort order.
	 * @param list<int> $storeIds Optional store ids to attach (must belong to the same house).
	 * @param string|null $barcode Optional barcode (EAN/UPC) the item was created from.
	 * @param list<array{storeId?: int|null, priceType?: string|null, priceMin?: float|null, priceMax?: float|null, priceCurrency?: string|null}> $prices Optional prices. A null store id is the store-less (default) price; at most one per store and one store-less.
	 * @param list<int> $labelIds Optional label ids to attach (must belong to the same house).
	 * @param list<array{fieldId: int, valueText?: string|null, valueNumber?: float|null, valueBool?: bool, valueDate?: int|null, valueOptionId?: int|null, offsetDays?: int|null, notifyOverride?: bool, notifyEnabled?: bool, notifyLeadDays?: int|null}> $customFields Optional custom-field values. Each field must belong to the house and apply to this list; at most one value per field.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryListItem, array{}>
	 *
	 * 200: Item added
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/lists/{listId}/items')]
	#[NoAdminRequired]
	#[Permission(['canAddItems'])]
	public function addItem(
		int $houseId,
		int $listId,
		string $name,
		?string $description = null,
		?int $categoryId = null,
		?string $quantity = null,
		?string $rrule = null,
		bool $repeatFromCompletion = false,
		bool $deleteOnDone = false,
		?int $sortOrder = null,
		array $storeIds = [],
		?string $barcode = null,
		array $prices = [],
		array $labelIds = [],
		array $customFields = [],
	): DataResponse {
		return $this->doAddItem($houseId, $listId, [
			'name' => $name,
			'description' => $description,
			'categoryId' => $categoryId,
			'quantity' => $quantity,
			'rrule' => $rrule,
			'repeatFromCompletion' => $repeatFromCompletion,
			'deleteOnDone' => $deleteOnDone,
			'sortOrder' => $sortOrder,
			'storeIds' => $storeIds,
			'barcode' => $barcode,
			'prices' => $prices,
			'labelIds' => $labelIds,
			'customFields' => $customFields,
		]);
	}

	/**
	 * Update an item
	 *
	 * @param int $houseId House id.
	 * @param int $listId List id.
	 * @param int $itemId Item id.
	 * @param string|null $name New name.
	 * @param string|null $description New description (empty string clears).
	 * @param int|null $categoryId New category id (0 or negative clears).
	 * @param string|null $quantity New quantity (empty string clears).
	 * @param string|null $rrule New RRULE (empty string clears).
	 * @param bool|null $repeatFromCompletion New recurrence anchor mode.
	 * @param bool|null $deleteOnDone If true, the item is deleted when marked done.
	 * @param int|null $imageFileId File id of attached image (0 or negative clears).
	 * @param int|null $sortOrder New sort order.
	 * @param int|null $targetListId Move item to a different checklist (must belong to the same house).
	 * @param list<int>|null $storeIds New set of store ids (empty array clears; null leaves unchanged). All must belong to the same house.
	 * @param string|null $barcode New barcode (EAN/UPC); empty string clears.
	 * @param list<array{storeId?: int|null, priceType?: string|null, priceMin?: float|null, priceMax?: float|null, priceCurrency?: string|null}>|null $prices New full set of prices (empty array clears all; null leaves them unchanged). A null store id is the store-less price; at most one per store and one store-less.
	 * @param list<int>|null $labelIds New set of label ids (empty array clears; null leaves unchanged). All must belong to the same house.
	 * @param list<array{fieldId: int, valueText?: string|null, valueNumber?: float|null, valueBool?: bool, valueDate?: int|null, valueOptionId?: int|null, offsetDays?: int|null, notifyOverride?: bool, notifyEnabled?: bool, notifyLeadDays?: int|null}>|null $customFields New full set of custom-field values (empty array clears all; null leaves them unchanged). Each field must belong to the house and apply to the item's list.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryListItem, array{}>
	 *
	 * 200: Item updated
	 */
	#[ApiRoute(verb: 'PATCH', url: '/api/houses/{houseId}/lists/{listId}/items/{itemId}')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function updateItem(
		int $houseId,
		int $listId,
		int $itemId,
		?string $name = null,
		?string $description = null,
		?int $categoryId = null,
		?string $quantity = null,
		?string $rrule = null,
		?bool $repeatFromCompletion = null,
		?bool $deleteOnDone = null,
		?int $imageFileId = null,
		?int $sortOrder = null,
		?int $targetListId = null,
		?array $storeIds = null,
		?string $barcode = null,
		?array $prices = null,
		?array $labelIds = null,
		?array $customFields = null,
	): DataResponse {
		return $this->doUpdateItem($houseId, $listId, $itemId, $name, $description, $categoryId, $quantity, $rrule, $repeatFromCompletion, $deleteOnDone, $imageFileId, $sortOrder, $targetListId, $storeIds, $barcode, $prices, $labelIds, $customFields);
	}

	/**
	 * Copy an item to another list
	 *
	 * Creates a new item on the target list with the same fields as the
	 * source. If the source has an image, the underlying file is duplicated
	 * so deleting either side's image will not affect the other.
	 *
	 * @param int $houseId House id.
	 * @param int $listId List id the source item lives on.
	 * @param int $itemId Source item id.
	 * @param int $targetListId Destination checklist id (must belong to the same house).
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryListItem, array{}>
	 *
	 * 200: Item copied
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/lists/{listId}/items/{itemId}/copy')]
	#[NoAdminRequired]
	#[Permission(['canCopyItems'])]
	public function copyItem(int $houseId, int $listId, int $itemId, int $targetListId): DataResponse {
		return $this->doCopyItem($houseId, $listId, $itemId, $targetListId);
	}

	/**
	 * Toggle an item's done status
	 *
	 * @param int $houseId House id.
	 * @param int $listId List id.
	 * @param int $itemId Item id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryListItem, array{}>
	 *
	 * 200: Item toggled
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/lists/{listId}/items/{itemId}/toggle')]
	#[NoAdminRequired]
	#[Permission(['canCheckItems'])]
	public function toggleItem(int $houseId, int $listId, int $itemId): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $itemId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			[, $list] = $this->loadItem($houseId, $listId, $itemId);
			$toggled = $this->lists->toggleItem($itemId, $uid);
			$houseName = $this->houses->get($houseId)->getName();
			if ($toggled->getDone()) {
				$this->notifications->notifyItemDone($houseId, $uid, $toggled->getName(), $list->getName());
				$this->activity->publishItemDone(
					$houseId,
					$houseName,
					$uid,
					$itemId,
					$toggled->getName(),
					(int)$list->getId(),
					$list->getName(),
				);
			} else {
				$this->activity->publishItemReopened(
					$houseId,
					$houseName,
					$uid,
					$itemId,
					$toggled->getName(),
					(int)$list->getId(),
					$list->getName(),
				);
			}
			return new DataResponse($this->serializeItem($toggled));
		});
	}

	/**
	 * Empty a list's trash, permanently deleting every soft-deleted item
	 *
	 * @param int $houseId House id.
	 * @param int $listId List id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Trash emptied
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/lists/{listId}/items/trash')]
	#[NoAdminRequired]
	#[Permission(['canDeleteItems'])]
	public function emptyTrash(int $houseId, int $listId): DataResponse {
		return $this->doEmptyTrash($houseId, $listId);
	}

	/**
	 * Delete an item
	 *
	 * @param int $houseId House id.
	 * @param int $listId List id.
	 * @param int $itemId Item id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Item deleted
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/lists/{listId}/items/{itemId}', requirements: ['itemId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canDeleteItems'])]
	public function deleteItem(int $houseId, int $listId, int $itemId): DataResponse {
		return $this->doDeleteItem($houseId, $listId, $itemId);
	}

	/**
	 * Restore a soft-deleted item back into the active list
	 *
	 * @param int $houseId House id.
	 * @param int $listId List id.
	 * @param int $itemId Item id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryListItem, array{}>
	 *
	 * 200: Item restored
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/lists/{listId}/items/{itemId}/restore')]
	#[NoAdminRequired]
	#[Permission(['canDeleteItems'])]
	public function restoreItem(int $houseId, int $listId, int $itemId): DataResponse {
		return $this->doRestoreItem($houseId, $listId, $itemId);
	}

	/**
	 * Permanently delete an item, bypassing the trash
	 *
	 * Works on both live items and items already in trash.
	 *
	 * @param int $houseId House id.
	 * @param int $listId List id.
	 * @param int $itemId Item id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Item permanently deleted
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/lists/{listId}/items/{itemId}/permanent')]
	#[NoAdminRequired]
	#[Permission(['canDeleteItems'])]
	public function permanentlyDeleteItem(int $houseId, int $listId, int $itemId): DataResponse {
		return $this->doPermanentlyDeleteItem($houseId, $listId, $itemId);
	}

	/**
	 * Archive an item
	 *
	 * Hides the item from the active list into the archive view. Archived items
	 * are never auto-purged and there is no bulk "empty archive".
	 *
	 * @param int $houseId House id.
	 * @param int $listId List id.
	 * @param int $itemId Item id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryListItem, array{}>
	 *
	 * 200: Item archived
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/lists/{listId}/items/{itemId}/archive')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function archiveItem(int $houseId, int $listId, int $itemId): DataResponse {
		return $this->doArchiveItem($houseId, $listId, $itemId);
	}

	/**
	 * Unarchive an item back into the active list
	 *
	 * @param int $houseId House id.
	 * @param int $listId List id.
	 * @param int $itemId Item id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryListItem, array{}>
	 *
	 * 200: Item unarchived
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/lists/{listId}/items/{itemId}/unarchive')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function unarchiveItem(int $houseId, int $listId, int $itemId): DataResponse {
		return $this->doUnarchiveItem($houseId, $listId, $itemId);
	}

	/**
	 * Batch reorder items in a list
	 *
	 * @param int $houseId House id.
	 * @param int $listId List id.
	 * @param list<array{id: int, sortOrder: int}> $items Reorder entries.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Items reordered
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/lists/{listId}/items/reorder')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function reorderItems(int $houseId, int $listId, array $items = []): DataResponse {
		return $this->doReorderItems($houseId, $listId, $items);
	}

	/**
	 * Validate a batch of item ids for the given house/user.
	 *
	 * Keeps only items that exist, belong to the house, and whose source list
	 * the caller can access (and, when `$kind` is given, is of that kind); every
	 * other id is reported as skipped. Preserves input order.
	 *
	 * @param list<int> $itemIds
	 * @return array{items: list<ChecklistItem>, skipped: list<int>}
	 */
	private function collectAccessibleItems(int $houseId, string $uid, array $itemIds, bool $includeDeleted = false, ?string $kind = null): array {
		$valid = [];
		$skipped = [];
		$listAccess = [];
		$isAdmin = $this->permissions->isAdmin($houseId, $uid);
		foreach ($itemIds as $rawId) {
			$itemId = (int)$rawId;
			try {
				$item = $this->lists->getItem($itemId, $includeDeleted);
				$list = $this->lists->getList((int)$item->getListId());
			} catch (NotFoundException) {
				$skipped[] = $itemId;
				continue;
			}
			if ($list->getHouseId() !== $houseId || ($kind !== null && $list->getKind() !== $kind)) {
				$skipped[] = $itemId;
				continue;
			}
			$listId = (int)$item->getListId();
			if (!isset($listAccess[$listId])) {
				$listAccess[$listId] = $isAdmin || $this->permissions->canAccessList($houseId, $uid, $listId);
			}
			if (!$listAccess[$listId]) {
				$skipped[] = $itemId;
				continue;
			}
			$valid[] = $item;
		}
		return ['items' => $valid, 'skipped' => $skipped];
	}

	/**
	 * Group items by their source list id, returning per-list names for grouped
	 * activity: `[listId => ['listName' => string, 'itemNames' => list<string>]]`.
	 *
	 * @param list<ChecklistItem> $items
	 * @return array<int, array{listName: string, itemNames: list<string>}>
	 */
	private function groupItemsBySourceList(array $items): array {
		$groups = [];
		$listNames = [];
		foreach ($items as $item) {
			$listId = (int)$item->getListId();
			if (!isset($listNames[$listId])) {
				$listNames[$listId] = $this->lists->getList($listId)->getName();
			}
			$groups[$listId]['listName'] = $listNames[$listId];
			$groups[$listId]['itemNames'][] = $item->getName();
		}
		return $groups;
	}

	/**
	 * Move a batch of items to another list in one action
	 *
	 * The destination may be a list of any kind. Items crossing kinds keep the
	 * fields the destination's items have, drop the rest, and start unchecked.
	 *
	 * @param int $houseId House id.
	 * @param list<int> $itemIds Ids of the items to move.
	 * @param int $targetListId Destination list id (must belong to the same house).
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryBatchResult, array{}>
	 *
	 * 200: Items moved
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/items/batch/move')]
	#[NoAdminRequired]
	#[Permission(['canMoveItems'])]
	public function batchMoveItems(int $houseId, array $itemIds = [], int $targetListId = 0): DataResponse {
		return $this->runAction(function () use ($houseId, $itemIds, $targetListId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$targetList = $this->lists->getList($targetListId);
			$this->assertListInHouse($targetList->getHouseId(), $houseId);
			if (!$this->permissions->isAdmin($houseId, $uid)
				&& !$this->permissions->canAccessList($houseId, $uid, $targetListId)) {
				throw new ForbiddenException('No access to the destination list');
			}

			$collected = $this->collectAccessibleItems($houseId, $uid, $itemIds);
			// Items already on the destination are a no-op — drop them so they
			// neither move nor emit a spurious "moved" activity.
			$toMove = array_values(array_filter(
				$collected['items'],
				fn (ChecklistItem $i) => (int)$i->getListId() !== $targetListId,
			));
			$groups = $this->groupItemsBySourceList($toMove);
			$moved = $this->lists->moveItems(array_map(fn (ChecklistItem $i) => (int)$i->getId(), $toMove), $targetListId);

			$houseName = $this->houses->get($houseId)->getName();
			foreach ($groups as $fromListId => $group) {
				$this->activity->publishItemsMoved(
					$houseId,
					$houseName,
					$uid,
					$fromListId,
					$group['listName'],
					$targetListId,
					$targetList->getName(),
					$group['itemNames'],
				);
			}
			return new DataResponse([
				'success' => true,
				'items' => $this->serializeItems($moved),
				'skipped' => $collected['skipped'],
			]);
		});
	}

	/**
	 * Copy a batch of items to another list in one action
	 *
	 * The destination may be a list of any kind. Copies keep the fields the
	 * destination's items have, drop the rest, and start unchecked.
	 *
	 * @param int $houseId House id.
	 * @param list<int> $itemIds Ids of the items to copy.
	 * @param int $targetListId Destination list id (must belong to the same house).
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryBatchResult, array{}>
	 *
	 * 200: Items copied
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/items/batch/copy')]
	#[NoAdminRequired]
	#[Permission(['canCopyItems'])]
	public function batchCopyItems(int $houseId, array $itemIds = [], int $targetListId = 0): DataResponse {
		return $this->runAction(function () use ($houseId, $itemIds, $targetListId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$targetList = $this->lists->getList($targetListId);
			$this->assertListInHouse($targetList->getHouseId(), $houseId);
			if (!$this->permissions->isAdmin($houseId, $uid)
				&& !$this->permissions->canAccessList($houseId, $uid, $targetListId)) {
				throw new ForbiddenException('No access to the destination list');
			}

			$collected = $this->collectAccessibleItems($houseId, $uid, $itemIds);
			$groups = $this->groupItemsBySourceList($collected['items']);
			$copies = [];
			foreach ($collected['items'] as $source) {
				[$newImageFileId, $newImageOwner] = $this->duplicateImageFor($source, $houseId, $uid);
				$copies[] = $this->lists->copyItem((int)$source->getId(), $targetListId, $uid, $newImageFileId, $newImageOwner);
			}

			$houseName = $this->houses->get($houseId)->getName();
			foreach ($groups as $fromListId => $group) {
				$this->activity->publishItemsCopied(
					$houseId,
					$houseName,
					$uid,
					$fromListId,
					$group['listName'],
					$targetListId,
					$targetList->getName(),
					$group['itemNames'],
				);
			}
			return new DataResponse([
				'success' => true,
				'items' => $this->serializeItems($copies),
				'skipped' => $collected['skipped'],
			]);
		});
	}

	/**
	 * Delete a batch of items in one action
	 *
	 * Soft-deletes to the list trash by default; pass permanent=true to erase
	 * items outright (used when clearing selected items from the trash view).
	 *
	 * @param int $houseId House id.
	 * @param list<int> $itemIds Ids of the items to delete.
	 * @param bool $permanent If true, permanently delete instead of moving to trash.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryBatchResult, array{}>
	 *
	 * 200: Items deleted
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/items/batch/delete')]
	#[NoAdminRequired]
	#[Permission(['canDeleteItems'])]
	public function batchDeleteItems(int $houseId, array $itemIds = [], bool $permanent = false): DataResponse {
		return $this->runAction(function () use ($houseId, $itemIds, $permanent): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);

			$collected = $this->collectAccessibleItems($houseId, $uid, $itemIds, includeDeleted: $permanent);
			$validIds = array_map(fn (ChecklistItem $i) => (int)$i->getId(), $collected['items']);

			if ($permanent) {
				$this->lists->permanentlyDeleteItems($validIds);
			} else {
				// Group before deleting so the source list names are still resolvable.
				$groups = $this->groupItemsBySourceList($collected['items']);
				$this->lists->deleteItems($validIds);
				$houseName = $this->houses->get($houseId)->getName();
				foreach ($groups as $listId => $group) {
					$this->activity->publishItemsDeleted(
						$houseId,
						$houseName,
						$uid,
						$listId,
						$group['listName'],
						$group['itemNames'],
					);
				}
			}
			return new DataResponse([
				'success' => true,
				'items' => [],
				'skipped' => $collected['skipped'],
			]);
		});
	}

	/**
	 * Archive (or unarchive) a batch of items in one action
	 *
	 * @param int $houseId House id.
	 * @param list<int> $itemIds Ids of the items to (un)archive.
	 * @param bool $archive If true, archive the items; if false, unarchive them.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryBatchResult, array{}>
	 *
	 * 200: Items archived
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/items/batch/archive')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function batchArchiveItems(int $houseId, array $itemIds = [], bool $archive = true): DataResponse {
		return $this->runAction(function () use ($houseId, $itemIds, $archive): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);

			$collected = $this->collectAccessibleItems($houseId, $uid, $itemIds);
			$validIds = array_map(fn (ChecklistItem $i) => (int)$i->getId(), $collected['items']);

			if ($archive) {
				$this->lists->archiveItems($validIds);
			} else {
				$this->lists->unarchiveItems($validIds);
			}
			return new DataResponse([
				'success' => true,
				'items' => [],
				'skipped' => $collected['skipped'],
			]);
		});
	}

	/**
	 * Assign (or clear) a category on a batch of items in one action
	 *
	 * @param int $houseId House id.
	 * @param list<int> $itemIds Ids of the items to update.
	 * @param int|null $categoryId Category id to assign (must belong to the same house); 0 or null clears.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryBatchResult, array{}>
	 *
	 * 200: Category assigned
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/items/batch/category')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function batchSetCategory(int $houseId, array $itemIds = [], ?int $categoryId = null): DataResponse {
		return $this->runAction(function () use ($houseId, $itemIds, $categoryId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$resolvedCategoryId = ($categoryId !== null && $categoryId > 0) ? $categoryId : null;
			if ($resolvedCategoryId !== null) {
				$this->categories->assertInHouse($resolvedCategoryId, $houseId);
			}

			$collected = $this->collectAccessibleItems($houseId, $uid, $itemIds);
			$groups = $this->groupItemsBySourceList($collected['items']);
			$updated = $this->lists->setItemsCategory(
				array_map(fn (ChecklistItem $i) => (int)$i->getId(), $collected['items']),
				$resolvedCategoryId,
			);

			$houseName = $this->houses->get($houseId)->getName();
			foreach ($groups as $listId => $group) {
				$this->activity->publishItemsCategorized(
					$houseId,
					$houseName,
					$uid,
					$listId,
					$group['listName'],
					$group['itemNames'],
				);
			}
			return new DataResponse([
				'success' => true,
				'items' => $this->serializeItems($updated),
				'skipped' => $collected['skipped'],
			]);
		});
	}

	/**
	 * Clear the done-state on a batch of items in one action ("uncheck all")
	 *
	 * Unchecks every targeted item that is currently done, in a single request
	 * (clearing done_at / done_by). Items already unchecked are left as-is.
	 * Only checklist items are ever checked off, so items of other list kinds
	 * are skipped.
	 *
	 * @param int $houseId House id.
	 * @param list<int> $itemIds Ids of the items to uncheck.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryBatchResult, array{}>
	 *
	 * 200: Items unchecked
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/items/batch/uncheck')]
	#[NoAdminRequired]
	#[Permission(['canCheckItems'])]
	public function batchUncheckItems(int $houseId, array $itemIds = []): DataResponse {
		return $this->runAction(function () use ($houseId, $itemIds): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);

			$collected = $this->collectAccessibleItems($houseId, $uid, $itemIds, kind: Checklist::KIND_CHECKLIST);
			$updated = $this->lists->uncheckItems(
				array_map(fn (ChecklistItem $i) => (int)$i->getId(), $collected['items']),
			);
			return new DataResponse([
				'success' => true,
				'items' => $this->serializeItems($updated),
				'skipped' => $collected['skipped'],
			]);
		});
	}

	/**
	 * Set the stores attached to a batch of items in one action
	 *
	 * Replaces the store set on every targeted item (an empty list clears them).
	 *
	 * @param int $houseId House id.
	 * @param list<int> $itemIds Ids of the items to update.
	 * @param list<int> $storeIds Store ids to assign (must belong to the same house); empty clears.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryBatchResult, array{}>
	 *
	 * 200: Stores assigned
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/items/batch/stores')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function batchSetStores(int $houseId, array $itemIds = [], array $storeIds = []): DataResponse {
		return $this->runAction(function () use ($houseId, $itemIds, $storeIds): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$storeIds = array_map('intval', $storeIds);
			$this->stores->assertStoresInHouse($houseId, $storeIds);

			$collected = $this->collectAccessibleItems($houseId, $uid, $itemIds);
			$groups = $this->groupItemsBySourceList($collected['items']);
			$updated = $this->lists->setItemsStore(
				array_map(fn (ChecklistItem $i) => (int)$i->getId(), $collected['items']),
				$storeIds,
			);

			// Attaching stores reuses the generic "item updated" activity rather
			// than a dedicated subject.
			$this->publishItemsUpdated($houseId, $uid, $updated, $groups);
			return new DataResponse([
				'success' => true,
				'items' => $this->serializeItems($updated),
				'skipped' => $collected['skipped'],
			]);
		});
	}

	/**
	 * Set the labels attached to a batch of items in one action
	 *
	 * Replaces the label set on every targeted item (an empty list clears them).
	 *
	 * @param int $houseId House id.
	 * @param list<int> $itemIds Ids of the items to update.
	 * @param list<int> $labelIds Label ids to assign (must belong to the same house); empty clears.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryBatchResult, array{}>
	 *
	 * 200: Labels assigned
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/items/batch/labels')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function batchSetLabels(int $houseId, array $itemIds = [], array $labelIds = []): DataResponse {
		return $this->runAction(function () use ($houseId, $itemIds, $labelIds): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$labelIds = $this->labels->assertLabelsInHouse($houseId, $labelIds);

			$collected = $this->collectAccessibleItems($houseId, $uid, $itemIds);
			$groups = $this->groupItemsBySourceList($collected['items']);
			$updated = $this->lists->setItemsLabels(
				array_map(fn (ChecklistItem $i) => (int)$i->getId(), $collected['items']),
				$labelIds,
			);

			// Attaching labels reuses the generic "item updated" activity rather
			// than a dedicated subject.
			$this->publishItemsUpdated($houseId, $uid, $updated, $groups);
			return new DataResponse([
				'success' => true,
				'items' => $this->serializeItems($updated),
				'skipped' => $collected['skipped'],
			]);
		});
	}

	/**
	 * @param ChecklistItem[] $updated
	 * @param array<int, array{listName: string, itemNames: list<string>}> $groups
	 */
	private function publishItemsUpdated(int $houseId, string $uid, array $updated, array $groups): void {
		$houseName = $this->houses->get($houseId)->getName();
		foreach ($updated as $item) {
			$this->activity->publishItemUpdated(
				$houseId,
				$houseName,
				$uid,
				(int)$item->getId(),
				$item->getName(),
				(int)$item->getListId(),
				$groups[(int)$item->getListId()]['listName'] ?? '',
			);
		}
	}

	/**
	 * Upload an image for an item
	 *
	 * Expects a multipart/form-data request with the image file in a field
	 * named **image**.
	 *
	 * @param int $houseId House id.
	 * @param int $listId List id.
	 * @param int $itemId Item id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryListItem, array{}>
	 *
	 * 200: Image uploaded and attached
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/lists/{listId}/items/{itemId}/image')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function uploadItemImage(int $houseId, int $listId, int $itemId): DataResponse {
		return $this->doUploadItemImage($houseId, $listId, $itemId);
	}

	/**
	 * Clear the image attached to an item
	 *
	 * @param int $houseId House id.
	 * @param int $listId List id.
	 * @param int $itemId Item id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryListItem, array{}>
	 *
	 * 200: Image cleared
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/lists/{listId}/items/{itemId}/image')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function clearItemImage(int $houseId, int $listId, int $itemId): DataResponse {
		return $this->doClearItemImage($houseId, $listId, $itemId);
	}
}
