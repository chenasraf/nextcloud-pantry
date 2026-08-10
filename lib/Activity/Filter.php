<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Activity;

use OCA\Pantry\AppInfo\Application;
use OCP\Activity\IFilter;
use OCP\IL10N;
use OCP\IURLGenerator;

class Filter implements IFilter {
	public function __construct(
		private IL10N $l,
		private IURLGenerator $url,
	) {
	}

	public function getIdentifier(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->l->t('Pantry');
	}

	public function getPriority(): int {
		return 50;
	}

	public function getIcon(): string {
		return $this->url->getAbsoluteURL(
			$this->url->imagePath(Application::APP_ID, 'app-dark.svg')
		);
	}

	/**
	 * @param string[] $types
	 * @return string[]
	 */
	public function filterTypes(array $types): array {
		return $types;
	}

	/**
	 * @return string[]
	 */
	public function allowedApps(): array {
		return [Application::APP_ID];
	}
}
