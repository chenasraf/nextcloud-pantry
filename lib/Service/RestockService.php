<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Db\Checklist;
use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Db\ChecklistItemMapper;
use OCA\Pantry\Db\ChecklistMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\TTransactional;
use OCP\IDBConnection;

/**
 * A member ticking a checklist item that restocks inventory items puts what
 * they bought back into stock, and their uncheck takes it out again.
 *
 * - What was bought is the first number in the item's quantity when it is
 *   ticked: untouched, what stock asked for; set by hand, what the member
 *   actually bought. With no number, everything that needs restocking goes to
 *   full. Level items go to Full.
 * - Inventory items that need restocking are filled in the order they
 *   linked, until the number runs out; what is left over goes to the first of
 *   them. When none needs restocking, it all goes to the first linked. Items
 *   whose on-hand amount is not counted are skipped.
 * - Stock that is expiring or expired is thrown out for the new: its share
 *   replaces what is on hand rather than adding to it.
 * - Everything restocked gets a fresh expiry date, today + its shelf life, or
 *   none without one. Items not counted are not restocked but still get the
 *   fresh date, so the tick settles their expiry too.
 * - The tick keeps a record of what it changed, so the member's uncheck can
 *   put it back (never below 0, levels and replaced amounts as they were, the
 *   date unless it was edited since). Any other uncheck drops the record and
 *   leaves stock alone.
 * - Stock changing runs the restock sync, so a shared item that came up short
 *   goes straight back on the list asking for the rest.
 *
 * @psalm-import-type RestockEntry from ChecklistItem
 * @psalm-import-type RestockSyncEvent from RestockSyncService
 * @psalm-type RestockToggle = array{item: ChecklistItem, restocked: list<RestockEntry>, undone: list<RestockEntry>, events: list<RestockSyncEvent>}
 */
class RestockService {
	use TTransactional;

	/** @var array<int, Checklist|null> */
	private array $lists = [];

	public function __construct(
		private ChecklistService $checklists,
		private ChecklistItemMapper $itemMapper,
		private ChecklistMapper $listMapper,
		private RestockSyncService $sync,
		private IDBConnection $db,
	) {
	}

	/**
	 * A member checks a checklist item off, or unchecks it.
	 *
	 * @return RestockToggle
	 */
	public function toggle(int $itemId, string $uid): array {
		return $this->atomic(function () use ($itemId, $uid): array {
			$this->itemMapper->lock($itemId);
			$before = $this->checklists->getItem($itemId);
			$record = $before->getDone() ? $before->getRestock() : null;
			$item = $this->checklists->toggleItem($itemId, $uid);
			return $item->getDone() ? $this->restock($item, $uid) : $this->undo($item, $record ?? [], $uid);
		}, $this->db);
	}

	/**
	 * A member unchecks an item they ticked by some other path than
	 * {@see toggle()}, such as Shopping Mode's own uncheck, after it has been
	 * unchecked.
	 *
	 * @param list<RestockEntry> $record The item's record from before it was unchecked.
	 * @return RestockToggle
	 */
	public function afterMemberUncheck(ChecklistItem $item, array $record, string $uid): array {
		return $this->atomic(fn (): array => $this->undo($item, $record, $uid), $this->db);
	}

	/**
	 * @return RestockToggle
	 */
	private function restock(ChecklistItem $row, string $uid): array {
		$contributors = $this->sync->contributors($row);
		if ($contributors === []) {
			return $this->result($row, [], [], []);
		}
		$today = StockRules::today();
		$restocked = [];
		$updated = [];
		$events = [];
		foreach ($this->plan($contributors, StockRules::firstNumber($row->getQuantity()), $today) as $grant) {
			$item = $grant['item'];
			$before = $this->sync->snapshotOf($item, $today);
			$entry = $this->entry($item);
			if ($grant['stock'] && self::isLevel($item)) {
				$entry['previousLevel'] = $item->getStockLevel() ?? StockRules::LEVEL_FULL;
				$entry['replaced'] = $grant['replace'];
				$item->setStockLevel(StockRules::LEVEL_FULL);
			} elseif ($grant['stock']) {
				$amount = $grant['amount'] ?? 0.0;
				$entry['added'] = StockRules::formatNumber($amount);
				if ($grant['replace']) {
					$entry['replaced'] = true;
					$entry['previousOnHand'] = $item->getQuantity();
					$item->setQuantity(StockRules::withNumber(self::onHandText($item), $amount));
				} else {
					$item->setQuantity(self::onHandPlus($item, $amount));
				}
			}
			$entry['expiresAt'] = StockRules::renewedExpiry($item->getShelfLifeDays(), $today);
			$item->setExpiresAt($entry['expiresAt']);
			$item->setUpdatedAt(time());
			$this->itemMapper->update($item);
			$restocked[] = $entry;
			$updated[] = [$item, $before];
		}
		if ($restocked === []) {
			return $this->result($row, [], [], []);
		}
		$row->setRestock($restocked);
		$this->itemMapper->update($row);
		foreach ($updated as [$item, $before]) {
			array_push($events, ...$this->sync->afterWrite($item, $before, false, $uid));
		}
		return $this->result($row, $restocked, [], $events);
	}

