<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Controller;

use OCA\Pantry\Db\PhotoMapper;
use OCA\Pantry\Exception\ForbiddenException;
use OCA\Pantry\Exception\NotFoundException;
use OCA\Pantry\Service\HouseAuthService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\OCSController;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IPreview;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Serve images from the owner's storage so any house member can view them,
 * regardless of Nextcloud sharing settings.
 */
final class ImageController extends OCSController {
	use TranslatesDomainExceptions;

	public function __construct(
		string $appName,
		IRequest $request,
		private HouseAuthService $auth,
		private PhotoMapper $photoMapper,
		private IRootFolder $rootFolder,
		private IPreview $previewManager,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Serve a photo board image preview
	 *
	 * @param int $houseId House id.
	 * @param int $photoId Photo record id.
	 * @param int $size Preview size (longest edge).
	 *
	 * @return FileDisplayResponse<Http::STATUS_OK, array{Content-Type: string}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: Preview returned
	 * 404: Image not found
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/photos/{photoId}/preview')]
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function photoPreview(int $houseId, int $photoId, int $size = 300): FileDisplayResponse|DataResponse {
		return $this->runAction(function () use ($houseId, $photoId, $size) {
			$this->auth->requireMember($houseId, $this->requireUid());

			$photo = $this->photoMapper->findById($photoId);
			if ($photo->getHouseId() !== $houseId) {
				throw new NotFoundException('Photo does not belong to this house');
			}

			return $this->servePreview($photo->getUploadedBy(), $photo->getFileId(), $size);
		});
	}

	/**
	 * Serve a checklist item image preview
	 *
	 * @param int $houseId House id.
	 * @param int $fileId Nextcloud file id.
	 * @param string $owner File owner uid.
	 * @param int $size Preview size (longest edge).
	 *
	 * @return FileDisplayResponse<Http::STATUS_OK, array{Content-Type: string}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: Preview returned
	 * 404: Image not found
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/image-preview')]
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function itemImagePreview(int $houseId, int $fileId, string $owner, int $size = 300): FileDisplayResponse|DataResponse {
		return $this->runAction(function () use ($houseId, $fileId, $owner, $size) {
			$this->auth->requireMember($houseId, $this->requireUid());

			return $this->servePreview($owner, $fileId, $size);
		});
	}

	private function servePreview(string $ownerUid, int $fileId, int $size): FileDisplayResponse|DataResponse {
		$size = max(16, min($size, 2048));

		$userFolder = $this->rootFolder->getUserFolder($ownerUid);
		$nodes = $userFolder->getById($fileId);
		if (empty($nodes)) {
			return new DataResponse(['error' => 'File not found'], Http::STATUS_NOT_FOUND);
		}

		$file = $nodes[0];
		if (!$file instanceof File) {
			return new DataResponse(['error' => 'Not a file'], Http::STATUS_NOT_FOUND);
		}

		if ($this->previewManager->isAvailable($file)) {
			$preview = $this->previewManager->getPreview($file, $size, $size);
			$resp = new FileDisplayResponse($preview, Http::STATUS_OK, [
				'Content-Type' => $preview->getMimeType(),
			]);
		} else {
			$resp = new FileDisplayResponse($file, Http::STATUS_OK, [
				'Content-Type' => $file->getMimeType(),
			]);
		}
		$resp->cacheFor(3600);
		return $resp;
	}

	private function requireUid(): string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new ForbiddenException('Not authenticated');
		}
		return $user->getUID();
	}
}
