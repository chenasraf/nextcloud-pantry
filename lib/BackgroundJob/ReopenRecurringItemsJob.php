<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\BackgroundJob;

use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Service\ChecklistService;
use OCA\Pantry\Service\NotificationService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Periodically processes recurring checklist items:
 * 1. Reopens done items whose next_due_at has passed.
 * 2. Re-notifies for undone fixed-schedule items whose next_due_at has passed
 *    (the item is already visible but the schedule ticked again — nudge).
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
		// 1. Reopen done items whose schedule has passed.
		$reopened = $this->lists->reopenDueItems();
		if (count($reopened) > 0) {
			$this->logger->info('Pantry: reopened {count} recurring item(s)', ['count' => count($reopened)]);
			$this->notifyGrouped($reopened, 'recurred');
		}

		// 2. Nudge for undone fixed-schedule items whose schedule ticked again.
		$reminded = $this->lists->advanceDueReminders();
		if (count($reminded) > 0) {
			$this->logger->info('Pantry: sent reminders for {count} undone item(s)', ['count' => count($reminded)]);
			$this->notifyGrouped($reminded, 'reminder');
		}
	}

	/**
	 * Group items by list and send one notification per list.
	 *
	 * @param ChecklistItem[] $items
	 */
	private function notifyGrouped(array $items, string $type): void {
		$byList = [];
		foreach ($items as $item) {
			$byList[$item->getListId()][] = $item->getName();
		}
		foreach ($byList as $listId => $itemNames) {
			try {
				$list = $this->lists->getList($listId);
				if ($type === 'recurred') {
					$this->notifications->notifyItemsRecurred(
						$list->getHouseId(),
						$itemNames,
						$list->getName(),
					);
				} else {
					$this->notifications->notifyItemsReminder(
						$list->getHouseId(),
						$itemNames,
						$list->getName(),
					);
				}
			} catch (\Throwable $e) {
				$this->logger->warning('Pantry: failed to notify for {type} items in list {id}: {msg}', [
					'type' => $type,
					'id' => $listId,
					'msg' => $e->getMessage(),
				]);
			}
		}
	}
}
