<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Db\Checklist;
use OCA\Pantry\Db\ChecklistItemMapper;
use OCA\Pantry\Db\ChecklistMapper;
use OCA\Pantry\Db\House;
use OCA\Pantry\Db\HouseMemberMapper;
use OCA\Pantry\Db\Share;
use Psr\Log\LoggerInterface;

/**
 * Once a day per inventory, at its house's recurrence time, tells members
 * what newly ran low or out, or started expiring or expired, since the last
 * summary: "Kitchen: 2 low, 3 expiring, 1 expired". Items flagged only,
 * without a shopping list to go to, count too. An item that went low and
 * recovered between two summaries is not reported.
 *
 * Each item keeps the stock and expiry status the last summary saw. An
 * inventory with its summary turned off still moves them on, so turning it
 * back on reports only what happens from then.
 *
 * @psalm-type StockSummaryCounts = array{low: int, out: int, expiring: int, expired: int}
 */
class StockSummaryService {
	public function __construct(
		private ChecklistMapper $listMapper,
		private ChecklistItemMapper $itemMapper,
		private HouseService $houses,
		private HouseMemberMapper $members,
		private ShareService $shares,
		private NotificationService $notifications,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Summarise every inventory not yet summarised today whose house's
	 * recurrence time has passed.
	 *
	 * @return int How many summaries it sent.
	 */
	public function run(int $now): int {
		$today = (new \DateTimeImmutable())->setTimestamp($now)->setTime(0, 0, 0);
		$recurrenceTimes = [];
		$sent = 0;
		foreach ($this->listMapper->findInventoriesToSummarize($today->getTimestamp()) as $inventory) {
			$houseId = $inventory->getHouseId();
			$minutes = $recurrenceTimes[$houseId] ??= $this->recurrenceTime($houseId);
			if ($now < $today->setTime(intdiv($minutes, 60), $minutes % 60)->getTimestamp()) {
				continue;
			}
			try {
				$sent += $this->summarize($inventory, $today->getTimestamp()) ? 1 : 0;
			} catch (\Throwable $e) {
				$this->logger->error('Pantry stock summary: failed for inventory {id}: {msg}', [
					'id' => $inventory->getId(),
					'msg' => $e->getMessage(),
					'exception' => $e,
				]);
			}
		}
		return $sent;
	}

	/**
	 * What newly needs restocking on the inventory, moving each item's
	 * summarised status on, and send it when there is any.
	 */
	private function summarize(Checklist $inventory, int $today): bool {
		$counts = ['low' => 0, 'out' => 0, 'expiring' => 0, 'expired' => 0];
		foreach ($this->itemMapper->findByList((int)$inventory->getId()) as $item) {
			$snapshot = RestockSyncService::snapshot($item, $inventory->expiryLeadDays(), $today);
			$stock = $snapshot['status'];
			$expiry = $snapshot['expiry'];
			if ($snapshot['low'] && !in_array($item->getSummarizedStockStatus(), [StockRules::STATUS_LOW, StockRules::STATUS_OUT], true)) {
				if ($stock === StockRules::STATUS_OUT) {
					$counts['out']++;
				} else {
					$counts['low']++;
				}
			}
			if ($expiry !== $item->getSummarizedExpiryStatus()) {
				if ($expiry === StockRules::EXPIRY_EXPIRING) {
					$counts['expiring']++;
				} elseif ($expiry === StockRules::EXPIRY_EXPIRED) {
					$counts['expired']++;
				}
			}
			if ($item->getSummarizedStockStatus() !== $stock || $item->getSummarizedExpiryStatus() !== $expiry) {
				$item->setSummarizedStockStatus($stock);
				$item->setSummarizedExpiryStatus($expiry);
				$this->itemMapper->update($item);
			}
		}
		$inventory->setSummarizedOn($today);
		$this->listMapper->update($inventory);

		if (!$inventory->sendsStockSummary() || array_sum($counts) === 0) {
			return false;
		}
		$recipients = [];
		foreach ($this->members->findByHouse($inventory->getHouseId()) as $member) {
			$uid = $member->getUserId();
			if ($this->shares->canView($inventory->getHouseId(), $uid, Share::TYPE_CHECKLIST, (int)$inventory->getId())) {
				$recipients[] = $uid;
			}
		}
		$this->notifications->notifyStockSummary($inventory, $counts, $recipients);
		return true;
	}

	private function recurrenceTime(int $houseId): int {
		try {
			return $this->houses->get($houseId)->getRecurrenceTime();
		} catch (\Throwable) {
			return House::DEFAULT_RECURRENCE_TIME;
		}
	}
}
