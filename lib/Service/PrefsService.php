<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\AppInfo\Application;
use OCP\IConfig;

class PrefsService {
	private const KEY_LAST_HOUSE = 'last_house_id';

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
}
