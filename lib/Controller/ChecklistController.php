<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Controller;

use OCA\Pantry\Activity\ActivityPublisher;
use OCA\Pantry\Exception\ForbiddenException;
use OCA\Pantry\Exception\NotFoundException;
use OCA\Pantry\Permission\Permission;
use OCA\Pantry\ResponseDefinitions;
use OCA\Pantry\Service\CategoryService;
use OCA\Pantry\Service\ChecklistService;
use OCA\Pantry\Service\HouseAuthService;
use OCA\Pantry\Service\HouseService;
use OCA\Pantry\Service\ImageService;
use OCA\Pantry\Service\NotificationService;
use OCA\Pantry\Service\PermissionService;
use OCA\Pantry\Service\PrefsService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @psalm-import-type PantryList from ResponseDefinitions
 * @psalm-import-type PantryListItem from ResponseDefinitions
 * @psalm-import-type PantrySuccess from ResponseDefinitions
 */
final class ChecklistController extends OCSController {
	use TranslatesDomainExceptions;

	public function __construct(
		string $appName,
		IRequest $request,
		private ChecklistService $lists,
		private CategoryService $categories,
		private HouseAuthService $auth,
		private HouseService $houses,
		private ImageService $images,
		private NotificationService $notifications,
		private ActivityPublisher $activity,
		private PrefsService $prefs,
		private PermissionService $permissions,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
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
		return $this->runAction(function () use ($houseId, $sortBy, $limit, $offset): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$all = $this->lists->listForHouse($houseId, $sortBy);
			$all = array_values(array_filter(
				$all,
				fn ($l) => $this->permissions->canAccessList($houseId, $uid, (int)$l->getId()),
			));
			$sliced = array_slice($all, max(0, $offset), max(0, $limit));
			$out = array_map(fn ($l) => $l->jsonSerialize(), $sliced);
			return new DataResponse($out);
		});
	}

	/**
	 * Batch reorder checklists in a house
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
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryList, array{}>
	 *
	 * 200: List created
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/lists')]
	#[NoAdminRequired]
	#[Permission(['canCreateLists'])]
	public function createList(int $houseId, string $name, ?string $description = null, ?string $icon = null, ?string $color = null): DataResponse {
		return $this->runAction(function () use ($houseId, $name, $description, $icon, $color): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$list = $this->lists->createList($houseId, $name, $description, $icon, $color);
			$this->activity->publishListCreated(
				$houseId,
				$this->houses->get($houseId)->getName(),
				$uid,
				(int)$list->getId(),
				$list->getName(),
			);
			return new DataResponse($list->jsonSerialize());
		});
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
		return $this->runAction(function () use ($houseId, $limit, $offset): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$all = $this->lists->listDeletedForHouse($houseId);
			$sliced = array_slice($all, max(0, $offset), max(0, $limit));
			return new DataResponse(array_map(fn ($l) => $l->jsonSerialize(), $sliced));
		});
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
		return $this->runAction(function () use ($houseId): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->lists->emptyListsTrash($houseId);
			return new DataResponse(['success' => true]);
		});
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
		return $this->runAction(function () use ($houseId, $listId): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$list = $this->lists->getList($listId);
			$this->assertListInHouse($list->getHouseId(), $houseId);
			return new DataResponse($list->jsonSerialize());
		});
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
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryList, array{}>
	 *
	 * 200: List updated
	 */
	#[ApiRoute(verb: 'PATCH', url: '/api/houses/{houseId}/lists/{listId}', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function updateList(int $houseId, int $listId, ?string $name = null, ?string $description = null, ?string $icon = null, ?string $color = null, ?int $sortOrder = null, ?bool $deleteOnDoneDefault = null): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $name, $description, $icon, $color, $sortOrder, $deleteOnDoneDefault): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$existing = $this->lists->getList($listId);
			$this->assertListInHouse($existing->getHouseId(), $houseId);
			$patch = [];
			if ($name !== null) {
				$patch['name'] = $name;
			}
			if ($description !== null) {
				$patch['description'] = $description;
			}
			if ($icon !== null) {
				$patch['icon'] = $icon;
			}
			if ($color !== null) {
				$patch['color'] = $color;
			}
			if ($sortOrder !== null) {
				$patch['sortOrder'] = $sortOrder;
			}
			if ($deleteOnDoneDefault !== null) {
				$patch['deleteOnDoneDefault'] = $deleteOnDoneDefault;
			}
			$list = $this->lists->updateList($listId, $patch);
			$contentChanged = $name !== null || $description !== null || $icon !== null || $color !== null;
			if ($contentChanged) {
				$this->activity->publishListUpdated(
					$houseId,
					$this->houses->get($houseId)->getName(),
					$uid,
					$listId,
					$list->getName(),
				);
			}
			return new DataResponse($list->jsonSerialize());
		});
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
		return $this->runAction(function () use ($houseId, $listId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$existing = $this->lists->getList($listId);
			$this->assertListInHouse($existing->getHouseId(), $houseId);
			$listName = $existing->getName();
			$this->lists->deleteList($listId);
			$this->activity->publishListDeleted(
				$houseId,
				$this->houses->get($houseId)->getName(),
				$uid,
				$listId,
				$listName,
			);
			return new DataResponse(['success' => true]);
		});
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
		return $this->runAction(function () use ($houseId, $listId): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$existing = $this->lists->getList($listId, includeDeleted: true);
			$this->assertListInHouse($existing->getHouseId(), $houseId);
			$restored = $this->lists->restoreList($listId);
			return new DataResponse($restored->jsonSerialize());
		});
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
		return $this->runAction(function () use ($houseId, $listId): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$existing = $this->lists->getList($listId, includeDeleted: true);
			$this->assertListInHouse($existing->getHouseId(), $houseId);
			$this->lists->permanentlyDeleteList($listId);
			return new DataResponse(['success' => true]);
		});
	}

	/**
	 * List items across every list in a house (meta "All lists" view)
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
			$items = array_map(fn ($i) => $i->jsonSerialize(), $sliced);
			return new DataResponse($items);
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
		return $this->runAction(function () use ($houseId, $listId, $sortBy, $limit, $offset): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$list = $this->lists->getList($listId);
			$this->assertListInHouse($list->getHouseId(), $houseId);
			$categorySort = $this->prefs->getCategorySort($uid, $houseId);
			$all = $this->lists->listItems($listId, $sortBy, $categorySort);
			$sliced = array_slice($all, max(0, $offset), max(0, $limit));
			$items = array_map(fn ($i) => $i->jsonSerialize(), $sliced);
			return new DataResponse($items);
		});
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
		return $this->runAction(function () use ($houseId, $listId, $limit, $offset): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$list = $this->lists->getList($listId);
			$this->assertListInHouse($list->getHouseId(), $houseId);
			$all = $this->lists->listDeletedItems($listId);
			$sliced = array_slice($all, max(0, $offset), max(0, $limit));
			$items = array_map(fn ($i) => $i->jsonSerialize(), $sliced);
			return new DataResponse($items);
		});
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
	): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $name, $description, $categoryId, $quantity, $rrule, $repeatFromCompletion, $deleteOnDone, $sortOrder): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$list = $this->lists->getList($listId);
			$this->assertListInHouse($list->getHouseId(), $houseId);
			if ($categoryId !== null) {
				$this->categories->assertInHouse($categoryId, $houseId);
			}
			$item = $this->lists->addItem($listId, [
				'name' => $name,
				'description' => $description,
				'categoryId' => $categoryId,
				'quantity' => $quantity,
				'rrule' => $rrule,
				'repeatFromCompletion' => $repeatFromCompletion,
				'deleteOnDone' => $deleteOnDone,
				'sortOrder' => $sortOrder ?? 0,
			], $uid);
			$this->notifications->notifyItemAdded($houseId, $uid, $item->getName(), $list->getName());
			$this->activity->publishItemAdded(
				$houseId,
				$this->houses->get($houseId)->getName(),
				$uid,
				(int)$item->getId(),
				$item->getName(),
				$listId,
				$list->getName(),
			);
			return new DataResponse($item->jsonSerialize());
		});
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
	 * @param int|null $targetListId Move item to a different list (must belong to the same house).
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
	): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $itemId, $name, $description, $categoryId, $quantity, $rrule, $repeatFromCompletion, $deleteOnDone, $imageFileId, $sortOrder, $targetListId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$item = $this->lists->getItem($itemId);
			$list = $this->lists->getList($item->getListId());
			$this->assertListInHouse($list->getHouseId(), $houseId);
			if ($item->getListId() !== $listId) {
				throw new NotFoundException('Item does not belong to this list');
			}
			$patch = [];
			if ($name !== null) {
				$patch['name'] = $name;
			}
			if ($description !== null) {
				$patch['description'] = $description;
			}
			if ($categoryId !== null) {
				if ($categoryId > 0) {
					$this->categories->assertInHouse($categoryId, $houseId);
					$patch['categoryId'] = $categoryId;
				} else {
					$patch['categoryId'] = null;
				}
			}
			if ($quantity !== null) {
				$patch['quantity'] = $quantity;
			}
			if ($rrule !== null) {
				$patch['rrule'] = $rrule;
			}
			if ($repeatFromCompletion !== null) {
				$patch['repeatFromCompletion'] = $repeatFromCompletion;
			}
			if ($deleteOnDone !== null) {
				$patch['deleteOnDone'] = $deleteOnDone;
			}
			if ($imageFileId !== null) {
				$patch['imageFileId'] = $imageFileId > 0 ? $imageFileId : null;
			}
			if ($sortOrder !== null) {
				$patch['sortOrder'] = $sortOrder;
			}
			$targetList = null;
			if ($targetListId !== null && $targetListId !== $listId) {
				// Moving an item to another list needs the move capability and
				// access to the destination list (admins bypass both).
				if (!$this->permissions->isAdmin($houseId, $uid)) {
					if (!$this->permissions->can($houseId, $uid, 'canMoveItems')) {
						throw new ForbiddenException('Missing permission: canMoveItems');
					}
					if (!$this->permissions->canAccessList($houseId, $uid, $targetListId)) {
						throw new ForbiddenException('No access to the destination list');
					}
				}
				$targetList = $this->lists->getList($targetListId);
				$this->assertListInHouse($targetList->getHouseId(), $houseId);
				$patch['listId'] = $targetListId;
			}
			$updated = $this->lists->updateItem($itemId, $patch);

			$houseName = $this->houses->get($houseId)->getName();
			if ($targetList !== null && $targetList->getId() !== $list->getId()) {
				$this->activity->publishItemMoved(
					$houseId,
					$houseName,
					$uid,
					$itemId,
					$updated->getName(),
					(int)$list->getId(),
					$list->getName(),
					(int)$targetList->getId(),
					$targetList->getName(),
				);
			} elseif ($name !== null || $description !== null || $categoryId !== null || $quantity !== null || $rrule !== null || $repeatFromCompletion !== null || $deleteOnDone !== null || $imageFileId !== null) {
				$this->activity->publishItemUpdated(
					$houseId,
					$houseName,
					$uid,
					$itemId,
					$updated->getName(),
					(int)$list->getId(),
					$list->getName(),
				);
			}
			return new DataResponse($updated->jsonSerialize());
		});
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
	 * @param int $targetListId Destination list id (must belong to the same house).
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryListItem, array{}>
	 *
	 * 200: Item copied
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/lists/{listId}/items/{itemId}/copy')]
	#[NoAdminRequired]
	#[Permission(['canCopyItems'])]
	public function copyItem(int $houseId, int $listId, int $itemId, int $targetListId): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $itemId, $targetListId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$source = $this->lists->getItem($itemId);
			$sourceList = $this->lists->getList($source->getListId());
			$this->assertListInHouse($sourceList->getHouseId(), $houseId);
			if ($source->getListId() !== $listId) {
				throw new NotFoundException('Item does not belong to this list');
			}
			$targetList = $this->lists->getList($targetListId);
			$this->assertListInHouse($targetList->getHouseId(), $houseId);
			if (!$this->permissions->isAdmin($houseId, $uid)
				&& !$this->permissions->canAccessList($houseId, $uid, $targetListId)) {
				throw new ForbiddenException('No access to the destination list');
			}

			$newImageFileId = null;
			$newImageOwner = null;
			if ($source->getImageFileId() !== null && $source->getImageUploadedBy() !== null) {
				$newImageFileId = $this->images->duplicateItemImage(
					$source->getImageUploadedBy(),
					$source->getImageFileId(),
					$uid,
					$houseId,
				);
				if ($newImageFileId !== null) {
					$newImageOwner = $uid;
				}
			}

			$copy = $this->lists->copyItem($itemId, $targetListId, $uid, $newImageFileId, $newImageOwner);

			$houseName = $this->houses->get($houseId)->getName();
			$this->activity->publishItemCopied(
				$houseId,
				$houseName,
				$uid,
				(int)$copy->getId(),
				$copy->getName(),
				(int)$sourceList->getId(),
				$sourceList->getName(),
				(int)$targetList->getId(),
				$targetList->getName(),
			);
			return new DataResponse($copy->jsonSerialize());
		});
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
			$item = $this->lists->getItem($itemId);
			$list = $this->lists->getList($item->getListId());
			$this->assertListInHouse($list->getHouseId(), $houseId);
			if ($item->getListId() !== $listId) {
				throw new NotFoundException('Item does not belong to this list');
			}
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
			return new DataResponse($toggled->jsonSerialize());
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
		return $this->runAction(function () use ($houseId, $listId): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$list = $this->lists->getList($listId);
			$this->assertListInHouse($list->getHouseId(), $houseId);
			$this->lists->emptyTrash($listId);
			return new DataResponse(['success' => true]);
		});
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
		return $this->runAction(function () use ($houseId, $listId, $itemId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$item = $this->lists->getItem($itemId);
			$list = $this->lists->getList($item->getListId());
			$this->assertListInHouse($list->getHouseId(), $houseId);
			if ($item->getListId() !== $listId) {
				throw new NotFoundException('Item does not belong to this list');
			}
			$itemName = $item->getName();
			$this->lists->deleteItem($itemId);
			$this->activity->publishItemDeleted(
				$houseId,
				$this->houses->get($houseId)->getName(),
				$uid,
				$itemId,
				$itemName,
				(int)$list->getId(),
				$list->getName(),
			);
			return new DataResponse(['success' => true]);
		});
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
		return $this->runAction(function () use ($houseId, $listId, $itemId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$item = $this->lists->getItem($itemId, includeDeleted: true);
			$list = $this->lists->getList($item->getListId());
			$this->assertListInHouse($list->getHouseId(), $houseId);
			if ($item->getListId() !== $listId) {
				throw new NotFoundException('Item does not belong to this list');
			}
			$restored = $this->lists->restoreItem($itemId);
			$this->activity->publishItemRestored(
				$houseId,
				$this->houses->get($houseId)->getName(),
				$uid,
				$itemId,
				$restored->getName(),
				(int)$list->getId(),
				$list->getName(),
			);
			return new DataResponse($restored->jsonSerialize());
		});
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
		return $this->runAction(function () use ($houseId, $listId, $itemId): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$item = $this->lists->getItem($itemId, includeDeleted: true);
			$list = $this->lists->getList($item->getListId());
			$this->assertListInHouse($list->getHouseId(), $houseId);
			if ($item->getListId() !== $listId) {
				throw new NotFoundException('Item does not belong to this list');
			}
			$this->lists->permanentlyDeleteItem($itemId);
			return new DataResponse(['success' => true]);
		});
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
		return $this->runAction(function () use ($houseId, $listId, $items): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$list = $this->lists->getList($listId);
			$this->assertListInHouse($list->getHouseId(), $houseId);
			$this->lists->reorderItems($listId, $items);
			return new DataResponse(['success' => true]);
		});
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
		return $this->runAction(function () use ($houseId, $listId, $itemId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$item = $this->lists->getItem($itemId);
			$list = $this->lists->getList($item->getListId());
			$this->assertListInHouse($list->getHouseId(), $houseId);
			if ($item->getListId() !== $listId) {
				throw new NotFoundException('Item does not belong to this list');
			}

			$data = $this->request->getUploadedFile('image');
			if ($data === null || !is_array($data) || ($data['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
				throw new \InvalidArgumentException('No image uploaded');
			}
			$tmp = (string)($data['tmp_name'] ?? '');
			if ($tmp === '' || !is_uploaded_file($tmp)) {
				throw new \InvalidArgumentException('Invalid upload');
			}
			$bytes = file_get_contents($tmp);
			if ($bytes === false) {
				throw new \RuntimeException('Could not read uploaded file');
			}
			$original = (string)($data['name'] ?? 'image.jpg');
			$fileId = $this->images->uploadForUser($uid, $houseId, $original, $bytes);

			$updated = $this->lists->updateItem($itemId, ['imageFileId' => $fileId, 'imageUploadedBy' => $uid]);
			return new DataResponse($updated->jsonSerialize());
		});
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
		return $this->runAction(function () use ($houseId, $listId, $itemId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$item = $this->lists->getItem($itemId);
			$list = $this->lists->getList($item->getListId());
			$this->assertListInHouse($list->getHouseId(), $houseId);
			if ($item->getListId() !== $listId) {
				throw new NotFoundException('Item does not belong to this list');
			}
			$updated = $this->lists->updateItem($itemId, ['imageFileId' => null, 'imageUploadedBy' => null]);
			return new DataResponse($updated->jsonSerialize());
		});
	}

	private function requireUid(): string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new ForbiddenException('Not authenticated');
		}
		return $user->getUID();
	}

	private function assertListInHouse(int $listHouseId, int $routeHouseId): void {
		if ($listHouseId !== $routeHouseId) {
			throw new NotFoundException('List does not belong to this house');
		}
	}
}
