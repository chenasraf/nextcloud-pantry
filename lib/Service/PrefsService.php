<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\AppInfo\Application;
use OCP\IConfig;

class PrefsService {
	private const KEY_LAST_HOUSE = 'last_house_id';
	private const KEY_IMAGE_FOLDER = 'image_folder';
	public const DEFAULT_IMAGE_FOLDER = '/Pantry';

	public function __construct(
		private IConfig $config,
	) {
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
		];
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
