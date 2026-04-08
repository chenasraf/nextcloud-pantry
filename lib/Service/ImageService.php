<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;

class ImageService {
	public const CHECKLIST_ITEMS_SUBDIR = 'Checklist items';
	public const PHOTOS_SUBDIR = 'Photo board';

	public function __construct(
		private IRootFolder $rootFolder,
		private PrefsService $prefs,
	) {
	}

	/**
	 * Upload image bytes to the user's configured pantry image folder, returning
	 * the Nextcloud file id on success.
	 */
	public function uploadForUser(string $uid, int $houseId, string $originalName, string $data): int {
		if ($data === '') {
			throw new \InvalidArgumentException('Empty file');
		}
		$folder = $this->resolveChecklistItemsFolder($uid, $houseId);
		$filename = $this->uniqueName($folder, $originalName);
		try {
			$file = $folder->newFile($filename, $data);
		} catch (NotPermittedException $e) {
			throw new \RuntimeException('Could not write file: ' . $e->getMessage(), 0, $e);
		}
		return $file->getId();
	}

	/**
	 * Upload image bytes to the user's configured pantry image folder under the
	 * "Photo board" subdirectory, returning the Nextcloud file id on success.
	 */
	public function uploadPhoto(string $uid, int $houseId, string $originalName, string $data): int {
		if ($data === '') {
			throw new \InvalidArgumentException('Empty file');
		}
		$folder = $this->resolvePhotoFolder($uid, $houseId);
		$filename = $this->uniqueName($folder, $originalName);
		try {
			$file = $folder->newFile($filename, $data);
		} catch (NotPermittedException $e) {
			throw new \RuntimeException('Could not write file: ' . $e->getMessage(), 0, $e);
		}
		return $file->getId();
	}

	/**
	 * Delete a file by its Nextcloud file id.
	 *
	 * Silently does nothing if the file does not exist or is not accessible.
	 */
	public function deleteFile(int $fileId, string $uid): void {
		$userFolder = $this->rootFolder->getUserFolder($uid);
		$nodes = $userFolder->getById($fileId);
		foreach ($nodes as $node) {
			try {
				$node->delete();
			} catch (\Throwable) {
				// Best-effort — file may have been removed already.
			}
			break; // Only need to delete once.
		}
	}

	private function resolvePhotoFolder(string $uid, int $houseId): Folder {
		$base = $this->resolveBaseFolder($uid, $houseId);
		return $this->getOrCreateSubFolder($base, self::PHOTOS_SUBDIR);
	}

	private function resolveChecklistItemsFolder(string $uid, int $houseId): Folder {
		$base = $this->resolveBaseFolder($uid, $houseId);
		return $this->getOrCreateSubFolder($base, self::CHECKLIST_ITEMS_SUBDIR);
	}

	private function resolveBaseFolder(string $uid, int $houseId): Folder {
		$userFolder = $this->rootFolder->getUserFolder($uid);
		$path = $this->prefs->getImageFolder($uid, $houseId);
		$relative = ltrim($path, '/');
		if ($relative === '') {
			return $userFolder;
		}
		if ($userFolder->nodeExists($relative)) {
			$node = $userFolder->get($relative);
			if (!$node instanceof Folder) {
				throw new \RuntimeException('Configured image path is not a folder: ' . $path);
			}
			return $node;
		}
		return $userFolder->newFolder($relative);
	}

	private function getOrCreateSubFolder(Folder $parent, string $name): Folder {
		if ($parent->nodeExists($name)) {
			$node = $parent->get($name);
			if (!$node instanceof Folder) {
				throw new \RuntimeException('Expected a folder at ' . $name);
			}
			return $node;
		}
		return $parent->newFolder($name);
	}

	private function uniqueName(Folder $folder, string $original): string {
		$base = basename($original);
		if ($base === '' || $base === '.' || $base === '..') {
			$base = 'image.jpg';
		}
		// Strip characters Nextcloud disallows in filenames.
		$base = preg_replace('/[\/\\\\]/', '_', $base) ?? 'image.jpg';
		$dot = strrpos($base, '.');
		$name = $dot === false ? $base : substr($base, 0, $dot);
		$ext = $dot === false ? '' : substr($base, $dot);
		$candidate = $base;
		$i = 1;
		while ($folder->nodeExists($candidate)) {
			$candidate = sprintf('%s (%d)%s', $name, $i, $ext);
			$i++;
		}
		return $candidate;
	}
}
