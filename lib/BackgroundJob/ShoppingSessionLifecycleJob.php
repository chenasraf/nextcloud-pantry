<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\BackgroundJob;

use OCA\Pantry\Service\ShoppingSessionService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

/**
 * Shopping-session lifecycle job (ADR 0001 + 0008). Runs hourly and does two
 * passes:
 *
 * 1. Auto-close abandoned live sessions — those idle (no `last_seen_at` update)
 *    for longer than {@see self::IDLE_SECONDS}.
 * 2. Retention purge — when the `shopping_history_retention_days` app config is
 *    positive, permanently delete closed sessions older than that horizon
 *    (cascading their child rows). Default 0 = keep forever.
 */
class ShoppingSessionLifecycleJob extends TimedJob {
	private const INTERVAL_SECONDS = 3600;
	/** A live session with no activity for this long is treated as abandoned. */
	private const IDLE_SECONDS = 4 * 3600;

	public function __construct(
		ITimeFactory $time,
		private ShoppingSessionService $sessions,
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(self::INTERVAL_SECONDS);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	protected function run(mixed $argument): void {
		$closed = $this->sessions->closeIdleSessions(self::IDLE_SECONDS);
		if ($closed > 0) {
			$this->logger->info('Pantry: auto-closed {count} idle shopping session(s)', ['count' => $closed]);
		}

		$retentionDays = $this->appConfig->getValueInt('pantry', 'shopping_history_retention_days', 0);
		$purged = $this->sessions->purgeAgedSessions($retentionDays);
		if ($purged > 0) {
			$this->logger->info('Pantry: purged {count} aged shopping session(s)', ['count' => $purged]);
		}
	}
}
