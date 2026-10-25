<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Activity\ActivityPublisher;
use OCA\Pantry\Db\Checklist;
use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Db\ChecklistItemMapper;
use OCA\Pantry\Db\ChecklistMapper;
use OCA\Pantry\Db\House;
use OCA\Pantry\Service\ChecklistService;
use OCA\Pantry\Service\HouseService;
use OCA\Pantry\Service\ImageService;
use OCA\Pantry\Service\NotificationService;
use OCA\Pantry\Service\RestockSyncService;
use OCA\Pantry\Service\StockLinkService;
use OCA\Pantry\Service\StockRules;
use OCA\Pantry\Service\TemplateCopyService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Runs the sync against an in-memory house: a "Shopping" checklist (10), a
 * "Groceries" checklist (11), and "Kitchen" (20) and "Garage" (21)
 * inventories restocking into Shopping.
 */
class RestockSyncServiceTest extends TestCase {
	/** @var ChecklistMapper&MockObject */
	private ChecklistMapper $listMapper;
	/** @var ChecklistItemMapper&MockObject */
	private ChecklistItemMapper $itemMapper;
	/** @var ActivityPublisher&MockObject */
	private ActivityPublisher $activity;
	/** @var NotificationService&MockObject */
	private NotificationService $notifications;
	private StockLinkService $links;
	private RestockSyncService $svc;

	/** @var array<int, Checklist> */
	private array $lists = [];
	/** @var array<int, ChecklistItem> */
	private array $items = [];
	private int $nextItemId = 100;
	private int $clock = 1000;
	/** @var list<string> Locks taken and reads made, in order. */
	private array $log = [];
	/** @var array<string, callable(): void> What another request does while this one waits for a lock, by lock. */
	private array $whileWaiting = [];

	protected function setUp(): void {
		$this->listMapper = $this->createMock(ChecklistMapper::class);
		$this->itemMapper = $this->createMock(ChecklistItemMapper::class);
		$this->activity = $this->createMock(ActivityPublisher::class);
		$this->notifications = $this->createMock(NotificationService::class);

		$this->addList(10, Checklist::KIND_CHECKLIST, 'Shopping');
		$this->addList(11, Checklist::KIND_CHECKLIST, 'Groceries');
		$this->addList(20, Checklist::KIND_INVENTORY, 'Kitchen', restockInto: 10);
		$this->addList(21, Checklist::KIND_INVENTORY, 'Garage', restockInto: 10);

		$this->listMapper->method('findById')->willReturnCallback(function (int $id): Checklist {
			return $this->lists[$id] ?? throw new DoesNotExistException('missing');
		});
		$this->listMapper->method('findRestockingInto')->willReturnCallback(fn (int $id): array => array_values(array_filter(
			$this->lists,
			static fn (Checklist $l): bool => $l->getKind() === Checklist::KIND_INVENTORY && $l->getRestockChecklistId() === $id,
		)));
		$this->listMapper->method('lock')->willReturnCallback(fn (int $id) => $this->lock('list ' . $id));
		$this->itemMapper->method('lock')->willReturnCallback(fn (int $id) => $this->lock('item ' . $id));
		$this->itemMapper->method('findById')->willReturnCallback(function (int $id, bool $includeDeleted = false): ChecklistItem {
			$item = $this->items[$id] ?? null;
			if ($item === null || (!$includeDeleted && $item->getDeletedAt() !== null)) {
				throw new DoesNotExistException('missing');
			}
			return $item;
		});
		$this->itemMapper->method('findByList')->willReturnCallback(fn (int $listId): array => $this->itemsWhere(
			static fn (ChecklistItem $i): bool => $i->getListId() === $listId && $i->getDeletedAt() === null && $i->getArchivedAt() === null,
		));
		$this->itemMapper->method('findArchivedByList')->willReturnCallback(fn (int $listId): array => $this->itemsWhere(
			static fn (ChecklistItem $i): bool => $i->getListId() === $listId && $i->getArchivedAt() !== null && $i->getDeletedAt() === null,
		));
		$this->itemMapper->method('findLinkedTo')->willReturnCallback(function (array $ids): array {
			$this->log[] = 'read links of ' . implode(',', $ids);
			$linked = $this->itemsWhere(static fn (ChecklistItem $i): bool => $i->getLinkedItemId() !== null && in_array($i->getLinkedItemId(), $ids, true));
			usort($linked, static fn (ChecklistItem $a, ChecklistItem $b): int => [$a->getLinkedAt(), $a->getId()] <=> [$b->getLinkedAt(), $b->getId()]);
			return $linked;
		});
		$this->itemMapper->method('findLinkedIdsFromList')->willReturnCallback(fn (int $listId): array => array_values(array_unique(array_map(
			static fn (ChecklistItem $i): int => (int)$i->getLinkedItemId(),
			$this->itemsWhere(static fn (ChecklistItem $i): bool => $i->getListId() === $listId && $i->getLinkedItemId() !== null),
		))));
		$this->itemMapper->method('update')->willReturnArgument(0);

		$this->links = new StockLinkService($this->listMapper, $this->itemMapper);

		$checklists = $this->createMock(ChecklistService::class);
		$checklists->method('systemDone')->willReturnCallback(function (ChecklistItem $row): ChecklistItem {
			$row->setDone(true);
			$row->setDoneBy(null);
			$row->setQuantityPinned(false);
			$row->setMemberReopened(false);
			if ($row->getDeleteOnDone()) {
				$row->setDeletedAt($this->clock);
			}
			return $row;
		});
		$checklists->method('reviveForRestock')->willReturnCallback(function (ChecklistItem $row): ChecklistItem {
			$row->setArchivedAt(null);
			$row->setDone(false);
			$row->setDoneBy(null);
			return $row;
		});

		$templateCopy = $this->createMock(TemplateCopyService::class);
		$templateCopy->method('copyForRestock')->willReturnCallback(function (ChecklistItem $source, Checklist $inventory, Checklist $checklist, ?string $addedBy, ?string $quantity): ChecklistItem {
			$row = $this->addItem((int)$checklist->getId(), $source->getName(), ['quantity' => $quantity]);
			$row->setAddedBy($addedBy);
			// Adding a row pairs same-name items of inventories restocking into it.
			$this->links->onChecklistRowSaved($row, $checklist);
			return $row;
		});

		$house = new House();
		$house->setName('Home');
		$houses = $this->createMock(HouseService::class);
		$houses->method('get')->willReturn($house);

		$this->svc = new RestockSyncService(
			$this->listMapper,
			$this->itemMapper,
			$checklists,
			$this->links,
			$templateCopy,
			$this->createMock(ImageService::class),
			$this->activity,
			$this->notifications,
			$houses,
			$this->createMock(IDBConnection::class),
			$this->createMock(LoggerInterface::class),
		);
	}

