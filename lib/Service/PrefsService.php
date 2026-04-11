<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\AppInfo\Application;
use OCP\IConfig;
use OCP\IL10N;

class PrefsService {
	private const KEY_LAST_HOUSE = 'last_house_id';
	private const KEY_IMAGE_FOLDER = 'image_folder';
	public const DEFAULT_IMAGE_FOLDER = '/Pantry';

	public function __construct(
		private IConfig $config,
		private IL10N $l,
	) {
	}

	public function getFirstDayOfWeek(string $uid): int {
		$value = $this->config->getUserValue($uid, 'core', 'first_day_of_week', '');
		if ($value === '') {
			return (int)$this->l->l('firstday', null);
		}
		return (int)$value;
	}

	public function getLastHouseId(string $uid): ?int {
		$value = $this->config->getUserValue($uid, Application::APP_ID, self::KEY_LAST_HOUSE, '');
		if ($value === '') {
			return null;
		}
		return (int)$value;
	}

	public function setLastHouseId(string $uid, ?int $houseId): void {
		if ($houseId === null) {
			$this->config->deleteUserValue($uid, Application::APP_ID, self::KEY_LAST_HOUSE);
			return;
		}
		$this->config->setUserValue($uid, Application::APP_ID, self::KEY_LAST_HOUSE, (string)$houseId);
	}

	public function getImageFolder(string $uid, int $houseId): string {
		$value = $this->config->getUserValue(
			$uid,
			Application::APP_ID,
			self::KEY_IMAGE_FOLDER . '_' . $houseId,
			self::DEFAULT_IMAGE_FOLDER,
		);
		return $this->normalizeFolder($value);
	}

	public function setImageFolder(string $uid, int $houseId, string $folder): string {
		$normalized = $this->normalizeFolder($folder);
		$this->config->setUserValue($uid, Application::APP_ID, self::KEY_IMAGE_FOLDER . '_' . $houseId, $normalized);
		return $normalized;
	}

	// ----- Unified user prefs -----

	/**
	 * @return array<string, mixed>
	 */
	public function getAllUserPrefs(string $uid): array {
		return [
			'lastHouseId' => $this->getLastHouseId($uid),
			'firstDayOfWeek' => $this->getFirstDayOfWeek($uid),
		];
	}

	/**
	 * @param array<string, mixed> $patch
	 */
	public function setUserPrefs(string $uid, array $patch): void {
		if (array_key_exists('lastHouseId', $patch)) {
			$v = $patch['lastHouseId'];
			$this->setLastHouseId($uid, is_int($v) ? $v : null);
		}
	}

	// ----- Sort preferences -----

	private const KEY_PHOTO_SORT = 'photo_sort';
	private const KEY_NOTE_SORT = 'note_sort';

	public function getPhotoSort(string $uid, int $houseId): string {
		return $this->config->getUserValue(
			$uid,
			Application::APP_ID,
			self::KEY_PHOTO_SORT . '_' . $houseId,
			'custom',
		);
	}

	public function setPhotoSort(string $uid, int $houseId, string $sort): string {
		$allowed = ['custom', 'newest', 'oldest', 'description_asc', 'description_desc'];
		if (!in_array($sort, $allowed, true)) {
			$sort = 'custom';
		}
		$this->config->setUserValue($uid, Application::APP_ID, self::KEY_PHOTO_SORT . '_' . $houseId, $sort);
		return $sort;
	}

	public function getPhotoFoldersFirst(string $uid, int $houseId): bool {
		return $this->config->getUserValue(
			$uid,
			Application::APP_ID,
			'photo_folders_first_' . $houseId,
			'1',
		) === '1';
	}

	public function setPhotoFoldersFirst(string $uid, int $houseId, bool $value): bool {
		$this->config->setUserValue($uid, Application::APP_ID, 'photo_folders_first_' . $houseId, $value ? '1' : '0');
		return $value;
	}

	public function getNoteSort(string $uid, int $houseId): string {
		return $this->config->getUserValue(
			$uid,
			Application::APP_ID,
			self::KEY_NOTE_SORT . '_' . $houseId,
			'custom',
		);
	}

	public function setNoteSort(string $uid, int $houseId, string $sort): string {
		$allowed = ['custom', 'newest', 'oldest', 'title_asc', 'title_desc'];
		if (!in_array($sort, $allowed, true)) {
			$sort = 'custom';
		}
		$this->config->setUserValue($uid, Application::APP_ID, self::KEY_NOTE_SORT . '_' . $houseId, $sort);
		return $sort;
	}

	// ----- Checklist item sort preferences -----

	private const KEY_CHECKLIST_ITEM_SORT = 'checklist_item_sort';

