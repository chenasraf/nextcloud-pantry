<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Activity\ActivityPublisher;
use OCA\Pantry\Db\Checklist;
use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Db\ChecklistItemMapper;
use OCA\Pantry\Db\ChecklistMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\TTransactional;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

/**
 * Keeps the checklist item that restocks an inventory item in step with its
 * stock, inside the request of the write that changed it.
 *
 * - An item that needs restocking puts its linked row back in play asking
 *   for what is missing: open rows are updated, checked ones unchecked,
 *   archived ones unarchived, and a trashed one is replaced by a new row on
 *   the restock checklist. An unlinked item that auto-links finds or adds its
 *   row there first. Without a link or a restock checklist it is only marked
 *   on the inventory.
 * - A row several inventories link asks for the sum of what each low one is
 *   missing, in the wording of the first to link.
 * - A quantity a member set by hand is left alone until the row is next done.
 * - When the write brings the item back in stock and nothing else linked to
 *   the row needs it, the row is ticked off with nobody credited ("No longer
 *   needed"), unless a member reopened it to buy anyway.
 * - Rows are created and revived in the name of the member whose write caused
 *   it, whatever their rights on the checklist, so it shows in Activity; with
 *   nobody behind it, in the app's name.
 *
 * Syncs touching one row run one at a time: each holds the row, or the
 * restock checklist while it may create one, for its transaction.
 *
 * @psalm-type RestockSnapshot = array{status: string, shortfall: string|null, needs: bool}
 * @psalm-type RestockSyncEvent = array{event: 'added'|'revived'|'changed'|'pinned'|'recovered', itemId: int, rowId: int, rowName: string, quantity: string|null, listId: int, listName: string}
 */
class RestockSyncService {
	use TTransactional;

	public const EVENT_ADDED = 'added';
	public const EVENT_REVIVED = 'revived';
	public const EVENT_CHANGED = 'changed';
	public const EVENT_PINNED = 'pinned';
	public const EVENT_RECOVERED = 'recovered';

	/** @var array<int, Checklist|null> Lists by id, trashed ones included, memoized per request. */
	private array $lists = [];