	private function lock(string $what): void {
		$this->log[] = 'lock ' . $what;
		$other = $this->whileWaiting[$what] ?? null;
		if ($other !== null) {
			unset($this->whileWaiting[$what]);
			$other();
		}
	}

	private function addList(int $id, string $kind, string $name, ?int $restockInto = null): Checklist {
		$list = new Checklist();
		$list->setId($id);
		$list->setHouseId(1);
		$list->setKind($kind);
		$list->setName($name);
		$list->setRestockChecklistId($restockInto);
		return $this->lists[$id] = $list;
	}

	/**
	 * @param array<string, mixed> $state
	 */
	private function addItem(int $listId, string $name, array $state = []): ChecklistItem {
		$item = new ChecklistItem();
		$item->setId($this->nextItemId++);
		$item->setListId($listId);
		$item->setName($name);
		$item->setQuantity($state['quantity'] ?? null);
		$item->setDone($state['done'] ?? false);
		$item->setDoneBy($state['doneBy'] ?? null);
		$item->setArchivedAt($state['archivedAt'] ?? null);
		$item->setDeletedAt($state['deletedAt'] ?? null);
		$item->setCreatedAt($state['createdAt'] ?? 0);
		$item->setDeleteOnDone($state['deleteOnDone'] ?? false);
		$item->setQuantityPinned($state['pinned'] ?? false);
		$item->setMemberReopened($state['reopened'] ?? false);
		$item->setStockMode($state['stockMode'] ?? null);
		$item->setStockLevel($state['stockLevel'] ?? null);
		$item->setTarget($state['target'] ?? null);
		$item->setReorderPoint($state['reorderPoint'] ?? null);
		if (isset($state['linkedTo'])) {
			$item->setLinkedItemId($state['linkedTo']);
			$item->setLinkedAt($state['linkedAt'] ?? $this->clock++);
		}
		if (array_key_exists('autoLink', $state)) {
			$item->setAutoLink($state['autoLink']);
		}
		return $this->items[(int)$item->getId()] = $item;
	}

