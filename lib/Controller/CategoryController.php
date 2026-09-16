<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Controller;

use OCA\Pantry\Exception\ForbiddenException;
use OCA\Pantry\Permission\Permission;
use OCA\Pantry\ResponseDefinitions;
use OCA\Pantry\Service\CategoryService;
use OCA\Pantry\Service\HouseAuthService;
use OCA\Pantry\Service\PrefsService;
use OCA\Pantry\Service\StoreService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @psalm-import-type PantryCategory from ResponseDefinitions
 * @psalm-import-type PantryStoreCategoryOrder from ResponseDefinitions
 * @psalm-import-type PantrySuccess from ResponseDefinitions
 */
final class CategoryController extends OCSController {
	use TranslatesDomainExceptions;

	public function __construct(
		string $appName,
		IRequest $request,
		private CategoryService $categories,
		private StoreService $stores,
		private HouseAuthService $auth,
		private PrefsService $prefs,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * List all categories in a house
	 *
	 * @param int $houseId House id.
	 * @param int<1, 500> $limit Maximum number of categories to return.
	 * @param int<0, max> $offset Number of categories to skip.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryCategory>, array{}>
	 *
	 * 200: Categories returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/categories')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function index(int $houseId, int $limit = 100, int $offset = 0): DataResponse {
		return $this->runAction(function () use ($houseId, $limit, $offset): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$sortBy = $this->prefs->getCategorySort($uid, $houseId);
			$all = $this->categories->listForHouse($houseId, $sortBy);
			$sliced = array_slice($all, max(0, $offset), max(0, $limit));
			return new DataResponse(array_map(fn ($c) => $c->jsonSerialize(), $sliced));
		});
	}

	/**
	 * Create a category
	 *
	 * @param int $houseId House id.
	 * @param string $name Category name.
	 * @param string $icon Icon key from the palette.
	 * @param string $color Hex color (e.g. "#4caf50").
	 * @param int|null $listId List to scope the category to, or null for a global category.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryCategory, array{}>
	 *
	 * 200: Category created
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/categories')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function create(int $houseId, string $name, string $icon, string $color, ?int $listId = null): DataResponse {
		return $this->runAction(function () use ($houseId, $name, $icon, $color, $listId): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$cat = $this->categories->create($houseId, $name, $icon, $color, $listId);
			return new DataResponse($cat->jsonSerialize());
		});
	}

	/**
	 * Update a category
	 *
	 * @param int $houseId House id.
	 * @param int $categoryId Category id.
	 * @param string|null $name New name.
	 * @param string|null $icon New icon key.
	 * @param string|null $color New hex color.
	 * @param int|null $sortOrder New sort order.
	 * @param int|null $listId New list scope, or null for a global category. Only applied when the field is present in the request.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryCategory, array{}>
	 *
	 * 200: Category updated
	 */
	#[ApiRoute(verb: 'PATCH', url: '/api/houses/{houseId}/categories/{categoryId}')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function update(
		int $houseId,
		int $categoryId,
		?string $name = null,
		?string $icon = null,
		?string $color = null,
		?int $sortOrder = null,
		?int $listId = null,
	): DataResponse {
		// listId is nullable-but-meaningful: an explicit null moves the category
		// to the global scope, so distinguish "field sent" from "field omitted"
		// by the presence of the key rather than by the value being null.
		$listIdProvided = array_key_exists('listId', $this->request->getParams());
		return $this->runAction(function () use ($houseId, $categoryId, $name, $icon, $color, $sortOrder, $listId, $listIdProvided): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->categories->assertInHouse($categoryId, $houseId);
			$patch = [];
			if ($name !== null) {
				$patch['name'] = $name;
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
			if ($listIdProvided) {
				$patch['listId'] = $listId;
			}
			$updated = $this->categories->update($categoryId, $patch);
			return new DataResponse($updated->jsonSerialize());
		});
	}

	/**
	 * Delete a category
	 *
	 * Detaches any items that reference it.
	 *
	 * @param int $houseId House id.
	 * @param int $categoryId Category id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Category deleted
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/categories/{categoryId}')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function destroy(int $houseId, int $categoryId): DataResponse {
		return $this->runAction(function () use ($houseId, $categoryId): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->categories->assertInHouse($categoryId, $houseId);
			$this->categories->delete($categoryId);
			return new DataResponse(['success' => true]);
		});
	}

	/**
	 * Batch reorder categories
	 *
	 * @param int $houseId House id.
	 * @param list<array{id: int, sortOrder: int}> $items Reorder entries.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Categories reordered
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/categories/reorder')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function reorder(int $houseId, array $items = []): DataResponse {
		return $this->runAction(function () use ($houseId, $items): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->categories->reorder($houseId, $items);
			return new DataResponse(['success' => true]);
		});
	}

	/**
	 * The category order a store is shopped in
	 *
	 * Lists only the categories the store arranges, in its own order. Every
	 * other category trails them in the house-wide order, so an empty list
	 * means the store follows the house order throughout.
	 *
	 * @param int $houseId House id.
	 * @param int $storeId Store id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryStoreCategoryOrder, array{}>
	 *
	 * 200: Store category order returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/stores/{storeId}/category-order')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function storeOrder(int $houseId, int $storeId): DataResponse {
		return $this->runAction(function () use ($houseId, $storeId): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->stores->assertInHouse($storeId, $houseId);
			return new DataResponse([
				'storeId' => $storeId,
				'categoryIds' => $this->categories->orderForStore($houseId, $storeId),
			]);
		});
	}

	/**
	 * Arrange a store's categories
	 *
	 * Replaces the store's whole arrangement. Ids that do not name a category
	 * of this house are dropped; an empty list returns the store to the
	 * house-wide order.
	 *
	 * @param int $houseId House id.
	 * @param int $storeId Store id.
	 * @param list<int> $categoryIds Category ids in the order the store is walked.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryStoreCategoryOrder, array{}>
	 *
	 * 200: Store category order saved
	 */
	#[ApiRoute(verb: 'PUT', url: '/api/houses/{houseId}/stores/{storeId}/category-order')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function setStoreOrder(int $houseId, int $storeId, array $categoryIds = []): DataResponse {
		return $this->runAction(function () use ($houseId, $storeId, $categoryIds): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->stores->assertInHouse($storeId, $houseId);
			$stored = $this->categories->setOrderForStore($houseId, $storeId, array_values($categoryIds));
			return new DataResponse(['storeId' => $storeId, 'categoryIds' => $stored]);
		});
	}

	/**
	 * Drop a store's category arrangement
	 *
	 * The store falls back to the house-wide category order.
	 *
	 * @param int $houseId House id.
	 * @param int $storeId Store id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Store category order cleared
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/stores/{storeId}/category-order')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function clearStoreOrder(int $houseId, int $storeId): DataResponse {
		return $this->runAction(function () use ($houseId, $storeId): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->stores->assertInHouse($storeId, $houseId);
			$this->categories->clearOrderForStore($storeId);
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
}
