<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Controller;

use OCA\Pantry\Exception\ForbiddenException;
use OCA\Pantry\Permission\Permission;
use OCA\Pantry\ResponseDefinitions;
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
		private PrefsService $prefs,
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
			$sortBy = $this->prefs->getStoreSort($uid, $houseId);
			$all = $this->stores->listForHouse($houseId, $sortBy);
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
	 * @param string|null $brand Brand or chain name (e.g. "Walmart").
	 * @param string|null $location Store address or location.
	 * @param list<array{day: int, start: string, end: string}>|null $openingHours Opening-hours intervals.
	 * @param string|null $contact Phone, social or other contact details.
	 * @param string|null $responsible Person responsible for the store.
	 * @param string|null $notes Free-form notes.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryStore, array{}>
	 *
	 * 200: Store created
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/stores')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function create(
		int $houseId,
		string $name,
		string $icon,
		string $color,
		?string $brand = null,
		?string $location = null,
		?array $openingHours = null,
		?string $contact = null,
		?string $responsible = null,
		?string $notes = null,
	): DataResponse {
		return $this->runAction(function () use ($houseId, $name, $icon, $color, $brand, $location, $openingHours, $contact, $responsible, $notes): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$info = $this->collectInfo($brand, $location, $openingHours, $contact, $responsible, $notes);
			$store = $this->stores->create($houseId, $name, $icon, $color, $info);
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
	 * @param string|null $brand Brand or chain name (e.g. "Walmart"). Empty string clears it.
	 * @param string|null $location Store address or location. Empty string clears it.
	 * @param list<array{day: int, start: string, end: string}>|null $openingHours Opening-hours intervals. Empty list clears them.
	 * @param string|null $contact Phone, social or other contact details. Empty string clears it.
	 * @param string|null $responsible Person responsible for the store. Empty string clears it.
	 * @param string|null $notes Free-form notes. Empty string clears it.
	 * @param int|null $sortOrder New sort order.
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
		?string $brand = null,
		?string $location = null,
		?array $openingHours = null,
		?string $contact = null,
		?string $responsible = null,
		?string $notes = null,
		?int $sortOrder = null,
	): DataResponse {
		return $this->runAction(function () use ($houseId, $storeId, $name, $icon, $color, $brand, $location, $openingHours, $contact, $responsible, $notes, $sortOrder): DataResponse {
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
			if ($sortOrder !== null) {
				$patch['sortOrder'] = $sortOrder;
			}
			$patch += $this->collectInfo($brand, $location, $openingHours, $contact, $responsible, $notes);
			$updated = $this->stores->update($storeId, $patch);
			return new DataResponse($updated->jsonSerialize());
		});
	}

	/**
	 * Builds a partial info-fields map, including only the fields that were provided
	 * (non-null). Empty strings/arrays are kept so the service can clear the field.
	 *
	 * @param list<array{day: int, start: string, end: string}>|null $openingHours
	 *
	 * @return array<string, mixed>
	 */
	private function collectInfo(
		?string $brand,
		?string $location,
		?array $openingHours,
		?string $contact,
		?string $responsible,
		?string $notes,
	): array {
		$info = [];
		if ($brand !== null) {
			$info['brand'] = $brand;
		}
		if ($location !== null) {
			$info['location'] = $location;
		}
		if ($openingHours !== null) {
			$info['openingHours'] = $openingHours;
		}
		if ($contact !== null) {
			$info['contact'] = $contact;
		}
		if ($responsible !== null) {
			$info['responsible'] = $responsible;
		}
		if ($notes !== null) {
			$info['notes'] = $notes;
		}
		return $info;
	}

	/**
	 * Batch reorder stores
	 *
	 * @param int $houseId House id.
	 * @param list<array{id: int, sortOrder: int}> $items Reorder entries.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Stores reordered
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/stores/reorder')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function reorder(int $houseId, array $items = []): DataResponse {
		return $this->runAction(function () use ($houseId, $items): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->stores->reorder($houseId, $items);
			return new DataResponse(['success' => true]);
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