	/** A count item: `$onHand` of a target of 12 eggs. */
	private function eggs(int $inventoryId, string $onHand, array $state = []): ChecklistItem {
		return $this->addItem($inventoryId, 'Eggs', $state + ['stockMode' => StockRules::MODE_COUNT, 'quantity' => $onHand, 'target' => '12 eggs']);
	}

	/**
	 * Set on-hand and sync, as a stock write does.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function write(ChecklistItem $item, string $onHand, ?string $uid = 'alice'): array {
		$before = RestockSyncService::snapshot($item);
		$item->setQuantity($onHand);
		return $this->svc->afterWrite($item, $before, false, $uid);
	}

	/**
	 * @param callable(ChecklistItem): bool $filter
	 * @return list<ChecklistItem>
	 */
	private function itemsWhere(callable $filter): array {
		return array_values(array_filter($this->items, $filter));
	}

	/**
	 * @return list<ChecklistItem>
	 */
	private function rowsOn(int $listId): array {
		return $this->itemsWhere(static fn (ChecklistItem $i): bool => $i->getListId() === $listId);
	}

	// ----- Only through a link -----

	public function testAnUnlinkedLowItemAddsItsRowToTheRestockChecklist(): void {
		$eggs = $this->eggs(20, '12 eggs');
		$this->activity->expects($this->once())->method('publishItemAdded')
			->with(1, 'Home', 'alice', $this->anything(), 'Eggs', 10, 'Shopping');
		$this->notifications->expects($this->once())->method('notifyItemAdded')->with(1, 'alice', 'Eggs', 'Shopping');

		$events = $this->write($eggs, '4 eggs');

		$rows = $this->rowsOn(10);
		$this->assertCount(1, $rows);
		$this->assertSame('8 eggs', $rows[0]->getQuantity());
		$this->assertSame('alice', $rows[0]->getAddedBy());
		$this->assertSame($rows[0]->getId(), $eggs->getLinkedItemId());
		$this->assertSame([[
			'event' => 'added',
			'itemId' => $eggs->getId(),
			'rowId' => $rows[0]->getId(),
			'rowName' => 'Eggs',
			'quantity' => '8 eggs',
			'listId' => 10,
			'listName' => 'Shopping',
		]], $events);
	}

	public function testAnUnlinkedLowItemLinksTheOneSameNameRow(): void {
		$row = $this->addItem(10, 'eggs', ['quantity' => '2']);
		$eggs = $this->eggs(20, '12 eggs');

		$events = $this->write($eggs, '4 eggs');

		$this->assertSame($row->getId(), $eggs->getLinkedItemId());
		$this->assertSame('8 eggs', $row->getQuantity());
		$this->assertCount(1, $this->rowsOn(10));
		$this->assertSame('changed', $events[0]['event']);
	}

	public function testSeveralSameNameRowsWaitForAPick(): void {
		$this->addItem(10, 'Eggs');
		$this->addItem(10, 'Eggs', ['done' => true]);
		$eggs = $this->eggs(20, '12 eggs');

		$this->assertSame([], $this->write($eggs, '4 eggs'));
		$this->assertNull($eggs->getLinkedItemId());
		$this->assertCount(2, $this->rowsOn(10));
	}

	public function testAnItemWithAutoLinkOffIsOnlyFlagged(): void {
		$eggs = $this->eggs(20, '12 eggs', ['autoLink' => false]);

		$this->assertSame([], $this->write($eggs, '4 eggs'));
		$this->assertSame([], $this->rowsOn(10));
	}

	public function testWithoutARestockChecklistAnUnlinkedItemIsOnlyFlagged(): void {
		$this->lists[20]->setRestockChecklistId(null);
		$eggs = $this->eggs(20, '12 eggs');

		$this->assertSame([], $this->write($eggs, '4 eggs'));
		$this->assertSame([], $this->rowsOn(10));
	}

	public function testALinkedItemUsesItsRowOnAnyChecklist(): void {
		$row = $this->addItem(11, 'Eggs', ['quantity' => '2']);
		$eggs = $this->eggs(20, '12 eggs', ['linkedTo' => $row->getId()]);

		$this->write($eggs, '4 eggs');

		$this->assertSame('8 eggs', $row->getQuantity());
		$this->assertSame([], $this->rowsOn(10));
	}

