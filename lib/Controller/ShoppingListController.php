<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Controller;

use OCA\Pantry\Exception\ForbiddenException;
use OCA\Pantry\Exception\NotFoundException;
use OCA\Pantry\ResponseDefinitions;
use OCA\Pantry\Service\HouseAuthService;
use OCA\Pantry\Service\ShoppingListService;
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
final class ShoppingListController extends OCSController {
	use TranslatesDomainExceptions;

	public function __construct(
		string $appName,
		IRequest $request,
		private ShoppingListService $lists,
		private HouseAuthService $auth,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * List all shopping lists in a house
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
	 * Create a shopping list in a house
	 *
	 * @param int $houseId House id.
	 * @param string $name List name.
	 * @param string|null $description Optional description.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryList, array{}>
	 *
	 * 200: List created
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/lists')]
	#[NoAdminRequired]
	public function createList(int $houseId, string $name, ?string $description = null): DataResponse {
		return $this->runAction(function () use ($houseId, $name, $description): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$list = $this->lists->createList($houseId, $name, $description);
			return new DataResponse($list->jsonSerialize());
		});
	}

	/**
	 * Get a shopping list
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
	 * Update a shopping list
	 *
	 * @param int $houseId House id.
	 * @param int $listId List id.
	 * @param string|null $name New name.
	 * @param string|null $description New description.
	 * @param int|null $sortOrder New sort order.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryList, array{}>
	 *
	 * 200: List updated
	 */
	#[ApiRoute(verb: 'PATCH', url: '/api/houses/{houseId}/lists/{listId}')]
	#[NoAdminRequired]
	public function updateList(int $houseId, int $listId, ?string $name = null, ?string $description = null, ?int $sortOrder = null): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $name, $description, $sortOrder): DataResponse {
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
			if ($sortOrder !== null) {
				$patch['sortOrder'] = $sortOrder;
			}
			$list = $this->lists->updateList($listId, $patch);
			return new DataResponse($list->jsonSerialize());
		});
	}

	/**
	 * Delete a shopping list
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
	 * List items in a shopping list
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
	 * @param string|null $category Optional category label.
	 * @param string|null $quantity Optional quantity string.
	 * @param string|null $rrule Optional RFC 5545 RRULE for recurrence.
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
		?string $category = null,
		?string $quantity = null,
		?string $rrule = null,
		?int $sortOrder = null,
	): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $name, $category, $quantity, $rrule, $sortOrder): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$list = $this->lists->getList($listId);
			$this->assertListInHouse($list->getHouseId(), $houseId);
			$item = $this->lists->addItem($listId, [
				'name' => $name,
				'category' => $category,
				'quantity' => $quantity,
				'rrule' => $rrule,
				'sortOrder' => $sortOrder ?? 0,
			]);
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
	 * @param string|null $category New category (empty string clears).
	 * @param string|null $quantity New quantity (empty string clears).
	 * @param string|null $rrule New RRULE (empty string clears).
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
		?string $category = null,
		?string $quantity = null,
		?string $rrule = null,
		?int $sortOrder = null,
	): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $itemId, $name, $category, $quantity, $rrule, $sortOrder): DataResponse {
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
			if ($category !== null) {
				$patch['category'] = $category;
			}
			if ($quantity !== null) {
				$patch['quantity'] = $quantity;
			}
			if ($rrule !== null) {
				$patch['rrule'] = $rrule;
			}
			if ($sortOrder !== null) {
				$patch['sortOrder'] = $sortOrder;
			}
			$updated = $this->lists->updateItem($itemId, $patch);
			return new DataResponse($updated->jsonSerialize());
		});
	}

	/**
	 * Toggle an item's bought status
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