	public function getChecklistItemSort(string $uid, int $houseId): string {
		return $this->config->getUserValue(
			$uid,
			Application::APP_ID,
			self::KEY_CHECKLIST_ITEM_SORT . '_' . $houseId,
			'custom',
		);
	}

	public function setChecklistItemSort(string $uid, int $houseId, string $sort): string {
		$allowed = ['custom', 'newest', 'oldest', 'name_asc', 'name_desc', 'category'];
		if (!in_array($sort, $allowed, true)) {
			$sort = 'custom';
		}
		$this->config->setUserValue($uid, Application::APP_ID, self::KEY_CHECKLIST_ITEM_SORT . '_' . $houseId, $sort);
		return $sort;
	}

	// ----- Notification preferences -----

	public function getNotificationPref(string $uid, int $houseId, string $prefKey): bool {
		$value = $this->config->getUserValue(
			$uid,
			Application::APP_ID,
			$prefKey . '_' . $houseId,
			'1', // enabled by default
		);
		return $value === '1';
	}

	public function setNotificationPref(string $uid, int $houseId, string $prefKey, bool $enabled): void {
		$this->config->setUserValue(
			$uid,
			Application::APP_ID,
			$prefKey . '_' . $houseId,
			$enabled ? '1' : '0',
		);
	}

	/**
	 * @return array<string, bool>
	 */
	public function getNotificationPrefs(string $uid, int $houseId): array {
		return [
			'notifyPhoto' => $this->getNotificationPref($uid, $houseId, 'notify_photo'),
			'notifyNoteCreate' => $this->getNotificationPref($uid, $houseId, 'notify_note_create'),
			'notifyNoteEdit' => $this->getNotificationPref($uid, $houseId, 'notify_note_edit'),
			'notifyItemAdd' => $this->getNotificationPref($uid, $houseId, 'notify_item_add'),
			'notifyItemRecur' => $this->getNotificationPref($uid, $houseId, 'notify_item_recur'),
			'notifyItemDone' => $this->getNotificationPref($uid, $houseId, 'notify_item_done'),
		];
	}

	// ----- Unified house prefs -----

	/**
	 * @return array<string, mixed>
	 */
	public function getAllHousePrefs(string $uid, int $houseId): array {
		return [
			'imageFolder' => $this->getImageFolder($uid, $houseId),
			'photoSort' => $this->getPhotoSort($uid, $houseId),
			'photoFoldersFirst' => $this->getPhotoFoldersFirst($uid, $houseId),
			'noteSort' => $this->getNoteSort($uid, $houseId),
			'checklistItemSort' => $this->getChecklistItemSort($uid, $houseId),
			...$this->getNotificationPrefs($uid, $houseId),
		];
	}

	/**
	 * @param array<string, mixed> $patch
	 */
	public function setHousePrefs(string $uid, int $houseId, array $patch): void {
		if (array_key_exists('imageFolder', $patch) && is_string($patch['imageFolder'])) {
			$this->setImageFolder($uid, $houseId, $patch['imageFolder']);
		}
		if (array_key_exists('photoSort', $patch) && is_string($patch['photoSort'])) {
			$this->setPhotoSort($uid, $houseId, $patch['photoSort']);
		}
		if (array_key_exists('photoFoldersFirst', $patch) && is_bool($patch['photoFoldersFirst'])) {
			$this->setPhotoFoldersFirst($uid, $houseId, $patch['photoFoldersFirst']);
		}
		if (array_key_exists('noteSort', $patch) && is_string($patch['noteSort'])) {
			$this->setNoteSort($uid, $houseId, $patch['noteSort']);
		}
		if (array_key_exists('checklistItemSort', $patch) && is_string($patch['checklistItemSort'])) {
			$this->setChecklistItemSort($uid, $houseId, $patch['checklistItemSort']);
		}
		// Notification prefs
		$notifKeys = [
			'notifyPhoto' => 'notify_photo',
			'notifyNoteCreate' => 'notify_note_create',
			'notifyNoteEdit' => 'notify_note_edit',
			'notifyItemAdd' => 'notify_item_add',
			'notifyItemRecur' => 'notify_item_recur',
			'notifyItemDone' => 'notify_item_done',
		];
		foreach ($notifKeys as $camel => $dbKey) {
			if (array_key_exists($camel, $patch) && is_bool($patch[$camel])) {
				$this->setNotificationPref($uid, $houseId, $dbKey, $patch[$camel]);
			}
		}
	}

	private function normalizeFolder(string $folder): string {
		$trimmed = trim($folder);
		if ($trimmed === '') {
			return self::DEFAULT_IMAGE_FOLDER;
		}
		// Ensure leading slash, no trailing slash.
		$withLeading = str_starts_with($trimmed, '/') ? $trimmed : '/' . $trimmed;
		$clean = rtrim($withLeading, '/');
		return $clean === '' ? '/' : $clean;
	}
}
