<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Db\Checklist;
use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Db\ChecklistItemMapper;
use OCA\Pantry\Db\ChecklistMapper;
use OCA\Pantry\Exception\NotFoundException;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * Stock links: the stored pairing between an inventory item and the checklist
 * item that restocks it.
 *
 * - An inventory item has at most one link; a checklist item is linked by at
 *   most one item per inventory.
 * - While an item is unlinked and auto-links, it is paired by name with a row
 *   on its inventory's restock checklist when exactly one row matches. With
 *   several matches nothing links until a member picks one.
 * - A link holds through rename, archive, trash, restore and move. It drops
 *   when either item is deleted for good ({@see ChecklistItemMapper::delete()}),
 *   when an item crosses kinds, or when a move would give an inventory two
 *   items linked to one row.
 *
 * @psalm-type StockLinkRow = array{itemId: int, name: string, quantity: string|null, listId: int, listName: string, listColor: string|null, state: 'open'|'checked'|'archived'|'trashed'}
 * @psalm-type StockLinkSource = array{itemId: int, name: string, listId: int, listName: string}
 */
class StockLinkService {
	public const STATE_OPEN = 'open';
	public const STATE_CHECKED = 'checked';
	public const STATE_ARCHIVED = 'archived';
	public const STATE_TRASHED = 'trashed';

	/** Candidate order: unchecked rows first, then checked, then archived. */
	private const STATE_RANK = [
		self::STATE_OPEN => 0,
		self::STATE_CHECKED => 1,
		self::STATE_ARCHIVED => 2,
		self::STATE_TRASHED => 3,
	];

	/** @var array<int, Checklist|null> Lists by id, trashed ones included, memoized per request. */
	private array $lists = [];

	public function __construct(
		private ChecklistMapper $listMapper,
		private ChecklistItemMapper $itemMapper,
	) {
	}

	// ----- Restock checklist -----

	/**
	 * The checklist the inventory restocks into, or null when it has none or
	 * it is archived, trashed, deleted or no longer a checklist of its house.
	 */
	public function restockChecklist(Checklist $inventory): ?Checklist {
		$id = $inventory->getRestockChecklistId();
		if ($id === null || $inventory->getKind() !== Checklist::KIND_INVENTORY) {
			return null;
		}
		$list = $this->list($id);
		if ($list === null || $list->getDeletedAt() !== null || $list->getArchivedAt() !== null
			|| !$list->isChecklist() || $list->getHouseId() !== $inventory->getHouseId()) {
			return null;
		}
		return $list;
	}

	/**
	 * Check that a list can be the inventory's restock checklist: an active
	 * checklist in the same house.
	 */
	public function assertRestockChecklist(Checklist $inventory, int $checklistId): void {
		$list = $this->list($checklistId);
		if ($list === null || $list->getDeletedAt() !== null || $list->getHouseId() !== $inventory->getHouseId()) {
			throw new NotFoundException('Restock checklist not found');
		}
		if (!$list->isChecklist() || $list->getArchivedAt() !== null) {
			throw new \InvalidArgumentException('The restock checklist must be an active checklist');
		}
	}

	// ----- Linking -----

	/**
	 * Link an inventory item to a checklist item, replacing any link it has.
	 *
	 * @throws \InvalidArgumentException When the row is not a live item on a
	 *                                   checklist of the item's house, or another
	 *                                   item on the same inventory links it.
	 */
	public function link(ChecklistItem $item, ChecklistItem $row, ?int $now = null): ChecklistItem {
		$inventory = $this->requireList($item->getListId());
		$checklist = $this->list($row->getListId());
		if ($inventory->getKind() !== Checklist::KIND_INVENTORY) {
			throw new \InvalidArgumentException('Only inventory items can be linked');
		}
		if ($checklist === null || !$checklist->isChecklist() || $checklist->getHouseId() !== $inventory->getHouseId()) {
			throw new \InvalidArgumentException('Inventory items link only to items on a checklist in the same house');
		}
		if ($row->getDeletedAt() !== null) {
			throw new \InvalidArgumentException('Cannot link to an item in the trash');
		}
		$rowId = (int)$row->getId();
		foreach ($this->itemMapper->findLinkedTo([$rowId]) as $other) {
			if ($other->getListId() === $item->getListId() && $other->getId() !== $item->getId()) {
				throw new \InvalidArgumentException('Another item on this inventory is already linked to that item');
			}
		}
		$now ??= time();
		$item->setLinkedItemId($rowId);
		$item->setLinkedAt($now);
		$item->setUpdatedAt($now);
		$this->itemMapper->update($item);
		return $item;
	}

