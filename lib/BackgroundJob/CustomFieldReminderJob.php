<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\BackgroundJob;

use OCA\Pantry\Service\CustomFieldReminderService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Fires due date custom-field reminders. Day-granularity lead-times make the
 * exact run minute irrelevant, so a missed run simply catches up on the next.
 */
class CustomFieldReminderJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private CustomFieldReminderService $reminders,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(15 * 60);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	protected function run(mixed $argument): void {
		$sent = $this->reminders->sendDueReminders($this->time->getTime());
		if ($sent > 0) {
			$this->logger->info('Pantry: sent {count} custom-field reminder(s)', ['count' => $sent]);
		}
	}
}
