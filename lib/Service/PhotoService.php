<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Db\Photo;
use OCA\Pantry\Db\PhotoFolder;
use OCA\Pantry\Db\PhotoFolderMapper;
use OCA\Pantry\Db\PhotoMapper;
use OCA\Pantry\Exception\NotFoundException;
use OCP\AppFramework\Db\DoesNotExistException;

class PhotoService {
	public function __construct(
		private PhotoMapper $photoMapper,
		private PhotoFolderMapper $folderMapper,
	) {
	}

	// ----- Folders -----

	/**
	 * @return PhotoFolder[]
	 */
	public function listFolders(int $houseId): array {
		return $this->folderMapper->findByHouse($houseId);
	}

	public function getFolder(int $folderId): PhotoFolder {
		try {
			return $this->folderMapper->findById($folderId);
		} catch (DoesNotExistException) {
			throw new NotFoundException('Folder not found');
		}
	}

	public function createFolder(int $houseId, string $name): PhotoFolder {
		$name = trim($name);
		if ($name === '') {
			throw new \InvalidArgumentException('Folder name cannot be empty');
		}
		$now = time();
		$folder = new PhotoFolder();
		$folder->setHouseId($houseId);
		$folder->setName($name);
		$folder->setSortOrder(0);
		$folder->setCreatedAt($now);
		$folder->setUpdatedAt($now);
		/** @var PhotoFolder $saved */
		$saved = $this->folderMapper->insert($folder);
		return $saved;
	}

	public function updateFolder(int $folderId, array $patch): PhotoFolder {
		$folder = $this->getFolder($folderId);
		if (isset($patch['name'])) {
			$name = trim((string)$patch['name']);
			if ($name === '') {
				throw new \InvalidArgumentException('Folder name cannot be empty');
			}
			$folder->setName($name);
		}
		if (isset($patch['sortOrder'])) {
			$folder->setSortOrder((int)$patch['sortOrder']);
		}
		$folder->setUpdatedAt(time());
		$this->folderMapper->update($folder);
		return $folder;
	}

	public function deleteFolder(int $folderId): void {
		$folder = $this->getFolder($folderId);
		// Move all photos in this folder to the board root
		$this->photoMapper->moveToRoot($folderId);
		$this->folderMapper->delete($folder);
	}

	/**
	 * Batch reorder folders.
	 *
	 * @param array<array{id: int, sortOrder: int}> $items
	 */
	public function reorderFolders(int $houseId, array $items): void {
		foreach ($items as $entry) {
			$id = (int)($entry['id'] ?? 0);
			$sortOrder = (int)($entry['sortOrder'] ?? 0);
			if ($id <= 0) {
				continue;
			}
			try {
				$folder = $this->folderMapper->findById($id);
			} catch (DoesNotExistException) {
				continue;
			}
			if ($folder->getHouseId() !== $houseId) {
				continue;
			}
			$folder->setSortOrder($sortOrder);
			$folder->setUpdatedAt(time());
			$this->folderMapper->update($folder);
		}
	}

	// ----- Photos -----

	/**
	 * @return Photo[]
	 */
	public function listPhotos(int $houseId): array {
		return $this->photoMapper->findByHouse($houseId);
	}

	/**
	 * @return Photo[]
	 */
	public function listPhotosByFolder(int $folderId): array {
		return $this->photoMapper->findByFolder($folderId);
	}

	public function getPhoto(int $photoId): Photo {
		try {
			return $this->photoMapper->findById($photoId);
		} catch (DoesNotExistException) {
			throw new NotFoundException('Photo not found');
		}
	}

	public function addPhoto(int $houseId, string $uid, int $fileId, ?int $folderId, ?string $caption): Photo {
		$now = time();
		$photo = new Photo();
		$photo->setHouseId($houseId);
		$photo->setFolderId($folderId);
		$photo->setFileId($fileId);
		$photo->setCaption($caption !== null && trim($caption) !== '' ? trim($caption) : null);
		$photo->setUploadedBy($uid);
		$photo->setSortOrder(0);
		$photo->setCreatedAt($now);
		$photo->setUpdatedAt($now);
		/** @var Photo $saved */
		$saved = $this->photoMapper->insert($photo);
		return $saved;
	}

	public function updatePhoto(int $photoId, array $patch): Photo {
		$photo = $this->getPhoto($photoId);
		if (array_key_exists('caption', $patch)) {
			$caption = $patch['caption'];
			$photo->setCaption(is_string($caption) && trim($caption) !== '' ? trim($caption) : null);
		}
		if (array_key_exists('folderId', $patch)) {
			$fid = $patch['folderId'];
			$photo->setFolderId(is_int($fid) && $fid > 0 ? $fid : null);
		}
		if (isset($patch['sortOrder'])) {
			$photo->setSortOrder((int)$patch['sortOrder']);
		}
		$photo->setUpdatedAt(time());
		$this->photoMapper->update($photo);
		return $photo;
	}

	public function deletePhoto(int $photoId): void {
		$photo = $this->getPhoto($photoId);
		$this->photoMapper->delete($photo);
	}

	/**
	 * Batch reorder photos.
	 *
	 * @param array<array{id: int, sortOrder: int}> $items
	 */
	public function reorderPhotos(int $houseId, array $items): void {
		foreach ($items as $entry) {
			$id = (int)($entry['id'] ?? 0);
			$sortOrder = (int)($entry['sortOrder'] ?? 0);
			if ($id <= 0) {
				continue;
			}
			try {
				$photo = $this->photoMapper->findById($id);
			} catch (DoesNotExistException) {
				continue;
			}
			if ($photo->getHouseId() !== $houseId) {
				continue;
			}
			$photo->setSortOrder($sortOrder);
			$photo->setUpdatedAt(time());
			$this->photoMapper->update($photo);
		}
	}
}