	/**
	 * @param list<RestockEntry> $record
	 * @return RestockToggle
	 */
	private function undo(ChecklistItem $row, array $record, string $uid): array {
		$undone = [];
		$events = [];
		foreach ($record as $entry) {
			try {
				$item = $this->itemMapper->findById($entry['itemId'], true);
			} catch (DoesNotExistException) {
				continue;
			}
			if ($item->getLinkedItemId() !== (int)$row->getId()) {
				continue;
			}
			$before = $this->sync->snapshotOf($item);
			$changed = false;
			if ($entry['previousLevel'] !== null) {
				if (self::isLevel($item)) {
					$item->setStockLevel($entry['previousLevel']);
					$changed = true;
				}
			} elseif ($entry['added'] !== null && !self::isLevel($item) && self::onHand($item) !== null) {
				if ($entry['replaced']) {
					$item->setQuantity($entry['previousOnHand']);
				} else {
					$item->setQuantity(self::onHandPlus($item, -(StockRules::firstNumber($entry['added']) ?? 0.0)));
				}
				$changed = true;
			}
			// A date edited by hand since the tick is the member's, so it stays.
			if ($item->getExpiresAt() === $entry['expiresAt'] && $entry['previousExpiresAt'] !== $entry['expiresAt']) {
				$item->setExpiresAt($entry['previousExpiresAt']);
				$changed = true;
			}
			if (!$changed) {
				continue;
			}
			$item->setUpdatedAt(time());
			$this->itemMapper->update($item);
			$undone[] = $entry;
			array_push($events, ...$this->sync->afterWrite($item, $before, false, $uid));
		}
		return $this->result($row, [], $undone, $events);
	}

	/**
	 * Who gets what of the bought amount. Each count item gets an amount, to
	 * add to what is on hand or, when that is going off, to replace it; a level
	 * item goes to Full. Items not counted only get the fresh date (`stock`
	 * false), and only when it would change.
	 *
	 * @param list<ChecklistItem> $contributors Earliest linked first.
	 * @return list<array{item: ChecklistItem, amount: float|null, replace: bool, stock: bool}>
	 */
	private function plan(array $contributors, ?float $bought, int $today): array {
		$needing = [];
		$expiring = [];
		foreach ($contributors as $item) {
			$snapshot = $this->sync->snapshotOf($item, $today);
			if ($snapshot['needs'] && !self::uncounted($item)) {
				$needing[] = $item;
				$expiring[(int)$item->getId()] = StockRules::expiryNeedsRestock($snapshot['expiry']);
			}
		}

		$grants = [];
		$left = $bought;
		foreach ($needing as $item) {
			if ($left !== null && $left <= 0) {
				break;
			}
			$id = (int)$item->getId();
			$replace = $expiring[$id];
			if (self::isLevel($item)) {
				$grants[$id] = ['item' => $item, 'amount' => null, 'replace' => $replace, 'stock' => true];
				if ($left !== null) {
					$left -= min($left, StockRules::firstNumber($item->getQuantity()) ?? 0.0);
				}
				continue;
			}
			$need = $replace ? (StockRules::firstNumber($item->getTarget()) ?? 0.0) : self::missing($item);
			$give = $left !== null ? min($left, $need) : $need;
			if ($give > 0) {
				$grants[$id] = ['item' => $item, 'amount' => $give, 'replace' => $replace, 'stock' => true];
				if ($left !== null) {
					$left -= $give;
				}
			}
		}
		if ($left !== null && $left > 0) {
			$recipient = self::firstCounted($needing !== [] ? $needing : $contributors);
			if ($recipient !== null) {
				$id = (int)$recipient->getId();
				$grants[$id] = [
					'item' => $recipient,
					'amount' => ($grants[$id]['amount'] ?? 0.0) + $left,
					'replace' => $grants[$id]['replace'] ?? false,
					'stock' => true,
				];
			}
		}
		foreach ($contributors as $item) {
			if (self::uncounted($item) && $item->getExpiresAt() !== StockRules::renewedExpiry($item->getShelfLifeDays(), $today)) {
				$grants[(int)$item->getId()] = ['item' => $item, 'amount' => null, 'replace' => false, 'stock' => false];
			}
		}
		return array_values($grants);
	}

