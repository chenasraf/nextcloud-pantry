<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\BackgroundJob;

use OCA\Pantry\Service\RestockSyncService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Sends inventory items that started expiring, or expired, to their restock
 * checklist. Expiry moves at midnight but each house's items are synced only
 * once its recurrence time has passed, so a missed run catches up on the
 * next and re-runs find nothing left to do.
 */
class ExpiryRestockJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private RestockSyncService $restockSync,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(15 * 60);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	protected function run(mixed $argument): void {
		$synced = $this->restockSync->syncExpiry($this->time->getTime());
		if ($synced > 0) {
			$this->logger->info('Pantry: synced {count} inventory item(s) whose expiry moved', ['count' => $synced]);
		}
	}
}
