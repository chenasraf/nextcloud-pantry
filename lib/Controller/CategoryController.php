<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Controller;

use OCA\Pantry\Exception\ForbiddenException;
use OCA\Pantry\ResponseDefinitions;
use OCA\Pantry\Service\CategoryService;
use OCA\Pantry\Service\HouseAuthService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @psalm-import-type PantryCategory from ResponseDefinitions
 * @psalm-import-type PantrySuccess from ResponseDefinitions
 */
final class CategoryController extends OCSController {
	use TranslatesDomainExceptions;

	public function __construct(
		string $appName,
		IRequest $request,
		private CategoryService $categories,
		private HouseAuthService $auth,
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
	public function index(int $houseId, int $limit = 100, int $offset = 0): DataResponse {
		return $this->runAction(function () use ($houseId, $limit, $offset): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$all = $this->categories->listForHouse($houseId);
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
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryCategory, array{}>
	 *
	 * 200: Category created
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/categories')]
	#[NoAdminRequired]
	public function create(int $houseId, string $name, string $icon, string $color): DataResponse {
		return $this->runAction(function () use ($houseId, $name, $icon, $color): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$cat = $this->categories->create($houseId, $name, $icon, $color);
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
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryCategory, array{}>
	 *
	 * 200: Category updated
	 */
	#[ApiRoute(verb: 'PATCH', url: '/api/houses/{houseId}/categories/{categoryId}')]
	#[NoAdminRequired]
	public function update(
		int $houseId,
		int $categoryId,
		?string $name = null,
		?string $icon = null,
		?string $color = null,
		?int $sortOrder = null,
	): DataResponse {
		return $this->runAction(function () use ($houseId, $categoryId, $name, $icon, $color, $sortOrder): DataResponse {
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
	public function destroy(int $houseId, int $categoryId): DataResponse {
		return $this->runAction(function () use ($houseId, $categoryId): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->categories->assertInHouse($categoryId, $houseId);
			$this->categories->delete($categoryId);
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