	/**
	 * @param list<ChecklistItem> $items
	 */
	private static function firstCounted(array $items): ?ChecklistItem {
		foreach ($items as $item) {
			if (!self::isLevel($item) && self::onHand($item) !== null && StockRules::firstNumber($item->getTarget()) !== null) {
				return $item;
			}
		}
		return null;
	}

	private static function isLevel(ChecklistItem $item): bool {
		return $item->getStockMode() === StockRules::MODE_LEVEL;
	}

	/** What a count item is short of its target. */
	private static function missing(ChecklistItem $item): float {
		$target = StockRules::firstNumber($item->getTarget());
		$onHand = self::onHand($item);
		if ($target === null || $onHand === null) {
			return 0.0;
		}
		return max(0.0, $target - $onHand);
	}

	/** A count item's on-hand number: blank is 0, text without a number is not counted. */
	private static function onHand(ChecklistItem $item): ?float {
		$text = trim($item->getQuantity() ?? '');
		return $text === '' ? 0.0 : StockRules::firstNumber($text);
	}

	private static function uncounted(ChecklistItem $item): bool {
		return !self::isLevel($item) && self::onHand($item) === null;
	}

	/**
	 * On-hand text to write a number into. A blank amount takes the target's
	 * wording, as the stepper does.
	 */
	private static function onHandText(ChecklistItem $item): string {
		$text = trim($item->getQuantity() ?? '');
		if ($text !== '') {
			return $text;
		}
		$target = trim($item->getTarget() ?? '');
		return StockRules::firstNumber($target) !== null ? StockRules::withNumber($target, 0) : '0';
	}

	/** On-hand text after adding `$delta`, never below 0. */
	private static function onHandPlus(ChecklistItem $item, float $delta): string {
		return StockRules::withNumber(self::onHandText($item), max(0.0, (self::onHand($item) ?? 0.0) + $delta));
	}

	/**
	 * A record of a tick that changes nothing yet, with the item's date as it
	 * was.
	 *
	 * @return RestockEntry
	 */
	private function entry(ChecklistItem $item): array {
		return [
			'itemId' => (int)$item->getId(),
			'listId' => $item->getListId(),
			'itemName' => $item->getName(),
			'listName' => $this->list($item->getListId())?->getName() ?? '',
			'added' => null,
			'previousLevel' => null,
			'replaced' => false,
			'previousOnHand' => null,
			'previousExpiresAt' => $item->getExpiresAt(),
			'expiresAt' => $item->getExpiresAt(),
		];
	}

	/**
	 * @param list<RestockEntry> $restocked
	 * @param list<RestockEntry> $undone
	 * @param list<RestockSyncEvent> $events
	 * @return RestockToggle
	 */
	private function result(ChecklistItem $row, array $restocked, array $undone, array $events): array {
		// The sync may have brought the item straight back.
		if ($events !== []) {
			$row = $this->itemMapper->findById((int)$row->getId(), true);
		}
		return ['item' => $row, 'restocked' => $restocked, 'undone' => $undone, 'events' => $events];
	}

	private function list(int $listId): ?Checklist {
		if (!array_key_exists($listId, $this->lists)) {
			try {
				$this->lists[$listId] = $this->listMapper->findById($listId, true);
			} catch (DoesNotExistException) {
				$this->lists[$listId] = null;
			}
		}
		return $this->lists[$listId];
	}
}
