<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Controller;

use OCA\Pantry\Exception\ForbiddenException;
use OCA\Pantry\Exception\NotFoundException;
use OCA\Pantry\ResponseDefinitions;
use OCA\Pantry\Service\CategoryService;
use OCA\Pantry\Service\ChecklistService;
use OCA\Pantry\Service\HouseAuthService;
use OCA\Pantry\Service\ImageService;
use OCA\Pantry\Service\NotificationService;
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
		private ImageService $images,
		private NotificationService $notifications,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * List all checklists in a house
	 *
	 * @param int $houseId House id.
	 * @param int<1, 500> $limit Maximum number of lists to return.
	 * @param int<0, max> $offset Number of lists to skip.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryList>, array{}>
	 *
	 * 200: Lists returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/lists')]
	#[NoAdminRequired]
	public function indexLists(int $houseId, int $limit = 100, int $offset = 0): DataResponse {
		return $this->runAction(function () use ($houseId, $limit, $offset): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$all = $this->lists->listForHouse($houseId);
			$sliced = array_slice($all, max(0, $offset), max(0, $limit));
			$out = array_map(fn ($l) => $l->jsonSerialize(), $sliced);
			return new DataResponse($out);
		});
	}

	/**
	 * Create a checklist in a house
	 *
	 * @param int $houseId House id.
	 * @param string $name List name.
	 * @param string|null $description Optional description.
	 * @param string|null $icon Optional icon key.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryList, array{}>
	 *
	 * 200: List created
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/lists')]
	#[NoAdminRequired]
	public function createList(int $houseId, string $name, ?string $description = null, ?string $icon = null): DataResponse {
		return $this->runAction(function () use ($houseId, $name, $description, $icon): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$list = $this->lists->createList($houseId, $name, $description, $icon);
			return new DataResponse($list->jsonSerialize());
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
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/lists/{listId}')]
	#[NoAdminRequired]
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
	 * @param int|null $sortOrder New sort order.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryList, array{}>
	 *
	 * 200: List updated
	 */
	#[ApiRoute(verb: 'PATCH', url: '/api/houses/{houseId}/lists/{listId}')]
	#[NoAdminRequired]
	public function updateList(int $houseId, int $listId, ?string $name = null, ?string $description = null, ?string $icon = null, ?int $sortOrder = null): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $name, $description, $icon, $sortOrder): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
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
			if ($sortOrder !== null) {
				$patch['sortOrder'] = $sortOrder;
			}
			$list = $this->lists->updateList($listId, $patch);
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
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/lists/{listId}')]
	#[NoAdminRequired]
	public function deleteList(int $houseId, int $listId): DataResponse {
		return $this->runAction(function () use ($houseId, $listId): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$existing = $this->lists->getList($listId);
			$this->assertListInHouse($existing->getHouseId(), $houseId);
			$this->lists->deleteList($listId);
			return new DataResponse(['success' => true]);
		});
	}

	/**
	 * List items in a checklist
	 *
	 * Auto-reopens recurring items whose next occurrence has arrived.
	 *
	 * @param int $houseId House id.
	 * @param int $listId List id.
	 * @param int<1, 1000> $limit Maximum number of items to return.
	 * @param int<0, max> $offset Number of items to skip.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryListItem>, array{}>
	 *
	 * 200: Items returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/lists/{listId}/items')]
	#[NoAdminRequired]
	public function indexItems(int $houseId, int $listId, int $limit = 200, int $offset = 0): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $limit, $offset): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$list = $this->lists->getList($listId);
			$this->assertListInHouse($list->getHouseId(), $houseId);
			$all = $this->lists->listItems($listId);
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
	 * @param int|null $sortOrder Optional sort order.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryListItem, array{}>
	 *
	 * 200: Item added
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/lists/{listId}/items')]
	#[NoAdminRequired]
	public function addItem(
		int $houseId,
		int $listId,
		string $name,
		?string $description = null,
		?int $categoryId = null,
		?string $quantity = null,
		?string $rrule = null,
		bool $repeatFromCompletion = false,
		?int $sortOrder = null,
	): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $name, $description, $categoryId, $quantity, $rrule, $repeatFromCompletion, $sortOrder): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
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
				'sortOrder' => $sortOrder ?? 0,
			]);
			$this->notifications->notifyItemAdded($houseId, $this->requireUid(), $item->getName(), $list->getName());
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
	 * @param int|null $imageFileId File id of attached image (0 or negative clears).
	 * @param int|null $sortOrder New sort order.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryListItem, array{}>
	 *
	 * 200: Item updated
	 */
	#[ApiRoute(verb: 'PATCH', url: '/api/houses/{houseId}/lists/{listId}/items/{itemId}')]
	#[NoAdminRequired]
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
		?int $imageFileId = null,
		?int $sortOrder = null,
	): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $itemId, $name, $description, $categoryId, $quantity, $rrule, $repeatFromCompletion, $imageFileId, $sortOrder): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
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
			if ($imageFileId !== null) {
				$patch['imageFileId'] = $imageFileId > 0 ? $imageFileId : null;
			}
			if ($sortOrder !== null) {
				$patch['sortOrder'] = $sortOrder;
			}
			$updated = $this->lists->updateItem($itemId, $patch);
			return new DataResponse($updated->jsonSerialize());
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
			if ($toggled->getDone()) {
				$this->notifications->notifyItemDone($houseId, $uid, $toggled->getName(), $list->getName());
			}
			return new DataResponse($toggled->jsonSerialize());
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
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/lists/{listId}/items/{itemId}')]
	#[NoAdminRequired]
	public function deleteItem(int $houseId, int $listId, int $itemId): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $itemId): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$item = $this->lists->getItem($itemId);
			$list = $this->lists->getList($item->getListId());
			$this->assertListInHouse($list->getHouseId(), $houseId);
			if ($item->getListId() !== $listId) {
				throw new NotFoundException('Item does not belong to this list');
			}
			$this->lists->deleteItem($itemId);
			return new DataResponse(['success' => true]);
		});
	}

	/**
	 * Upload an image for an item
	 *
	 * Uploads the request body as an image into the user's configured pantry
	 * image folder and attaches it to the item.
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
