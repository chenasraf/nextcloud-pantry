<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\BackgroundJob;

use OCA\Pantry\Service\ChecklistService;
use OCA\Pantry\Service\NotificationService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Periodically reopens recurring checklist items whose next_due_at has
 * passed, so they appear unchecked without waiting for a user to open the list.
 */
class ReopenRecurringItemsJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private ChecklistService $lists,
		private NotificationService $notifications,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		// Run every 15 minutes.
		$this->setInterval(15 * 60);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	protected function run(mixed $argument): void {
		$reopened = $this->lists->reopenDueItems();
		if (count($reopened) > 0) {
			$this->logger->info('Pantry: reopened {count} recurring item(s)', ['count' => count($reopened)]);
			foreach ($reopened as $item) {
				try {
					$list = $this->lists->getList($item->getListId());
					$this->notifications->notifyItemRecurred(
						$list->getHouseId(),
						$item->getName(),
						$list->getName(),
					);
				} catch (\Throwable $e) {
					$this->logger->warning('Pantry: failed to notify for recurring item {id}: {msg}', [
						'id' => $item->getId(),
						'msg' => $e->getMessage(),
					]);
				}
			}
		}
	}
}
