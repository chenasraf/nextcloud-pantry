<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Controller;

use OCA\Pantry\Exception\ForbiddenException;
use OCA\Pantry\Permission\Permission;
use OCA\Pantry\ResponseDefinitions;
use OCA\Pantry\Service\HouseAuthService;
use OCA\Pantry\Service\StoreService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @psalm-import-type PantryStore from ResponseDefinitions
 * @psalm-import-type PantrySuccess from ResponseDefinitions
 */
final class StoreController extends OCSController {
	use TranslatesDomainExceptions;

	public function __construct(
		string $appName,
		IRequest $request,
		private StoreService $stores,
		private HouseAuthService $auth,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * List all stores in a house
	 *
	 * @param int $houseId House id.
	 * @param int<1, 500> $limit Maximum number of stores to return.
	 * @param int<0, max> $offset Number of stores to skip.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryStore>, array{}>
	 *
	 * 200: Stores returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/stores')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function index(int $houseId, int $limit = 100, int $offset = 0): DataResponse {
		return $this->runAction(function () use ($houseId, $limit, $offset): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$all = $this->stores->listForHouse($houseId);
			$sliced = array_slice($all, max(0, $offset), max(0, $limit));
			return new DataResponse(array_map(fn ($s) => $s->jsonSerialize(), $sliced));
		});
	}

	/**
	 * Create a store
	 *
	 * @param int $houseId House id.
	 * @param string $name Store name.
	 * @param string $icon Icon key from the palette.
	 * @param string $color Hex color (e.g. "#4caf50").
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryStore, array{}>
	 *
	 * 200: Store created
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/stores')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function create(int $houseId, string $name, string $icon, string $color): DataResponse {
		return $this->runAction(function () use ($houseId, $name, $icon, $color): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$store = $this->stores->create($houseId, $name, $icon, $color);
			return new DataResponse($store->jsonSerialize());
		});
	}

	/**
	 * Update a store
	 *
	 * @param int $houseId House id.
	 * @param int $storeId Store id.
	 * @param string|null $name New name.
	 * @param string|null $icon New icon key.
	 * @param string|null $color New hex color.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryStore, array{}>
	 *
	 * 200: Store updated
	 */
	#[ApiRoute(verb: 'PATCH', url: '/api/houses/{houseId}/stores/{storeId}')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function update(
		int $houseId,
		int $storeId,
		?string $name = null,
		?string $icon = null,
		?string $color = null,
	): DataResponse {
		return $this->runAction(function () use ($houseId, $storeId, $name, $icon, $color): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->stores->assertInHouse($storeId, $houseId);
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
			$updated = $this->stores->update($storeId, $patch);
			return new DataResponse($updated->jsonSerialize());
		});
	}

	/**
	 * Delete a store
	 *
	 * Detaches it from any items that reference it.
	 *
	 * @param int $houseId House id.
	 * @param int $storeId Store id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Store deleted
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/stores/{storeId}')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function destroy(int $houseId, int $storeId): DataResponse {
		return $this->runAction(function () use ($houseId, $storeId): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->stores->assertInHouse($storeId, $houseId);
			$this->stores->delete($storeId);
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
