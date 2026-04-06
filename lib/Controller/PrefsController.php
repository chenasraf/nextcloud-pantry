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
	 * Get the user's preferred image upload folder
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryImageFolder, array{}>
	 *
	 * 200: Folder returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/prefs/image-folder')]
	#[NoAdminRequired]
	public function getImageFolder(): DataResponse {
		return $this->runAction(function (): DataResponse {
			$uid = $this->requireUid();
			return new DataResponse(['folder' => $this->prefs->getImageFolder($uid)]);
		});
	}

	/**
	 * Set the user's preferred image upload folder
	 *
	 * @param string $folder Absolute path within the user's files.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryImageFolder, array{}>
	 *
	 * 200: Folder updated
	 */
	#[ApiRoute(verb: 'PUT', url: '/api/prefs/image-folder')]
	#[NoAdminRequired]
	public function setImageFolder(string $folder): DataResponse {
		return $this->runAction(function () use ($folder): DataResponse {
			$uid = $this->requireUid();
			$stored = $this->prefs->setImageFolder($uid, $folder);
			return new DataResponse(['folder' => $stored]);
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
