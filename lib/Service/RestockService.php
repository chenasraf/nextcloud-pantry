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
 * - Inventory items that need restocking are filled to their target in the
 *   order they linked, until the number runs out; what is left over goes to
 *   the first of them. When none needs restocking, it all goes to the first
 *   linked. Items whose on-hand amount is not counted are skipped.
 * - The tick keeps a record of what it added, so the member's uncheck can
 *   take it back out (never below 0, levels back to what they were). Any
 *   other uncheck drops the record and leaves stock alone.
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
		$restocked = [];
		$updated = [];
		$events = [];
		foreach (self::plan($contributors, StockRules::firstNumber($row->getQuantity())) as $grant) {
			$item = $grant['item'];
			$before = $this->sync->snapshotOf($item);
			if ($grant['add'] === null) {
				$previous = $item->getStockLevel();
				$item->setStockLevel(StockRules::LEVEL_FULL);
				$entry = $this->entry($item, null, $previous);
			} else {
				$item->setQuantity(self::onHandPlus($item, $grant['add']));
				$entry = $this->entry($item, StockRules::formatNumber($grant['add']), null);
			}
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
			if ($entry['previousLevel'] !== null) {
				if ($item->getStockMode() !== StockRules::MODE_LEVEL) {
					continue;
				}
				$item->setStockLevel($entry['previousLevel']);
			} else {
				$added = StockRules::firstNumber($entry['added']);
				if ($added === null || $item->getStockMode() === StockRules::MODE_LEVEL || self::onHand($item) === null) {
					continue;
				}
				$item->setQuantity(self::onHandPlus($item, -$added));
			}
			$item->setUpdatedAt(time());
			$this->itemMapper->update($item);
			$undone[] = $entry;
			array_push($events, ...$this->sync->afterWrite($item, $before, false, $uid));
		}
		return $this->result($row, [], $undone, $events);
	}

	/**
	 * Who gets what of the bought amount: an amount to add to each count
	 * item, or null to take a level item to Full.
	 *
	 * @param list<ChecklistItem> $contributors Earliest linked first.
	 * @return list<array{item: ChecklistItem, add: float|null}>
	 */
	public static function plan(array $contributors, ?float $bought): array {
		$needing = array_values(array_filter(
			$contributors,
			static fn (ChecklistItem $c): bool => RestockSyncService::snapshot($c)['low'],
		));
		$grants = [];
		if ($bought === null) {
			foreach ($needing as $item) {
				$missing = self::missing($item);
				if (self::isLevel($item)) {
					$grants[] = ['item' => $item, 'add' => null];
				} elseif ($missing > 0) {
					$grants[] = ['item' => $item, 'add' => $missing];
				}
			}
			return $grants;
		}

		$left = $bought;
		foreach ($needing as $item) {
			if ($left <= 0) {
				break;
			}
			if (self::isLevel($item)) {
				$grants[(int)$item->getId()] = ['item' => $item, 'add' => null];
				$left -= min($left, StockRules::firstNumber($item->getQuantity()) ?? 0.0);
				continue;
			}
			$give = min($left, self::missing($item));
			if ($give > 0) {
				$grants[(int)$item->getId()] = ['item' => $item, 'add' => $give];
				$left -= $give;
			}
		}
		if ($left > 0) {
			$recipient = self::firstCounted($needing !== [] ? $needing : $contributors);
			if ($recipient !== null) {
				$id = (int)$recipient->getId();
				$grants[$id] = ['item' => $recipient, 'add' => ($grants[$id]['add'] ?? 0.0) + $left];
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

	/**
	 * On-hand text after adding `$delta`, never below 0. A blank amount takes
	 * the target's wording, as the stepper does.
	 */
	private static function onHandPlus(ChecklistItem $item, float $delta): string {
		$text = trim($item->getQuantity() ?? '');
		if ($text === '') {
			$target = trim($item->getTarget() ?? '');
			$text = StockRules::firstNumber($target) !== null ? StockRules::withNumber($target, 0) : '0';
		}
		return StockRules::withNumber($text, max(0.0, (self::onHand($item) ?? 0.0) + $delta));
	}

	/**
	 * @return RestockEntry
	 */
	private function entry(ChecklistItem $item, ?string $added, ?string $previousLevel): array {
		return [
			'itemId' => (int)$item->getId(),
			'listId' => $item->getListId(),
			'itemName' => $item->getName(),
			'listName' => $this->list($item->getListId())?->getName() ?? '',
			'added' => $added,
			'previousLevel' => $previousLevel,
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