	/**
	 * Drop the item's link and turn auto-link off, so matching by name does not
	 * pair it again.
	 */
	public function unlink(ChecklistItem $item): ChecklistItem {
		$item->setLinkedItemId(null);
		$item->setLinkedAt(null);
		$item->setAutoLink(false);
		$item->setUpdatedAt(time());
		$this->itemMapper->update($item);
		return $item;
	}

	/**
	 * Link an unlinked, auto-linking inventory item when exactly one row on its
	 * inventory's restock checklist matches its name.
	 *
	 * @return ChecklistItem|null The row it was linked to.
	 */
	public function match(ChecklistItem $item, Checklist $inventory): ?ChecklistItem {
		if (!$this->canMatch($item)) {
			return null;
		}
		$candidates = $this->candidates($item, $inventory);
		if (count($candidates) !== 1) {
			return null;
		}
		$this->link($item, $candidates[0]);
		return $candidates[0];
	}

	/**
	 * Rows on the inventory's restock checklist with the item's name that it
	 * could link to, in the order a member picks from.
	 *
	 * @return list<ChecklistItem>
	 */
	public function candidates(ChecklistItem $item, Checklist $inventory): array {
		$restock = $this->restockChecklist($inventory);
		if ($restock === null) {
			return [];
		}
		return $this->candidatesFrom(
			$item,
			$this->rowsByName($restock),
			array_flip($this->itemMapper->findLinkedIdsFromList((int)$inventory->getId())),
		);
	}

	/**
	 * Match every unlinked, auto-linking item on the inventory, as when its
	 * restock checklist is set.
	 */
	public function matchInventory(Checklist $inventory): void {
		$restock = $this->restockChecklist($inventory);
		if ($restock === null) {
			return;
		}
		$inventoryId = (int)$inventory->getId();
		$rows = $this->rowsByName($restock);
		$linked = array_flip($this->itemMapper->findLinkedIdsFromList($inventoryId));
		foreach ($this->itemsOf($inventoryId) as $item) {
			if (!$this->canMatch($item)) {
				continue;
			}
			$candidates = $this->candidatesFrom($item, $rows, $linked);
			if (count($candidates) === 1) {
				$this->link($item, $candidates[0]);
				$linked[(int)$candidates[0]->getId()] = true;
			}
		}
	}

	// ----- Lifecycle -----

	/** An inventory item was created or renamed. */
	public function onInventoryItemSaved(ChecklistItem $item, Checklist $inventory): void {
		$this->match($item, $inventory);
	}

	/**
	 * A checklist item was created, renamed, moved onto the checklist, or
	 * ticked: the same-name items of every inventory restocking into it try
	 * to match.
	 */
	public function onChecklistRowSaved(ChecklistItem $row, Checklist $checklist): void {
		if (!$checklist->isChecklist()) {
			return;
		}
		$key = ScopedTaxonomyService::nameKey($row->getName());
		foreach ($this->listMapper->findRestockingInto((int)$checklist->getId()) as $inventory) {
			if ($inventory->getHouseId() !== $checklist->getHouseId()) {
				continue;
			}
			foreach ($this->itemsOf((int)$inventory->getId()) as $item) {
				if ($this->canMatch($item) && ScopedTaxonomyService::nameKey($item->getName()) === $key) {
					$this->match($item, $inventory);
				}
			}
		}
	}

