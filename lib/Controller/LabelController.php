<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Controller;

use OCA\Pantry\Exception\ForbiddenException;
use OCA\Pantry\Permission\Permission;
use OCA\Pantry\ResponseDefinitions;
use OCA\Pantry\Service\HouseAuthService;
use OCA\Pantry\Service\LabelService;
use OCA\Pantry\Service\PrefsService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @psalm-import-type PantryLabel from ResponseDefinitions
 * @psalm-import-type PantrySuccess from ResponseDefinitions
 */
final class LabelController extends OCSController {
	use TranslatesDomainExceptions;

	public function __construct(
		string $appName,
		IRequest $request,
		private LabelService $labels,
		private HouseAuthService $auth,
		private PrefsService $prefs,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * List all labels in a house
	 *
	 * @param int $houseId House id.
	 * @param int<1, 500> $limit Maximum number of labels to return.
	 * @param int<0, max> $offset Number of labels to skip.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryLabel>, array{}>
	 *
	 * 200: Labels returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/labels')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function index(int $houseId, int $limit = 100, int $offset = 0): DataResponse {
		return $this->runAction(function () use ($houseId, $limit, $offset): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$sortBy = $this->prefs->getLabelSort($uid, $houseId);
			$all = $this->labels->listForHouse($houseId, $sortBy);
			$sliced = array_slice($all, max(0, $offset), max(0, $limit));
			return new DataResponse(array_map(fn ($l) => $l->jsonSerialize(), $sliced));
		});
	}

	/**
	 * Create a label
	 *
	 * @param int $houseId House id.
	 * @param string $name Label name.
	 * @param string $icon Icon key from the palette.
	 * @param string $color Hex color (e.g. "#4caf50").
	 * @param int|null $listId List to scope the label to, or null for a global label.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryLabel, array{}>
	 *
	 * 200: Label created
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/labels')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function create(int $houseId, string $name, string $icon, string $color, ?int $listId = null): DataResponse {
		return $this->runAction(function () use ($houseId, $name, $icon, $color, $listId): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$label = $this->labels->create($houseId, $name, $icon, $color, $listId);
			return new DataResponse($label->jsonSerialize());
		});
	}

	/**
	 * Update a label
	 *
	 * @param int $houseId House id.
	 * @param int $labelId Label id.
	 * @param string|null $name New name.
	 * @param string|null $icon New icon key.
	 * @param string|null $color New hex color.
	 * @param int|null $sortOrder New sort order.
	 * @param int|null $listId New list scope, or null for a global label. Only applied when the field is present in the request.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryLabel, array{}>
	 *
	 * 200: Label updated
	 */
	#[ApiRoute(verb: 'PATCH', url: '/api/houses/{houseId}/labels/{labelId}')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function update(
		int $houseId,
		int $labelId,
		?string $name = null,
		?string $icon = null,
		?string $color = null,
		?int $sortOrder = null,
		?int $listId = null,
	): DataResponse {
		// listId is nullable-but-meaningful: an explicit null moves the label to
		// the global scope, so distinguish "field sent" from "field omitted" by
		// the presence of the key rather than by the value being null.
		$listIdProvided = array_key_exists('listId', $this->request->getParams());
		return $this->runAction(function () use ($houseId, $labelId, $name, $icon, $color, $sortOrder, $listId, $listIdProvided): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->labels->assertInHouse($labelId, $houseId);
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
			$updated = $this->labels->update($labelId, $patch);
			return new DataResponse($updated->jsonSerialize());
		});
	}

	/**
	 * Delete a label
	 *
	 * Detaches it from any items that carry it.
	 *
	 * @param int $houseId House id.
	 * @param int $labelId Label id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Label deleted
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/labels/{labelId}')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function destroy(int $houseId, int $labelId): DataResponse {
		return $this->runAction(function () use ($houseId, $labelId): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->labels->assertInHouse($labelId, $houseId);
			$this->labels->delete($labelId);
			return new DataResponse(['success' => true]);
		});
	}

	/**
	 * Batch reorder labels
	 *
	 * @param int $houseId House id.
	 * @param list<array{id: int, sortOrder: int}> $items Reorder entries.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Labels reordered
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/labels/reorder')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function reorder(int $houseId, array $items = []): DataResponse {
		return $this->runAction(function () use ($houseId, $items): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->labels->reorder($houseId, $items);
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