	public function testAWriteThatLeavesStatusAndShortfallAloneSyncsNothing(): void {
		$row = $this->addItem(10, 'Eggs', ['quantity' => '1', 'done' => true, 'doneBy' => 'bob']);
		$eggs = $this->eggs(20, '4 eggs', ['linkedTo' => $row->getId()]);

		$this->assertSame([], $this->svc->afterWrite($eggs, RestockSyncService::snapshot($eggs), false, 'alice'));
		$this->assertTrue($row->getDone());
		$this->assertSame('1', $row->getQuantity());
	}

	public function testANewLinkSyncsEvenWithoutAStockChange(): void {
		$row = $this->addItem(11, 'Eggs', ['done' => true, 'doneBy' => 'bob']);
		$eggs = $this->eggs(20, '4 eggs', ['linkedTo' => $row->getId()]);

		$events = $this->svc->afterWrite($eggs, RestockSyncService::snapshot($eggs), true, 'alice');

		$this->assertFalse($row->getDone());
		$this->assertSame('revived', $events[0]['event']);
	}

	public function testANewItemThatIsLowSyncs(): void {
		$eggs = $this->eggs(20, '4 eggs');

		$events = $this->svc->afterWrite($eggs, null, false, 'alice');

		$this->assertSame('added', $events[0]['event']);
	}

	public function testATrashedOrArchivedItemDoesNotSync(): void {
		$eggs = $this->eggs(20, '12 eggs', ['archivedAt' => 5]);

		$this->assertSame([], $this->write($eggs, '4 eggs'));
		$this->assertSame([], $this->rowsOn(10));
	}

	// ----- Reviving the row -----

	public function testAnOpenRowHasItsQuantityUpdated(): void {
		$row = $this->addItem(10, 'Eggs', ['quantity' => '8 eggs']);
		$eggs = $this->eggs(20, '4 eggs', ['linkedTo' => $row->getId()]);

		$events = $this->write($eggs, '2 eggs');

		$this->assertSame('10 eggs', $row->getQuantity());
		$this->assertFalse($row->getDone());
		$this->assertSame('changed', $events[0]['event']);
		$this->assertSame('10 eggs', $events[0]['quantity']);
	}

	public function testACheckedRowIsUnchecked(): void {
		$row = $this->addItem(10, 'Eggs', ['quantity' => '8 eggs', 'done' => true, 'doneBy' => 'bob']);
		$eggs = $this->eggs(20, '12 eggs', ['linkedTo' => $row->getId()]);
		$this->activity->expects($this->once())->method('publishItemReopened')
			->with(1, 'Home', 'alice', $row->getId(), 'Eggs', 10, 'Shopping');
		$this->notifications->expects($this->once())->method('notifyItemAdded')->with(1, 'alice', 'Eggs', 'Shopping');

		$events = $this->write($eggs, '6 eggs');

		$this->assertFalse($row->getDone());
		$this->assertSame('6 eggs', $row->getQuantity());
		$this->assertSame('revived', $events[0]['event']);
	}

	public function testAnArchivedRowIsUnarchivedAndUnchecked(): void {
		$row = $this->addItem(10, 'Eggs', ['done' => true, 'doneBy' => 'bob', 'archivedAt' => 5]);
		$eggs = $this->eggs(20, '12 eggs', ['linkedTo' => $row->getId()]);

		$events = $this->write($eggs, '6 eggs');

		$this->assertNull($row->getArchivedAt());
		$this->assertFalse($row->getDone());
		$this->assertSame('revived', $events[0]['event']);
	}

	/**
	 * @return array<string, array{0: callable(Checklist, ChecklistItem): void}>
	 */
	public static function trashedRows(): array {
		return [
			'row in the trash' => [static fn (Checklist $l, ChecklistItem $r) => $r->setDeletedAt(5)],
			'checklist in the trash' => [static fn (Checklist $l, ChecklistItem $r) => $l->setDeletedAt(5)],
			'checklist archived' => [static fn (Checklist $l, ChecklistItem $r) => $l->setArchivedAt(5)],
		];
	}