	/**
	 * An item moved from one list to another. `$item` already carries its new
	 * list.
	 */
	public function onItemMoved(ChecklistItem $item, Checklist $from, Checklist $to): void {
		$itemId = (int)$item->getId();
		if ($from->getKind() !== $to->getKind()) {
			if ($from->getKind() === Checklist::KIND_INVENTORY && $item->getLinkedItemId() !== null) {
				$item->setLinkedItemId(null);
				$item->setLinkedAt(null);
				$this->itemMapper->update($item);
			}
			if ($from->isChecklist()) {
				$this->itemMapper->clearLinksTo([$itemId]);
			}
			match ($to->getKind()) {
				Checklist::KIND_INVENTORY => $this->match($item, $to),
				Checklist::KIND_CHECKLIST => $this->onChecklistRowSaved($item, $to),
				default => null,
			};
			return;
		}
		if ($to->isChecklist()) {
			$this->onChecklistRowSaved($item, $to);
			return;
		}
		if ($to->getKind() !== Checklist::KIND_INVENTORY) {
			return;
		}
		$rowId = $item->getLinkedItemId();
		if ($rowId === null) {
			$this->match($item, $to);
			return;
		}
		foreach ($this->itemMapper->findLinkedTo([$rowId]) as $other) {
			if ($other->getListId() === $item->getListId() && (int)$other->getId() !== $itemId) {
				$item->setLinkedItemId(null);
				$item->setLinkedAt(null);
				$this->itemMapper->update($item);
				return;
			}
		}
	}

	// ----- Reading -----

	/**
	 * What each inventory item's link fields read: its auto-link switch, the row
	 * it is linked to, and, while it auto-links unlinked and several rows match,
	 * the candidates to pick from.
	 *
	 * @param ChecklistItem[] $items Items of inventories.
	 * @return array<int, array{autoLink: bool, stockLink: StockLinkRow|null, linkCandidates: list<StockLinkRow>}>
	 */
	public function describe(array $items): array {
		$rows = [];
		$rowIds = array_values(array_filter(array_map(static fn (ChecklistItem $i): ?int => $i->getLinkedItemId(), $items)));
		foreach ($this->itemMapper->findByIds($rowIds) as $row) {
			$rows[(int)$row->getId()] = $row;
		}

		/** @var array<int, array{rows: array<string, list<ChecklistItem>>, linked: array<int, mixed>}|null> $byInventory */
		$byInventory = [];
		$out = [];
		foreach ($items as $item) {
			$rowId = $item->getLinkedItemId();
			$row = $rowId !== null ? ($rows[$rowId] ?? null) : null;
			$candidates = [];
			if ($row === null && $this->canMatch($item)) {
				$inventoryId = $item->getListId();
				if (!array_key_exists($inventoryId, $byInventory)) {
					$inventory = $this->list($inventoryId);
					$restock = $inventory !== null ? $this->restockChecklist($inventory) : null;
					$byInventory[$inventoryId] = $restock === null ? null : [
						'rows' => $this->rowsByName($restock),
						'linked' => array_flip($this->itemMapper->findLinkedIdsFromList($inventoryId)),
					];
				}
				$context = $byInventory[$inventoryId];
				if ($context !== null) {
					$candidates = $this->candidatesFrom($item, $context['rows'], $context['linked']);
				}
			}
			$out[(int)$item->getId()] = [
				'autoLink' => $item->autoLinks(),
				'stockLink' => $row !== null ? $this->rowSummary($row) : null,
				'linkCandidates' => count($candidates) > 1 ? array_map($this->rowSummary(...), $candidates) : [],
			];
		}
		return $out;
	}

	/**
	 * The inventory items each checklist item restocks, earliest linked first.
	 * Items in the trash are left out.
	 *
	 * @param ChecklistItem[] $rows
	 * @return array<int, list<StockLinkSource>>
	 */
	public function restocksFor(array $rows): array {
		$out = [];
		foreach ($this->itemMapper->findLinkedTo(array_map(static fn (ChecklistItem $r): int => (int)$r->getId(), $rows)) as $item) {
			$inventory = $this->list($item->getListId());
			if ($item->getDeletedAt() !== null || $inventory === null || $inventory->getDeletedAt() !== null) {
				continue;
			}
			$out[(int)$item->getLinkedItemId()][] = [
				'itemId' => (int)$item->getId(),
				'name' => $item->getName(),
				'listId' => (int)$inventory->getId(),
				'listName' => $inventory->getName(),
			];
		}
		return $out;
	}

