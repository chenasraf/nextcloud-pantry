<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\BackgroundJob;

use OCA\Pantry\Service\ShoppingListService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Periodically reopens recurring shopping-list items whose next_due_at has
 * passed, so they appear unchecked without waiting for a user to open the list.
 */
class ReopenRecurringItemsJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private ShoppingListService $lists,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		// Run every 15 minutes.
		$this->setInterval(15 * 60);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	protected function run(mixed $argument): void {
		$count = $this->lists->reopenDueItems();
		if ($count > 0) {
			$this->logger->info('Pantry: reopened {count} recurring item(s)', ['count' => $count]);
		}
	}
}