	/**
	 * @param callable(Checklist, ChecklistItem): void $trash
	 */
	#[DataProvider('trashedRows')]
	public function testATrashedRowIsReplacedOnTheRestockChecklistAndTheLinkMoves(callable $trash): void {
		$old = $this->addItem(11, 'Eggs', ['quantity' => '2']);
		$trash($this->lists[11], $old);
		$eggs = $this->eggs(20, '12 eggs', ['linkedTo' => $old->getId(), 'autoLink' => false]);

		$events = $this->write($eggs, '4 eggs');

		$rows = $this->rowsOn(10);
		$this->assertCount(1, $rows);
		$this->assertSame($rows[0]->getId(), $eggs->getLinkedItemId());
		$this->assertSame('8 eggs', $rows[0]->getQuantity());
		$this->assertSame('2', $old->getQuantity());
		$this->assertSame('added', $events[0]['event']);
	}

	public function testATrashedRowWithoutARestockChecklistIsOnlyFlagged(): void {
		$this->lists[20]->setRestockChecklistId(null);
		$old = $this->addItem(11, 'Eggs', ['deletedAt' => 5]);
		$eggs = $this->eggs(20, '12 eggs', ['linkedTo' => $old->getId()]);

		$this->assertSame([], $this->write($eggs, '4 eggs'));
		$this->assertSame($old->getId(), $eggs->getLinkedItemId());
	}

	// ----- Quantity -----

	public function testALevelItemAsksForItsRestockAmount(): void {
		$row = $this->addItem(10, 'Flour', ['quantity' => '1']);
		$flour = $this->addItem(20, 'Flour', ['stockMode' => StockRules::MODE_LEVEL, 'stockLevel' => StockRules::LEVEL_FULL, 'quantity' => '1 kg bag', 'linkedTo' => $row->getId()]);

		$before = RestockSyncService::snapshot($flour);
		$flour->setStockLevel(StockRules::LEVEL_LOW);
		$this->svc->afterWrite($flour, $before, false, 'alice');

		$this->assertSame('1 kg bag', $row->getQuantity());
	}

	public function testALevelItemWithoutARestockAmountLeavesTheQuantity(): void {
		$row = $this->addItem(10, 'Flour', ['quantity' => '2 bags', 'done' => true, 'doneBy' => 'bob']);
		$flour = $this->addItem(20, 'Flour', ['stockMode' => StockRules::MODE_LEVEL, 'stockLevel' => StockRules::LEVEL_FULL, 'linkedTo' => $row->getId()]);

		$before = RestockSyncService::snapshot($flour);
		$flour->setStockLevel(StockRules::LEVEL_OUT);
		$events = $this->svc->afterWrite($flour, $before, false, 'alice');

		$this->assertFalse($row->getDone());
		$this->assertSame('2 bags', $row->getQuantity());
		$this->assertSame('revived', $events[0]['event']);
	}

	public function testASharedRowSumsEveryLowItemInTheWordingOfTheFirstToLink(): void {
		$this->addList(22, Checklist::KIND_INVENTORY, 'Cellar', restockInto: 10);
		$this->addList(23, Checklist::KIND_INVENTORY, 'Shed', restockInto: 10);
		$row = $this->addItem(10, 'Eggs');
		$this->addItem(21, 'Eggs', ['stockMode' => StockRules::MODE_COUNT, 'quantity' => '1', 'target' => '3', 'linkedTo' => $row->getId(), 'linkedAt' => 2]);
		$this->addItem(22, 'Eggs', ['stockMode' => StockRules::MODE_COUNT, 'quantity' => '6', 'target' => '6', 'linkedTo' => $row->getId(), 'linkedAt' => 3]);
		$this->addItem(23, 'Eggs', ['stockMode' => StockRules::MODE_LEVEL, 'stockLevel' => StockRules::LEVEL_LOW, 'linkedTo' => $row->getId(), 'linkedAt' => 4]);
		$eggs = $this->eggs(20, '12 eggs', ['linkedTo' => $row->getId(), 'linkedAt' => 1]);

		$this->write($eggs, '4 eggs');

		// 8 (Kitchen) + 2 (Garage); Cellar is full, and Shed's level item has no number.
		$this->assertSame('10 eggs', $row->getQuantity());
	}

	public function testAShortfallWithoutANumberAddsNothing(): void {
		$row = $this->addItem(10, 'Eggs');
		$this->addItem(21, 'Eggs', ['stockMode' => StockRules::MODE_LEVEL, 'stockLevel' => StockRules::LEVEL_LOW, 'quantity' => 'a box', 'linkedTo' => $row->getId(), 'linkedAt' => 1]);
		$eggs = $this->eggs(20, '12 eggs', ['linkedTo' => $row->getId(), 'linkedAt' => 2]);

		$this->write($eggs, '4 eggs');

		$this->assertSame('8 eggs', $row->getQuantity());
	}

