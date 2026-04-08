<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Controller;

use OCA\Pantry\Exception\ForbiddenException;
use OCA\Pantry\Exception\NotFoundException;
use OCA\Pantry\ResponseDefinitions;
use OCA\Pantry\Service\HouseAuthService;
use OCA\Pantry\Service\ImageService;
use OCA\Pantry\Service\NotificationService;
use OCA\Pantry\Service\PhotoService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @psalm-import-type PantryPhoto from ResponseDefinitions
 * @psalm-import-type PantryPhotoFolder from ResponseDefinitions
 * @psalm-import-type PantrySuccess from ResponseDefinitions
 */
final class PhotoController extends OCSController {
	use TranslatesDomainExceptions;

	public function __construct(
		string $appName,
		IRequest $request,
		private PhotoService $photos,
		private HouseAuthService $auth,
		private ImageService $images,
		private NotificationService $notifications,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	// ----- Folders -----

	/**
	 * List all photo folders in a house
	 *
	 * @param int $houseId House id.
	 * @param string $sortBy Sort mode (custom, newest, oldest, description_asc, description_desc).
	 * @param int<1, 500> $limit Maximum number of folders to return.
	 * @param int<0, max> $offset Number of folders to skip.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryPhotoFolder>, array{}>
	 *
	 * 200: Folders returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/photos/folders')]
	#[NoAdminRequired]
	public function indexFolders(int $houseId, string $sortBy = 'custom', int $limit = 100, int $offset = 0): DataResponse {
		return $this->runAction(function () use ($houseId, $sortBy, $limit, $offset): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$all = $this->photos->listFolders($houseId, $sortBy);
			$sliced = array_slice($all, max(0, $offset), max(0, $limit));
			return new DataResponse(array_map(fn ($f) => $f->jsonSerialize(), $sliced));
		});
	}

	/**
	 * Create a photo folder
	 *
	 * @param int $houseId House id.
	 * @param string $name Folder name.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryPhotoFolder, array{}>
	 *
	 * 200: Folder created
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/photos/folders')]
	#[NoAdminRequired]
	public function createFolder(int $houseId, string $name): DataResponse {
		return $this->runAction(function () use ($houseId, $name): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$folder = $this->photos->createFolder($houseId, $name);
			return new DataResponse($folder->jsonSerialize());
		});
	}

	/**
	 * Update a photo folder
	 *
	 * @param int $houseId House id.
	 * @param int $folderId Folder id.
	 * @param string|null $name New name.
	 * @param int|null $sortOrder New sort order.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryPhotoFolder, array{}>
	 *
	 * 200: Folder updated
	 */
	#[ApiRoute(verb: 'PATCH', url: '/api/houses/{houseId}/photos/folders/{folderId}')]
	#[NoAdminRequired]
	public function updateFolder(int $houseId, int $folderId, ?string $name = null, ?int $sortOrder = null): DataResponse {
		return $this->runAction(function () use ($houseId, $folderId, $name, $sortOrder): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$existing = $this->photos->getFolder($folderId);
			$this->assertInHouse($existing->getHouseId(), $houseId, 'Folder');
			$patch = [];
			if ($name !== null) {
				$patch['name'] = $name;
			}
			if ($sortOrder !== null) {
				$patch['sortOrder'] = $sortOrder;
			}
			$folder = $this->photos->updateFolder($folderId, $patch);
			return new DataResponse($folder->jsonSerialize());
		});
	}