	public function __construct(
		private ChecklistMapper $listMapper,
		private ChecklistItemMapper $itemMapper,
		private ChecklistService $checklists,
		private StockLinkService $stockLinks,
		private TemplateCopyService $templateCopy,
		private ImageService $images,
		private ActivityPublisher $activity,
		private NotificationService $notifications,
		private HouseService $houses,
		private IDBConnection $db,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * What a sync compares an item's stock by, taken before a write so the
	 * write can tell whether it changed anything.
	 *
	 * @return RestockSnapshot
	 */
	public static function snapshot(ChecklistItem $item): array {
		$result = StockRules::evaluate(self::stockOf($item));
		return [
			'status' => $result['stockStatus'],
			'shortfall' => $result['shortfall'],
			'needs' => $result['stockStatus'] === StockRules::STATUS_LOW || $result['stockStatus'] === StockRules::STATUS_OUT,
		];
	}

	/**
	 * Sync after a write to an inventory item. It runs when the write changed
	 * the item's stock status or shortfall, or gave it a link. Only a write
	 * that took the item out of needing restock ticks its row off.
	 *
	 * @param RestockSnapshot|null $before The item's stock before the write; null for a new item.
	 * @param string|null $actorUid Who made the write.
	 * @return list<RestockSyncEvent>
	 */
	public function afterWrite(ChecklistItem $item, ?array $before, bool $linked, ?string $actorUid): array {
		$inventory = $this->list($item->getListId());
		if ($inventory === null || !$this->inPlay($item, $inventory)) {
			return [];
		}
		$after = self::snapshot($item);
		$changed = $before === null
			? $after['needs']
			: $before['status'] !== $after['status'] || $before['shortfall'] !== $after['shortfall'];
		if (!$changed && !$linked) {
			return [];
		}
		$mayRecover = $before !== null && $before['needs'] && !$after['needs'];
		$event = $this->sync($item, $inventory, $mayRecover, $actorUid);
		return $event !== null ? [$event] : [];
	}

	/**
	 * Bring every item on the inventory that needs restocking onto its row, as
	 * when its restock checklist is set. Nobody's write caused it, so nothing
	 * is ticked off and it is credited to the app.
	 */
	public function backfill(Checklist $inventory): void {
		if ($this->stockLinks->restockChecklist($inventory) === null) {
			return;
		}
		$this->lists[(int)$inventory->getId()] = $inventory;
		foreach ($this->itemMapper->findByList((int)$inventory->getId()) as $item) {
			if (self::snapshot($item)['needs']) {
				$this->sync($item, $inventory, false, null);
			}
		}
	}

	/**
	 * Back-fill every inventory restocking into the checklist, as when it is
	 * restored from the trash or the archive.
	 */
	public function backfillInto(Checklist $checklist): void {
		if (!$checklist->isChecklist()) {
			return;
		}
		foreach ($this->listMapper->findRestockingInto((int)$checklist->getId()) as $inventory) {
			$this->backfill($inventory);
		}
	}

	/**
	 * Set a row's quantity back to what its linked items ask for, as when a
	 * member lets stock set it again.
	 *
	 * @return list<RestockSyncEvent>
	 */
	public function resyncRow(ChecklistItem $row): array {
		$event = $this->atomic(function () use ($row): ?array {
			$this->itemMapper->lock((int)$row->getId());
			$row = $this->itemMapper->findById((int)$row->getId(), true);
			$contributors = $this->contributors($row);
			if ($contributors === [] || $row->isQuantityPinned()) {
				return null;
			}
			return $this->setQuantity($row, $contributors[0], $this->quantityFor($contributors));
		}, $this->db);
		return $event !== null ? [$event] : [];
	}

	// ----- Sync -----

	/**
	 * @return RestockSyncEvent|null
	 */
	private function sync(ChecklistItem $item, Checklist $inventory, bool $mayRecover, ?string $actorUid): ?array {
		$rowId = $item->getLinkedItemId();
		try {
			$row = $rowId !== null ? $this->itemMapper->findById($rowId, true) : null;
		} catch (DoesNotExistException) {
			$row = null;
		}
		$needs = self::snapshot($item)['needs'];
		if ($row !== null && $this->usable($row)) {
			return $this->syncRow($item, $row, $mayRecover, $actorUid);
		}
		if (!$needs || ($row === null && !$item->autoLinks())) {
			return null;
		}
		return $this->findOrAdd($item, $inventory, $actorUid);
	}

	/**
	 * Bring the linked row in step with everything linked to it.
	 *
	 * @return RestockSyncEvent|null
	 */
	private function syncRow(ChecklistItem $item, ChecklistItem $row, bool $mayRecover, ?string $actorUid): ?array {
		return $this->atomic(function () use ($item, $row, $mayRecover, $actorUid): ?array {
			$rowId = (int)$row->getId();
			$this->itemMapper->lock($rowId);
			$row = $this->itemMapper->findById($rowId, true);
			$contributors = $this->contributors($row);
			$self = $this->find($contributors, $item) ?? $item;
			$low = array_values(array_filter($contributors, static fn (ChecklistItem $c): bool => self::snapshot($c)['needs']));

			if ($low === []) {
				if (!$mayRecover || $row->getDone() || $row->getArchivedAt() !== null || $row->isMemberReopened()) {
					return null;
				}
				$this->checklists->systemDone($row);
				return $this->event(self::EVENT_RECOVERED, $self, $row);
			}

			$revived = false;
			if ($row->getDone() || $row->getArchivedAt() !== null) {
				$row = $this->checklists->reviveForRestock($row);
				$revived = true;
				$this->announce($row, $actorUid, added: false);
			}
			$event = $this->setQuantity($row, $self, $this->quantityFor($contributors));
			if ($revived) {
				return $this->event(self::EVENT_REVIVED, $self, $row);
			}
			return $event;
		}, $this->db);
	}

	/**
	 * Link the item to the one same-name row on its restock checklist, or add
	 * a row there. Several same-name rows wait for a member to pick one.
	 *
	 * @return RestockSyncEvent|null
	 */
	private function findOrAdd(ChecklistItem $item, Checklist $inventory, ?string $actorUid): ?array {
		$restock = $this->stockLinks->restockChecklist($inventory);
		if ($restock === null) {
			return null;
		}
		return $this->atomic(function () use ($item, $inventory, $restock, $actorUid): ?array {
			$this->listMapper->lock((int)$restock->getId());
			// Another request may have linked it while this one waited.
			$item = $this->itemMapper->findById((int)$item->getId());
			$linked = $item->getLinkedItemId();
			if ($linked !== null) {
				try {
					$row = $this->itemMapper->findById($linked, true);
					if ($this->usable($row)) {
						return $this->syncRow($item, $row, false, $actorUid);
					}
				} catch (DoesNotExistException) {
				}
			}

			$candidates = $item->autoLinks() ? $this->stockLinks->candidates($item, $inventory) : [];
			if (count($candidates) > 1) {
				return null;
			}
			if (count($candidates) === 1) {
				$this->stockLinks->link($item, $candidates[0]);
				return $this->syncRow($item, $candidates[0], false, $actorUid);
			}

			$row = $this->templateCopy->copyForRestock(
				$item,
				$inventory,
				$restock,
				$actorUid,
				$this->contribution($item),
				fn (int $fileId, string $ownerUid): ?int => $this->images->duplicateItemImage($ownerUid, $fileId, $actorUid ?? $ownerUid, $restock->getHouseId()),
			);
			$this->stockLinks->link($item, $row);
			// Same-name items of other inventories may have linked the new row as
			// it was added.
			$this->setQuantity($row, $item, $this->quantityFor($this->contributors($row)));
			$this->announce($row, $actorUid, added: true);
			return $this->event(self::EVENT_ADDED, $item, $row);
		}, $this->db);
	}

	/**
	 * Write the quantity stock asks for onto the row, unless a member pinned
	 * it. A null quantity leaves the row as it is.
	 *
	 * @return RestockSyncEvent|null
	 */
	private function setQuantity(ChecklistItem $row, ChecklistItem $item, ?string $quantity): ?array {
		if ($quantity === null || $quantity === $row->getQuantity()) {
			return null;
		}
		if ($row->isQuantityPinned()) {
			return $this->event(self::EVENT_PINNED, $item, $row);
		}
		$row->setQuantity($quantity);
		$row->setUpdatedAt(time());
		$this->itemMapper->update($row);
		return $this->event(self::EVENT_CHANGED, $item, $row);
	}

	// ----- Quantity -----

	/**
	 * What a row asks for: the sum of the first numbers of what each low item
	 * linked to it is missing, in the wording of the first to link that has a
	 * number. Null when none has one.
	 *
	 * @param list<ChecklistItem> $contributors Earliest linked first.
	 */
	private function quantityFor(array $contributors): ?string {
		$sum = 0.0;
		$wording = null;
		foreach ($contributors as $contributor) {
			if (!self::snapshot($contributor)['needs']) {
				continue;
			}
			$text = $this->contribution($contributor);
			$number = StockRules::firstNumber($text);
			if ($number === null || $text === null) {
				continue;
			}
			$sum += $number;
			$wording ??= $text;
		}
		return $wording !== null ? StockRules::withNumber($wording, $sum) : null;
	}

	/**
	 * What one item asks a row for: a count item's shortfall, a level item's
	 * restock amount.
	 */
	private function contribution(ChecklistItem $item): ?string {
		return StockRules::evaluate(self::stockOf($item))['shortfall'];
	}

	// ----- Helpers -----

	/**
	 * Live items on live inventories linked to the row, earliest linked first.
	 *
	 * @return list<ChecklistItem>
	 */
	public function contributors(ChecklistItem $row): array {
		$out = [];
		foreach ($this->itemMapper->findLinkedTo([(int)$row->getId()]) as $item) {
			$inventory = $this->list($item->getListId());
			if ($inventory !== null && $this->inPlay($item, $inventory)) {
				$out[] = $item;
			}
		}
		return $out;
	}

	/**
	 * @param list<ChecklistItem> $items
	 */
	private function find(array $items, ChecklistItem $item): ?ChecklistItem {
		foreach ($items as $candidate) {
			if ($candidate->getId() === $item->getId()) {
				return $candidate;
			}
		}
		return null;
	}

	private function inPlay(ChecklistItem $item, Checklist $inventory): bool {
		return $inventory->getKind() === Checklist::KIND_INVENTORY
			&& $inventory->getDeletedAt() === null && $inventory->getArchivedAt() === null
			&& $item->getDeletedAt() === null && $item->getArchivedAt() === null;
	}

	/**
	 * A row a sync can bring back: not in the trash, on an active checklist.
	 */
	private function usable(ChecklistItem $row): bool {
		$list = $this->list($row->getListId());
		return $row->getDeletedAt() === null && $list !== null && $list->isChecklist()
			&& $list->getDeletedAt() === null && $list->getArchivedAt() === null;
	}

	/**
	 * Tell the house the row is on its list again, in the name of the member
	 * who caused it.
	 */
	private function announce(ChecklistItem $row, ?string $actorUid, bool $added): void {
		$list = $this->list($row->getListId());
		if ($list === null) {
			return;
		}
		$houseId = $list->getHouseId();
		$author = $actorUid ?? '';
		try {
			$houseName = $this->houses->get($houseId)->getName();
			$this->notifications->notifyItemAdded($houseId, $author, $row->getName(), $list->getName());
			if ($added) {
				$this->activity->publishItemAdded($houseId, $houseName, $author, (int)$row->getId(), $row->getName(), (int)$list->getId(), $list->getName());
			} else {
				$this->activity->publishItemReopened($houseId, $houseName, $author, (int)$row->getId(), $row->getName(), (int)$list->getId(), $list->getName());
			}
		} catch (\Throwable $e) {
			$this->logger->error('Pantry restock sync: failed to announce {item}: {msg}', [
				'item' => $row->getName(),
				'msg' => $e->getMessage(),
				'exception' => $e,
			]);
		}
	}

	/**
	 * @param 'added'|'revived'|'changed'|'pinned'|'recovered' $event
	 * @return RestockSyncEvent
	 */
	private function event(string $event, ChecklistItem $item, ChecklistItem $row): array {
		return [
			'event' => $event,
			'itemId' => (int)$item->getId(),
			'rowId' => (int)$row->getId(),
			'rowName' => $row->getName(),
			'quantity' => $row->getQuantity(),
			'listId' => $row->getListId(),
			'listName' => $this->list($row->getListId())?->getName() ?? '',
		];
	}

	/**
	 * @return array{stockMode: ?string, stockLevel: ?string, quantity: ?string, target: ?string, reorderPoint: ?string}
	 */
	private static function stockOf(ChecklistItem $item): array {
		return [
			'stockMode' => $item->getStockMode(),
			'stockLevel' => $item->getStockLevel(),
			'quantity' => $item->getQuantity(),
			'target' => $item->getTarget(),
			'reorderPoint' => $item->getReorderPoint(),
		];
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
