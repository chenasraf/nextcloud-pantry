<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Controller;

use OCA\Pantry\Exception\ForbiddenException;
use OCA\Pantry\ResponseDefinitions;
use OCA\Pantry\Service\HouseAuthService;
use OCA\Pantry\Service\PrefsService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @psalm-import-type PantryUserPrefs from ResponseDefinitions
 * @psalm-import-type PantryHousePrefs from ResponseDefinitions
 */
final class PrefsController extends OCSController {
	use TranslatesDomainExceptions;

	public function __construct(
		string $appName,
		IRequest $request,
		private PrefsService $prefs,
		private HouseAuthService $auth,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get all user-level preferences (not scoped to a house)
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryUserPrefs, array{}>
	 *
	 * 200: Prefs returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/prefs')]
	#[NoAdminRequired]
	public function getUserPrefs(): DataResponse {
		return $this->runAction(function (): DataResponse {
			$uid = $this->requireUid();
			$prefs = $this->prefs->getAllUserPrefs($uid);
			// If the saved house is no longer accessible, forget it.
			$houseId = $prefs['lastHouseId'] ?? null;
			if ($houseId !== null) {
				try {
					$this->auth->requireMember($houseId, $uid);
				} catch (ForbiddenException) {
					$this->prefs->setLastHouseId($uid, null);
					$prefs['lastHouseId'] = null;
				}
			}
			return new DataResponse($prefs);
		});
	}

	/**
	 * Update user-level preferences
	 *
	 * @param int|null $lastHouseId Last-used house id, or null to clear.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryUserPrefs, array{}>
	 *
	 * 200: Prefs updated
	 */
	#[ApiRoute(verb: 'PUT', url: '/api/prefs')]
	#[NoAdminRequired]
	public function setUserPrefs(?int $lastHouseId = null): DataResponse {
		return $this->runAction(function () use ($lastHouseId): DataResponse {
			$uid = $this->requireUid();
			$patch = [];
			if ($lastHouseId !== null) {
				$this->auth->requireMember($lastHouseId, $uid);
				$patch['lastHouseId'] = $lastHouseId;
			} else {
				// Explicit null means clear
				$patch['lastHouseId'] = null;
			}
			$this->prefs->setUserPrefs($uid, $patch);
			return new DataResponse($this->prefs->getAllUserPrefs($uid));
		});
	}

	/**
	 * Get all per-house preferences for the current user
	 *
	 * @param int $houseId House id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryHousePrefs, array{}>
	 *
	 * 200: Prefs returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/prefs')]
	#[NoAdminRequired]
	public function getHousePrefs(int $houseId): DataResponse {
		return $this->runAction(function () use ($houseId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			return new DataResponse($this->prefs->getAllHousePrefs($uid, $houseId));
		});
	}

	/**
	 * Update per-house preferences for the current user
	 *
	 * Only the fields present in the request body are updated; omitted fields
	 * are left unchanged.
	 *
	 * @param int $houseId House id.
	 * @param string|null $imageFolder Image upload folder path.
	 * @param string|null $photoSort Photo sort mode.
	 * @param bool|null $photoFoldersFirst Whether folders appear first in photo board.
	 * @param string|null $noteSort Note sort mode.
	 * @param string|null $checklistItemSort Checklist item sort mode.
	 * @param bool|null $notifyPhoto Photo upload notifications.
	 * @param bool|null $notifyNoteCreate Note creation notifications.
	 * @param bool|null $notifyNoteEdit Note edit notifications.
	 * @param bool|null $notifyItemAdd Checklist item added notifications.
	 * @param bool|null $notifyItemRecur Recurring item reappeared notifications.
	 * @param bool|null $notifyItemDone Item completed notifications.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryHousePrefs, array{}>
	 *
	 * 200: Prefs updated
	 */
	#[ApiRoute(verb: 'PUT', url: '/api/houses/{houseId}/prefs')]
	#[NoAdminRequired]
	public function setHousePrefs(
		int $houseId,
		?string $imageFolder = null,
		?string $photoSort = null,
		?bool $photoFoldersFirst = null,
		?string $noteSort = null,
		?string $checklistItemSort = null,
		?bool $notifyPhoto = null,
		?bool $notifyNoteCreate = null,
		?bool $notifyNoteEdit = null,
		?bool $notifyItemAdd = null,
		?bool $notifyItemRecur = null,
		?bool $notifyItemDone = null,
	): DataResponse {
		return $this->runAction(function () use ($houseId, $imageFolder, $photoSort, $photoFoldersFirst, $noteSort, $checklistItemSort, $notifyPhoto, $notifyNoteCreate, $notifyNoteEdit, $notifyItemAdd, $notifyItemRecur, $notifyItemDone): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$patch = array_filter([
				'imageFolder' => $imageFolder,
				'photoSort' => $photoSort,
				'photoFoldersFirst' => $photoFoldersFirst,
				'noteSort' => $noteSort,
				'checklistItemSort' => $checklistItemSort,
				'notifyPhoto' => $notifyPhoto,
				'notifyNoteCreate' => $notifyNoteCreate,
				'notifyNoteEdit' => $notifyNoteEdit,
				'notifyItemAdd' => $notifyItemAdd,
				'notifyItemRecur' => $notifyItemRecur,
				'notifyItemDone' => $notifyItemDone,
			], fn ($v) => $v !== null);
			$this->prefs->setHousePrefs($uid, $houseId, $patch);
			return new DataResponse($this->prefs->getAllHousePrefs($uid, $houseId));
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