	/**
	 * Delete a photo folder
	 *
	 * When deleteContents is false (default), photos are moved to the board root.
	 * When true, the folder and all its photos (including files) are permanently deleted.
	 *
	 * @param int $houseId House id.
	 * @param int $folderId Folder id.
	 * @param bool $deleteContents Whether to also delete photos inside the folder.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Folder deleted
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/photos/folders/{folderId}')]
	#[NoAdminRequired]
	public function deleteFolder(int $houseId, int $folderId, bool $deleteContents = false): DataResponse {
		return $this->runAction(function () use ($houseId, $folderId, $deleteContents): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$existing = $this->photos->getFolder($folderId);
			$this->assertInHouse($existing->getHouseId(), $houseId, 'Folder');
			$this->photos->deleteFolder($folderId, $deleteContents, $uid);
			return new DataResponse(['success' => true]);
		});
	}

	/**
	 * Batch reorder folders
	 *
	 * @param int $houseId House id.
	 * @param list<array{id: int, sortOrder: int}> $items Reorder entries.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Folders reordered
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/photos/folders/reorder')]
	#[NoAdminRequired]
	public function reorderFolders(int $houseId, array $items = []): DataResponse {
		return $this->runAction(function () use ($houseId, $items): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->photos->reorderFolders($houseId, $items);
			return new DataResponse(['success' => true]);
		});
	}

	// ----- Photos -----

	/**
	 * List all photos in a house
	 *
	 * @param int $houseId House id.
	 * @param string $sortBy Sort mode (custom, newest, oldest, description_asc, description_desc).
	 * @param int<1, 1000> $limit Maximum number of photos to return.
	 * @param int<0, max> $offset Number of photos to skip.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryPhoto>, array{}>
	 *
	 * 200: Photos returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/photos')]
	#[NoAdminRequired]
	public function indexPhotos(int $houseId, string $sortBy = 'custom', int $limit = 200, int $offset = 0): DataResponse {
		return $this->runAction(function () use ($houseId, $sortBy, $limit, $offset): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$all = $this->photos->listPhotos($houseId, $sortBy);
			$sliced = array_slice($all, max(0, $offset), max(0, $limit));
			return new DataResponse(array_map(fn ($p) => $p->jsonSerialize(), $sliced));
		});
	}

	/**
	 * Upload a photo
	 *
	 * @param int $houseId House id.
	 * @param int|null $folderId Optional folder id to place the photo in.
	 * @param string|null $caption Optional caption.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryPhoto, array{}>
	 *
	 * 200: Photo uploaded
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/photos')]
	#[NoAdminRequired]
	public function uploadPhoto(int $houseId, ?int $folderId = null, ?string $caption = null): DataResponse {
		return $this->runAction(function () use ($houseId, $folderId, $caption): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);

			if ($folderId !== null && $folderId > 0) {
				$folder = $this->photos->getFolder($folderId);
				$this->assertInHouse($folder->getHouseId(), $houseId, 'Folder');
			} else {
				$folderId = null;
			}

			$data = $this->request->getUploadedFile('image');
			if ($data === null || !is_array($data) || ($data['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
				throw new \InvalidArgumentException('No image uploaded');
			}
			$tmp = (string)($data['tmp_name'] ?? '');
			if ($tmp === '' || !is_uploaded_file($tmp)) {
				throw new \InvalidArgumentException('Invalid upload');
			}
			$bytes = file_get_contents($tmp);
			if ($bytes === false) {
				throw new \RuntimeException('Could not read uploaded file');
			}
			$original = (string)($data['name'] ?? 'image.jpg');
			$fileId = $this->images->uploadPhoto($uid, $houseId, $original, $bytes);

			$photo = $this->photos->addPhoto($houseId, $uid, $fileId, $folderId, $caption);
			$this->notifications->notifyPhotoUploaded($houseId, $uid);
			return new DataResponse($photo->jsonSerialize());
		});
	}

	/**
	 * Update a photo
	 *
	 * @param int $houseId House id.
	 * @param int $photoId Photo id.
	 * @param string|null $caption New caption (empty string clears).
	 * @param int|null $folderId New folder id (0 or negative moves to root).
	 * @param int|null $sortOrder New sort order.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryPhoto, array{}>
	 *
	 * 200: Photo updated
	 */
	#[ApiRoute(verb: 'PATCH', url: '/api/houses/{houseId}/photos/{photoId}')]
	#[NoAdminRequired]
	public function updatePhoto(int $houseId, int $photoId, ?string $caption = null, ?int $folderId = null, ?int $sortOrder = null): DataResponse {
		return $this->runAction(function () use ($houseId, $photoId, $caption, $folderId, $sortOrder): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$existing = $this->photos->getPhoto($photoId);
			$this->assertInHouse($existing->getHouseId(), $houseId, 'Photo');

			$patch = [];
			if ($caption !== null) {
				$patch['caption'] = $caption;
			}
			if ($folderId !== null) {
				if ($folderId > 0) {
					$folder = $this->photos->getFolder($folderId);
					$this->assertInHouse($folder->getHouseId(), $houseId, 'Folder');
				}
				$patch['folderId'] = $folderId;
			}
			if ($sortOrder !== null) {
				$patch['sortOrder'] = $sortOrder;
			}
			$photo = $this->photos->updatePhoto($photoId, $patch);
			return new DataResponse($photo->jsonSerialize());
		});
	}

	/**
	 * Delete a photo
	 *
	 * @param int $houseId House id.
	 * @param int $photoId Photo id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Photo deleted
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/photos/{photoId}')]
	#[NoAdminRequired]
	public function deletePhoto(int $houseId, int $photoId): DataResponse {
		return $this->runAction(function () use ($houseId, $photoId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$existing = $this->photos->getPhoto($photoId);
			$this->assertInHouse($existing->getHouseId(), $houseId, 'Photo');
			$this->photos->deletePhoto($photoId, $uid);
			return new DataResponse(['success' => true]);
		});
	}

	/**
	 * Batch reorder photos
	 *
	 * @param int $houseId House id.
	 * @param list<array{id: int, sortOrder: int}> $items Reorder entries.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Photos reordered
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/photos/reorder')]
	#[NoAdminRequired]
	public function reorderPhotos(int $houseId, array $items = []): DataResponse {
		return $this->runAction(function () use ($houseId, $items): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->photos->reorderPhotos($houseId, $items);
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

	private function assertInHouse(int $entityHouseId, int $routeHouseId, string $label): void {
		if ($entityHouseId !== $routeHouseId) {
			throw new NotFoundException($label . ' does not belong to this house');
		}
	}
}