	/**
	 * Rows an inventory item can be linked to by hand: live and archived items
	 * on the house's active checklists that `$canEdit` allows, leaving out rows
	 * already linked from the inventory. Sorted by name, then checklist.
	 *
	 * @param callable(Checklist): bool $canEdit
	 * @return list<StockLinkRow>
	 */
	public function linkTargets(Checklist $inventory, callable $canEdit, string $search = '', int $limit = 50): array {
		$needle = ScopedTaxonomyService::nameKey($search);
		$linked = array_flip($this->itemMapper->findLinkedIdsFromList((int)$inventory->getId()));
		$rows = [];
		foreach ($this->listMapper->findByHouse($inventory->getHouseId(), 'custom', Checklist::KIND_CHECKLIST) as $checklist) {
			$this->lists[(int)$checklist->getId()] = $checklist;
			if (!$canEdit($checklist)) {
				continue;
			}
			foreach ($this->itemsOf((int)$checklist->getId()) as $row) {
				if (isset($linked[(int)$row->getId()])) {
					continue;
				}
				if ($needle !== '' && !str_contains(ScopedTaxonomyService::nameKey($row->getName()), $needle)) {
					continue;
				}
				$rows[] = $row;
			}
		}
		usort($rows, function (ChecklistItem $a, ChecklistItem $b): int {
			return [ScopedTaxonomyService::nameKey($a->getName()), $this->list($a->getListId())?->getName() ?? '', $a->getId()]
				<=> [ScopedTaxonomyService::nameKey($b->getName()), $this->list($b->getListId())?->getName() ?? '', $b->getId()];
		});
		return array_map($this->rowSummary(...), array_slice($rows, 0, max(0, $limit)));
	}

	// ----- Helpers -----

	private function canMatch(ChecklistItem $item): bool {
		return $item->getLinkedItemId() === null && $item->autoLinks() && $item->getDeletedAt() === null;
	}

	/**
	 * Live and archived rows on the checklist, keyed by name.
	 *
	 * @return array<string, list<ChecklistItem>>
	 */
	private function rowsByName(Checklist $checklist): array {
		$byName = [];
		foreach ($this->itemsOf((int)$checklist->getId()) as $row) {
			$byName[ScopedTaxonomyService::nameKey($row->getName())][] = $row;
		}
		return $byName;
	}

	/**
	 * Same-name rows the item could link to, minus those already linked from
	 * its inventory, unchecked → checked → archived, oldest first.
	 *
	 * @param array<string, list<ChecklistItem>> $rowsByName
	 * @param array<int, mixed> $linked Row ids already linked from the inventory.
	 * @return list<ChecklistItem>
	 */
	private function candidatesFrom(ChecklistItem $item, array $rowsByName, array $linked): array {
		$candidates = array_values(array_filter(
			$rowsByName[ScopedTaxonomyService::nameKey($item->getName())] ?? [],
			static fn (ChecklistItem $row): bool => !isset($linked[(int)$row->getId()]),
		));
		usort($candidates, static fn (ChecklistItem $a, ChecklistItem $b): int => [self::STATE_RANK[self::state($a)], $a->getCreatedAt(), $a->getId()]
			<=> [self::STATE_RANK[self::state($b)], $b->getCreatedAt(), $b->getId()]);
		return $candidates;
	}

	/**
	 * Live and archived items on a list.
	 *
	 * @return ChecklistItem[]
	 */
	private function itemsOf(int $listId): array {
		return [...$this->itemMapper->findByList($listId), ...$this->itemMapper->findArchivedByList($listId)];
	}

	/**
	 * @return 'open'|'checked'|'archived'|'trashed'
	 */
	private static function state(ChecklistItem $row): string {
		if ($row->getDeletedAt() !== null) {
			return self::STATE_TRASHED;
		}
		if ($row->getArchivedAt() !== null) {
			return self::STATE_ARCHIVED;
		}
		return $row->getDone() ? self::STATE_CHECKED : self::STATE_OPEN;
	}

	/**
	 * @return StockLinkRow
	 */
	private function rowSummary(ChecklistItem $row): array {
		$list = $this->list($row->getListId());
		return [
			'itemId' => (int)$row->getId(),
			'name' => $row->getName(),
			'quantity' => $row->getQuantity(),
			'listId' => $row->getListId(),
			'listName' => $list?->getName() ?? '',
			'listColor' => $list?->getColor(),
			'state' => self::state($row),
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

	private function requireList(int $listId): Checklist {
		return $this->list($listId) ?? throw new NotFoundException('List not found');
	}
}
