<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Activity;

use OCA\Pantry\AppInfo\Application;
use OCP\Activity\ActivitySettings;
use OCP\IL10N;

class Setting extends ActivitySettings {
	public function __construct(
		private IL10N $l,
	) {
	}

	public function getIdentifier(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->l->t('A house member changed something in <strong>Pantry</strong>');
	}

	public function getGroupIdentifier(): string {
		return Application::APP_ID;
	}

	public function getGroupName(): string {
		return $this->l->t('Pantry');
	}

	public function getPriority(): int {
		return 50;
	}

	public function canChangeStream(): bool {
		return true;
	}

	public function isDefaultEnabledStream(): bool {
		return true;
	}

	public function canChangeMail(): bool {
		return true;
	}

	public function isDefaultEnabledMail(): bool {
		return false;
	}
}
