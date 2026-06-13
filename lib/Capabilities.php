<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry;

use OCA\Pantry\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\Capabilities\IPublicCapability;

class Capabilities implements IPublicCapability {
	public function __construct(
		private IAppManager $appManager,
	) {
	}

	/**
	 * @return array{
	 *     pantry: array{
	 *         version: array{
	 *             major: int,
	 *             minor: int,
	 *             micro: int,
	 *             string: string,
	 *             array: list<int>,
	 *         },
	 *         features: list<string>,
	 *     },
	 * }
	 */
	#[\Override]
	public function getCapabilities(): array {
		$version = $this->appManager->getAppVersion(Application::APP_ID);
		$parts = array_map('intval', explode('.', $version));
		$parts = array_pad($parts, 3, 0);

		return [
			Application::APP_ID => [
				'version' => [
					'major' => $parts[0],
					'minor' => $parts[1],
					'micro' => $parts[2],
					'string' => $version,
					'array' => $parts,
				],
				'features' => [
					'houses',
					'checklists',
					'checklist-color',
					'categories',
					'category-sort',
					'photos',
					'notes',
					'notifications',
					'item-images',
					'recurring-items',
					'move-items',
					'one-off-items',
					'item-authors',
					'activity',
					'soft-delete',
					'pref-tap-row-to-complete',
					'pref-category-spacing',
				],
			],
		];
	}
}
