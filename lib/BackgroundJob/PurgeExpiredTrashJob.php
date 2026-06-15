<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\BackgroundJob;

use OCA\Pantry\Service\TrashCleanupService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Runs once per day and permanently deletes any soft-deleted checklist,
 * checklist item, note, or photo whose deleted_at is older than the owning
 * house's configured retention window.
 */
class PurgeExpiredTrashJob extends TimedJob {
	private const INTERVAL_SECONDS = 86400;

	public function __construct(
		ITimeFactory $time,
		private TrashCleanupService $cleanup,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(self::INTERVAL_SECONDS);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	protected function run(mixed $argument): void {
		$totals = $this->cleanup->purgeAll();
		$removed = $totals['lists'] + $totals['items'] + $totals['notes'] + $totals['photos'];
		if ($removed > 0) {
			$this->logger->info(
				'Pantry: purged expired trash — lists={lists} items={items} notes={notes} photos={photos}',
				$totals,
			);
		}
	}
}