	// ----- Pinned quantity -----

	public function testAPinnedQuantityIsLeftAlone(): void {
		$row = $this->addItem(10, 'Eggs', ['quantity' => '2 dozen', 'pinned' => true]);
		$eggs = $this->eggs(20, '4 eggs', ['linkedTo' => $row->getId()]);

		$events = $this->write($eggs, '2 eggs');

		$this->assertSame('2 dozen', $row->getQuantity());
		$this->assertSame('pinned', $events[0]['event']);
		$this->assertSame('2 dozen', $events[0]['quantity']);
	}

	public function testResyncingARowSetsWhatStockAsksFor(): void {
		$row = $this->addItem(10, 'Eggs', ['quantity' => '2 dozen']);
		$this->eggs(20, '4 eggs', ['linkedTo' => $row->getId()]);

		$events = $this->svc->resyncRow($row);

		$this->assertSame('8 eggs', $row->getQuantity());
		$this->assertSame('changed', $events[0]['event']);
	}

	public function testResyncingARowNobodyLinksChangesNothing(): void {
		$row = $this->addItem(10, 'Eggs', ['quantity' => '2 dozen']);

		$this->assertSame([], $this->svc->resyncRow($row));
		$this->assertSame('2 dozen', $row->getQuantity());
	}

	// ----- Recovery -----

	public function testStockRecoveringTicksTheRowOffAsNoLongerNeeded(): void {
		$row = $this->addItem(10, 'Eggs', ['quantity' => '8 eggs']);
		$eggs = $this->eggs(20, '4 eggs', ['linkedTo' => $row->getId()]);
		$this->activity->expects($this->never())->method('publishItemDone');

		$events = $this->write($eggs, '12 eggs');

		$this->assertTrue($row->getDone());
		$this->assertNull($row->getDoneBy());
		$this->assertSame('recovered', $events[0]['event']);
	}

	public function testRecoveringGoesThroughTheDonePath(): void {
		$row = $this->addItem(10, 'Eggs', ['quantity' => '8 eggs', 'deleteOnDone' => true]);
		$eggs = $this->eggs(20, '4 eggs', ['linkedTo' => $row->getId()]);

		$this->write($eggs, '12 eggs');

		$this->assertNotNull($row->getDeletedAt());
	}

	public function testARowAnotherLowItemStillNeedsOnlyDropsItsQuantity(): void {
		$row = $this->addItem(10, 'Eggs', ['quantity' => '10 eggs']);
		$eggs = $this->eggs(20, '4 eggs', ['linkedTo' => $row->getId(), 'linkedAt' => 1]);
		$this->addItem(21, 'Eggs', ['stockMode' => StockRules::MODE_COUNT, 'quantity' => '1', 'target' => '3', 'linkedTo' => $row->getId(), 'linkedAt' => 2]);

		$events = $this->write($eggs, '12 eggs');

		$this->assertFalse($row->getDone());
		$this->assertSame('2', $row->getQuantity());
		$this->assertSame('changed', $events[0]['event']);
	}

	public function testARowAMemberReopenedStaysOpen(): void {
		$row = $this->addItem(10, 'Eggs', ['quantity' => '8 eggs', 'reopened' => true]);
		$eggs = $this->eggs(20, '4 eggs', ['linkedTo' => $row->getId()]);

		$this->assertSame([], $this->write($eggs, '12 eggs'));
		$this->assertFalse($row->getDone());
	}

	public function testOnlyAWriteThatEndsTheNeedTicksTheRowOff(): void {
		$row = $this->addItem(10, 'Eggs', ['quantity' => '2']);
		$eggs = $this->eggs(20, '12 eggs', ['linkedTo' => $row->getId()]);

		// In stock already: a member added the row to buy more anyway.
		$this->svc->afterWrite($eggs, RestockSyncService::snapshot($eggs), true, 'alice');
		$this->write($eggs, '13 eggs');

		$this->assertFalse($row->getDone());
	}

	// ----- Back-fill -----

