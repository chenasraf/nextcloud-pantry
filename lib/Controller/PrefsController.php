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
 * @psalm-import-type PantryLastHouse from ResponseDefinitions
 * @psalm-import-type PantryImageFolder from ResponseDefinitions
 * @psalm-import-type PantryNotificationPrefs from ResponseDefinitions
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
	 * Get the current user's last-used house id
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryLastHouse, array{}>
	 *
	 * 200: Last house returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/prefs/last-house')]
	#[NoAdminRequired]
	public function getLastHouse(): DataResponse {
		return $this->runAction(function (): DataResponse {
			$uid = $this->requireUid();
			$houseId = $this->prefs->getLastHouseId($uid);
			// If the saved house is no longer accessible, forget it.
			if ($houseId !== null) {
				try {
					$this->auth->requireMember($houseId, $uid);
				} catch (ForbiddenException) {
					$this->prefs->setLastHouseId($uid, null);
					$houseId = null;
				}
			}
			return new DataResponse(['houseId' => $houseId]);
		});
	}

	/**
	 * Set the current user's last-used house id
	 *
	 * @param int|null $houseId House id, or null to clear.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryLastHouse, array{}>
	 *
	 * 200: Last house updated
	 */
	#[ApiRoute(verb: 'PUT', url: '/api/prefs/last-house')]
	#[NoAdminRequired]
	public function setLastHouse(?int $houseId = null): DataResponse {
		return $this->runAction(function () use ($houseId): DataResponse {
			$uid = $this->requireUid();
			if ($houseId !== null) {
				$this->auth->requireMember($houseId, $uid);
			}
			$this->prefs->setLastHouseId($uid, $houseId);
			return new DataResponse(['houseId' => $houseId]);
		});
	}

	/**
	 * Get the user's preferred image upload folder for a house
	 *
	 * @param int $houseId House id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryImageFolder, array{}>
	 *
	 * 200: Folder returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/prefs/image-folder')]
	#[NoAdminRequired]
	public function getImageFolder(int $houseId): DataResponse {
		return $this->runAction(function () use ($houseId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			return new DataResponse(['folder' => $this->prefs->getImageFolder($uid, $houseId)]);
		});
	}

	/**
	 * Set the user's preferred image upload folder for a house
	 *
	 * @param int $houseId House id.
	 * @param string $folder Absolute path within the user's files.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryImageFolder, array{}>
	 *
	 * 200: Folder updated
	 */
	#[ApiRoute(verb: 'PUT', url: '/api/houses/{houseId}/prefs/image-folder')]
	#[NoAdminRequired]
	public function setImageFolder(int $houseId, string $folder): DataResponse {
		return $this->runAction(function () use ($houseId, $folder): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$stored = $this->prefs->setImageFolder($uid, $houseId, $folder);
			return new DataResponse(['folder' => $stored]);
		});
	}

	/**
	 * Get notification preferences for a house
	 *
	 * @param int $houseId House id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryNotificationPrefs, array{}>
	 *
	 * 200: Prefs returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/prefs/notifications')]
	#[NoAdminRequired]
	public function getNotificationPrefs(int $houseId): DataResponse {
		return $this->runAction(function () use ($houseId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			return new DataResponse($this->prefs->getNotificationPrefs($uid, $houseId));
		});
	}

	/**
	 * Update notification preferences for a house
	 *
	 * @param int $houseId House id.
	 * @param bool|null $notifyPhoto Photo upload notifications.
	 * @param bool|null $notifyNoteCreate Note creation notifications.
	 * @param bool|null $notifyNoteEdit Note edit notifications.
	 * @param bool|null $notifyItemAdd Checklist item added notifications.
	 * @param bool|null $notifyItemRecur Recurring item reappeared notifications.
	 * @param bool|null $notifyItemDone Item completed notifications.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryNotificationPrefs, array{}>
	 *
	 * 200: Prefs updated
	 */
	#[ApiRoute(verb: 'PUT', url: '/api/houses/{houseId}/prefs/notifications')]
	#[NoAdminRequired]
	public function setNotificationPrefs(int $houseId, ?bool $notifyPhoto = null, ?bool $notifyNoteCreate = null, ?bool $notifyNoteEdit = null, ?bool $notifyItemAdd = null, ?bool $notifyItemRecur = null, ?bool $notifyItemDone = null): DataResponse {
		return $this->runAction(function () use ($houseId, $notifyPhoto, $notifyNoteCreate, $notifyNoteEdit, $notifyItemAdd, $notifyItemRecur, $notifyItemDone): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			if ($notifyPhoto !== null) {
				$this->prefs->setNotificationPref($uid, $houseId, 'notify_photo', $notifyPhoto);
			}
			if ($notifyNoteCreate !== null) {
				$this->prefs->setNotificationPref($uid, $houseId, 'notify_note_create', $notifyNoteCreate);
			}
			if ($notifyNoteEdit !== null) {
				$this->prefs->setNotificationPref($uid, $houseId, 'notify_note_edit', $notifyNoteEdit);
			}
			if ($notifyItemAdd !== null) {
				$this->prefs->setNotificationPref($uid, $houseId, 'notify_item_add', $notifyItemAdd);
			}
			if ($notifyItemRecur !== null) {
				$this->prefs->setNotificationPref($uid, $houseId, 'notify_item_recur', $notifyItemRecur);
			}
			if ($notifyItemDone !== null) {
				$this->prefs->setNotificationPref($uid, $houseId, 'notify_item_done', $notifyItemDone);
			}
			return new DataResponse($this->prefs->getNotificationPrefs($uid, $houseId));
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
