<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\AppInfo;

use OCA\Pantry\Notification\Notifier;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
	public const APP_ID = 'pantry';
	public const DIST_DIR = '../dist';
	public const JS_DIR = self::DIST_DIR . '/js';
	public const CSS_DIR = self::DIST_DIR . '/css';

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerNotifierService(Notifier::class);
	}

	public function boot(IBootContext $context): void {
	}

	/**
	 * Helper to parse Vite Manifest
	 */
	public static function getViteEntryScript(string $entryName): string {
		$jsDir = realpath(__DIR__ . '/../' . Application::JS_DIR);
		$manifestPath = dirname($jsDir) . '/.vite/manifest.json';

		if (!file_exists($manifestPath)) {
			return '';
		}

		$manifest = json_decode(file_get_contents($manifestPath), true);

		if (isset($manifest[$entryName]['file'])) {
			$manifestFile = $manifest[$entryName]['file'];
			$fullPath = dirname($jsDir) . '/' . $manifestFile;

			if (!file_exists($fullPath)) {
				return '';
			}

			return pathinfo($manifestFile, PATHINFO_FILENAME);
		}

		return '';
	}

	public static function tableName(string $table): string {
		return self::APP_ID . '_' . $table;
	}
}