	public function testSettingTheRestockChecklistBackFillsLowItemsInTheAppsName(): void {
		$eggs = $this->eggs(20, '4 eggs');
		$milk = $this->addItem(20, 'Milk', ['stockMode' => StockRules::MODE_COUNT, 'quantity' => '2', 'target' => '2']);
		$this->activity->expects($this->once())->method('publishItemAdded')
			->with(1, 'Home', '', $this->anything(), 'Eggs', 10, 'Shopping');

		$this->svc->backfill($this->lists[20]);

		$rows = $this->rowsOn(10);
		$this->assertCount(1, $rows);
		$this->assertSame($rows[0]->getId(), $eggs->getLinkedItemId());
		$this->assertNull($rows[0]->getAddedBy());
		$this->assertNull($milk->getLinkedItemId());
	}

	public function testBackFillNeverTicksARowOff(): void {
		$row = $this->addItem(10, 'Milk', ['quantity' => '1']);
		$this->addItem(20, 'Milk', ['stockMode' => StockRules::MODE_COUNT, 'quantity' => '2', 'target' => '2', 'linkedTo' => $row->getId()]);

		$this->svc->backfill($this->lists[20]);

		$this->assertFalse($row->getDone());
	}

	public function testRestoringARestockChecklistBackFillsEveryInventoryRestockingIntoIt(): void {
		$kitchen = $this->eggs(20, '4 eggs');
		$garage = $this->addItem(21, 'Soda', ['stockMode' => StockRules::MODE_COUNT, 'quantity' => '0', 'target' => '6 cans']);

		$this->svc->backfillInto($this->lists[10]);

		$this->assertNotNull($kitchen->getLinkedItemId());
		$this->assertSame('6 cans', $this->items[(int)$garage->getLinkedItemId()]->getQuantity());
	}

	// ----- One sync per row at a time -----

	public function testASyncHoldsTheRowBeforeReadingWhatIsLinkedToIt(): void {
		$row = $this->addItem(10, 'Eggs');
		$eggs = $this->eggs(20, '12 eggs', ['linkedTo' => $row->getId()]);

		$this->write($eggs, '4 eggs');

		$this->assertSame(['lock item ' . $row->getId(), 'read links of ' . $row->getId()], array_slice($this->log, 0, 2));
	}

	public function testASharedRowSumsTheStockAnotherWriteLeftWhileThisOneWaited(): void {
		$row = $this->addItem(10, 'Eggs', ['quantity' => '8 eggs']);
		$kitchen = $this->eggs(20, '4 eggs', ['linkedTo' => $row->getId(), 'linkedAt' => 1]);
		$garage = $this->eggs(21, '12 eggs', ['linkedTo' => $row->getId(), 'linkedAt' => 2]);
		$this->whileWaiting['item ' . $row->getId()] = function () use ($garage): void {
			$this->write($garage, '9 eggs');
		};

		$this->write($kitchen, '2 eggs');

		// 10 from Kitchen and 3 from Garage, not the 10 + 0 read before waiting.
		$this->assertSame('13 eggs', $row->getQuantity());
	}

	public function testTwoItemsGoingLowAtOnceShareOneNewRow(): void {
		$kitchen = $this->eggs(20, '12 eggs');
		$garage = $this->eggs(21, '12 eggs');
		// Garage's write takes the restock checklist first and adds the row.
		$this->whileWaiting['list 10'] = function () use ($garage): void {
			$this->write($garage, '9 eggs');
		};

		$events = $this->write($kitchen, '4 eggs');

		$rows = $this->rowsOn(10);
		$this->assertCount(1, $rows);
		$this->assertSame($rows[0]->getId(), $kitchen->getLinkedItemId());
		$this->assertSame($rows[0]->getId(), $garage->getLinkedItemId());
		$this->assertSame('11 eggs', $rows[0]->getQuantity());
		// The row already asked for Kitchen's share by the time its write got to it.
		$this->assertSame([], $events);
	}

	public function testAWriteThatWaitedFindsItsItemAlreadyLinked(): void {
		$eggs = $this->eggs(20, '12 eggs');
		$this->whileWaiting['list 10'] = function () use ($eggs): void {
			$this->svc->afterWrite($eggs, null, false, 'bob');
		};

		$before = RestockSyncService::snapshot($eggs);
		$eggs->setQuantity('4 eggs');
		$this->svc->afterWrite($eggs, $before, false, 'alice');

		$this->assertCount(1, $this->rowsOn(10));
	}
}
