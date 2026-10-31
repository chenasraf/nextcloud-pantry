<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Db\Category;
use OCA\Pantry\Db\CategoryMapper;
use OCA\Pantry\Db\Checklist;
use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Db\ChecklistItemMapper;
use OCA\Pantry\Db\ChecklistMapper;
use OCA\Pantry\Service\ChecklistService;
use OCA\Pantry\Service\RestockService;
use OCA\Pantry\Service\RestockSyncService;
use OCA\Pantry\Service\StockRules;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Ticks and unticks row 9 on the "Shopping" checklist (10), which restocks
 * items of the "Kitchen" (20) and "Garage" (21) inventories.
 */
class RestockServiceTest extends TestCase {
	private const ROW = 9;

	/** @var RestockSyncService&MockObject */
	private RestockSyncService $sync;
	private RestockService $svc;

	/** @var array<int, ChecklistItem> */
	private array $items = [];
	private int $nextId = 30;
	/** @var list<array{int, array}> Inventory item and its stock before, for each sync the service ran. */
	private array $synced = [];
	/** @var array<string, mixed>|null What the last new inventory item was made from. */
	private ?array $added = null;

	protected function setUp(): void {
		$lists = [];
		foreach ([
			10 => ['Shopping', Checklist::KIND_CHECKLIST],
			11 => ['Errands', Checklist::KIND_CHECKLIST],
			20 => ['Kitchen', Checklist::KIND_INVENTORY],
			21 => ['Garage', Checklist::KIND_INVENTORY],
		] as $id => [$name, $kind]) {
			$list = new Checklist();
			$list->setId($id);
			$list->setHouseId(1);
			$list->setName($name);
			$list->setKind($kind);
			$lists[$id] = $list;
		}
		$listMapper = $this->createMock(ChecklistMapper::class);
		$listMapper->method('findById')->willReturnCallback(fn (int $id): Checklist => $lists[$id] ?? throw new DoesNotExistException('missing'));

		$itemMapper = $this->createMock(ChecklistItemMapper::class);
		$itemMapper->method('findById')->willReturnCallback(fn (int $id): ChecklistItem => $this->items[$id] ?? throw new DoesNotExistException('missing'));
		$itemMapper->method('update')->willReturnArgument(0);
		$itemMapper->method('findByList')->willReturnCallback(fn (int $listId): array => array_values(array_filter(
			$this->items,
			static fn (ChecklistItem $i): bool => $i->getListId() === $listId && $i->getDeletedAt() === null,
		)));

		$row = new ChecklistItem();
		$row->setId(self::ROW);
		$row->setListId(10);
		$row->setName('Eggs');
		$this->items[self::ROW] = $row;

		$checklists = $this->createMock(ChecklistService::class);
		$checklists->method('getItem')->willReturnCallback(fn (int $id): ChecklistItem => clone $this->items[$id]);
		$checklists->method('toggleItem')->willReturnCallback(function (int $id, string $uid): ChecklistItem {
			$item = $this->items[$id];
			$item->setDone(!$item->getDone());
			$item->setDoneBy($item->getDone() ? $uid : null);
			$item->setRestock(null);
			return $item;
		});
		$checklists->method('addItem')->willReturnCallback(function (int $listId, array $data, ?string $by): ChecklistItem {
			$this->added = $data;
			$item = new ChecklistItem();
			$item->setId($this->nextId++);
			$item->setListId($listId);
			$item->setName($data['name']);
			$item->setCategoryId($data['categoryId']);
			$item->setStockMode($data['stockMode']);
			$item->setTarget($data['target']);
			$item->setQuantity($data['quantity']);
			$this->items[(int)$item->getId()] = $item;
			return $item;
		});
		$categories = $this->createMock(CategoryMapper::class);
		$categories->method('findById')->willReturnCallback(static function (int $id): Category {
			$category = new Category();
			$category->setId($id);
			// Category 1 is house-wide; any other belongs to a single list.
			$category->setListId($id === 1 ? null : 10);
			return $category;
		});

		$this->sync = $this->createMock(RestockSyncService::class);
		$this->sync->method('contributors')->willReturnCallback(fn (ChecklistItem $row): array => array_values(array_filter(
			$this->items,
			static fn (ChecklistItem $i): bool => $i->getLinkedItemId() === (int)$row->getId() && $i->getDeletedAt() === null,
		)));
		$this->sync->method('snapshotOf')->willReturnCallback(static fn (ChecklistItem $i, ?int $today = null): array => RestockSyncService::snapshot($i, 2, $today));
		$this->sync->method('afterWrite')->willReturnCallback(function (ChecklistItem $item, ?array $before): array {
			$this->synced[] = [(int)$item->getId(), $before];
			return [];
		});

		$this->svc = new RestockService($checklists, $itemMapper, $listMapper, $this->sync, $this->createMock(IDBConnection::class), $categories);
	}

	private function row(): ChecklistItem {
		return $this->items[self::ROW];
	}

	private function counted(int $listId, ?string $onHand, string $target, ?string $reorderPoint = null): ChecklistItem {
		$item = $this->inventoryItem($listId);
		$item->setStockMode(StockRules::MODE_COUNT);
		$item->setQuantity($onHand);
		$item->setTarget($target);
		$item->setReorderPoint($reorderPoint);
		return $item;
	}

	private function level(int $listId, string $level, ?string $restockAmount = null): ChecklistItem {
		$item = $this->inventoryItem($listId);
		$item->setStockMode(StockRules::MODE_LEVEL);
		$item->setStockLevel($level);
		$item->setQuantity($restockAmount);
		return $item;
	}

	/** Linked to the row after every item made before it. */
	private function inventoryItem(int $listId): ChecklistItem {
		$item = new ChecklistItem();
		$item->setId($this->nextId++);
		$item->setListId($listId);
		$item->setName('Eggs');
		$item->setLinkedItemId(self::ROW);
		$item->setLinkedAt($item->getId());
		$this->items[(int)$item->getId()] = $item;
		return $item;
	}

	private function tick(?string $quantity): array {
		$this->row()->setQuantity($quantity);
		return $this->svc->toggle(self::ROW, 'alice');
	}

	// ----- Amount -----

	public function testATickPutsTheRowsNumberIntoStock(): void {
		$eggs = $this->counted(20, '4 eggs', '12 eggs');

		$result = $this->tick('8 eggs');

		$this->assertSame('12 eggs', $eggs->getQuantity());
		$this->assertSame([[
			'itemId' => (int)$eggs->getId(),
			'listId' => 20,
			'itemName' => 'Eggs',
			'listName' => 'Kitchen',
			'added' => '8',
			'previousLevel' => null,
			'replaced' => false,
			'previousOnHand' => null,
			'previousExpiresAt' => null,
			'expiresAt' => null,
		]], $result['restocked']);
		$this->assertSame($result['restocked'], $this->row()->getRestock());
	}

	public function testAQuantitySetByHandIsWhatWasBought(): void {
		$eggs = $this->counted(20, '4', '12');

		$this->tick('2 dozen');

		$this->assertSame('6', $eggs->getQuantity());
	}

	public function testABiggerPackGoesPastTheTarget(): void {
		$eggs = $this->counted(20, '4', '12');

		$this->tick('30');

		$this->assertSame('34', $eggs->getQuantity());
	}

	public function testABlankOnHandTakesTheTargetsWording(): void {
		$eggs = $this->counted(20, null, '12 eggs');

		$this->tick('12 eggs');

		$this->assertSame('12 eggs', $eggs->getQuantity());
	}

	public function testNoNumberFillsEverythingThatNeedsIt(): void {
		$kitchen = $this->counted(20, '4', '12');
		$garage = $this->counted(21, '0', '6');
		$milk = $this->level(20, StockRules::LEVEL_OUT);
		$fine = $this->counted(21, '10', '10');

		$result = $this->tick(null);

		$this->assertSame('12', $kitchen->getQuantity());
		$this->assertSame('6', $garage->getQuantity());
		$this->assertSame(StockRules::LEVEL_FULL, $milk->getStockLevel());
		$this->assertSame('10', $fine->getQuantity());
		$this->assertSame(['8', '6', null], array_column($result['restocked'], 'added'));
	}

	public function testALevelItemGoesToFullAndRemembersItsLevel(): void {
		$milk = $this->level(20, StockRules::LEVEL_LOW, '2 l');

		$result = $this->tick('2 l');

		$this->assertSame(StockRules::LEVEL_FULL, $milk->getStockLevel());
		$this->assertSame(StockRules::LEVEL_LOW, $result['restocked'][0]['previousLevel']);
		$this->assertNull($result['restocked'][0]['added']);
	}

	// ----- Shared rows -----

	public function testASharedRowFillsInLinkOrder(): void {
		$kitchen = $this->counted(20, '2', '12');
		$garage = $this->counted(21, '0', '6');

		$this->tick('12');

		$this->assertSame('12', $kitchen->getQuantity());
		$this->assertSame('2', $garage->getQuantity());
	}

	public function testWhatIsLeftOverGoesToTheFirstThatNeededIt(): void {
		$fine = $this->counted(20, '10', '10');
		$kitchen = $this->counted(20, '2', '12');
		$garage = $this->counted(21, '0', '6');

		$this->tick('20');

		$this->assertSame('10', $fine->getQuantity());
		$this->assertSame('16', $kitchen->getQuantity());
		$this->assertSame('6', $garage->getQuantity());
	}

	public function testWithNothingNeedingItAllGoesToTheEarliestLinked(): void {
		$kitchen = $this->counted(20, '12', '12');
		$garage = $this->counted(21, '6', '6');

		$this->tick('5');

		$this->assertSame('17', $kitchen->getQuantity());
		$this->assertSame('6', $garage->getQuantity());
	}

	public function testAnUncountedItemIsSkippedAndItsSharePassesOn(): void {
		$some = $this->counted(20, 'some', '12');
		$garage = $this->counted(21, '6', '6');

		$this->tick('4');

		$this->assertSame('some', $some->getQuantity());
		$this->assertSame('10', $garage->getQuantity());
	}

	public function testALevelItemTakesItsShareBeforeTheNext(): void {
		$milk = $this->level(20, StockRules::LEVEL_OUT, '2');
		$garage = $this->counted(21, '0', '6');

		$this->tick('5');

		$this->assertSame(StockRules::LEVEL_FULL, $milk->getStockLevel());
		$this->assertSame('3', $garage->getQuantity());
	}

	public function testAnItemOutOfTheNumberIsLeftAlone(): void {
		$kitchen = $this->counted(20, '0', '12');
		$garage = $this->counted(21, '0', '6');

		$result = $this->tick('10');

		$this->assertSame('10', $kitchen->getQuantity());
		$this->assertSame('0', $garage->getQuantity());
		$this->assertCount(1, $result['restocked']);
	}

	// ----- Sync -----

	public function testEveryRestockedItemIsSynced(): void {
		$kitchen = $this->counted(20, '2', '12');
		$garage = $this->counted(21, '0', '6');

		$this->tick('12');

		$this->assertSame([(int)$kitchen->getId(), (int)$garage->getId()], array_column($this->synced, 0));
		$this->assertTrue($this->synced[1][1]['needs']);
	}

	public function testAnUnlinkedRowRestocksNothing(): void {
		$this->sync->expects($this->never())->method('afterWrite');

		$result = $this->tick('8');

		$this->assertSame([], $result['restocked']);
		$this->assertNull($this->row()->getRestock());
	}

	// ----- Undo -----

	public function testUncheckingTakesItBackOut(): void {
		$eggs = $this->counted(20, '4', '12');
		$milk = $this->level(21, StockRules::LEVEL_LOW, '2');
		$this->tick(null);

		$result = $this->svc->toggle(self::ROW, 'alice');

		$this->assertSame('4', $eggs->getQuantity());
		$this->assertSame(StockRules::LEVEL_LOW, $milk->getStockLevel());
		$this->assertCount(2, $result['undone']);
		$this->assertNull($this->row()->getRestock());
		$this->assertCount(4, $this->synced);
	}

	public function testUndoNeverGoesBelowZero(): void {
		$eggs = $this->counted(20, '4', '12');
		$this->tick('8');
		$eggs->setQuantity('3');

		$this->svc->toggle(self::ROW, 'alice');

		$this->assertSame('0', $eggs->getQuantity());
	}

	public function testUndoSkipsItemsUnlinkedOrPurgedSince(): void {
		$unlinked = $this->counted(20, '0', '6');
		$purged = $this->counted(21, '0', '6');
		$kept = $this->counted(21, '0', '6');
		$this->tick(null);
		$unlinked->setLinkedItemId(null);
		unset($this->items[(int)$purged->getId()]);

		$result = $this->svc->toggle(self::ROW, 'alice');

		$this->assertSame('6', $unlinked->getQuantity());
		$this->assertSame('0', $kept->getQuantity());
		$this->assertSame([(int)$kept->getId()], array_column($result['undone'], 'itemId'));
	}

	public function testUncheckingARowWithoutARecordTouchesNoStock(): void {
		$eggs = $this->counted(20, '4', '12');
		$this->row()->setDone(true);
		$this->sync->expects($this->never())->method('afterWrite');

		$result = $this->svc->toggle(self::ROW, 'alice');

		$this->assertSame('4', $eggs->getQuantity());
		$this->assertSame([], $result['undone']);
	}

	public function testAShoppingModeUncheckTakesTheRecordBack(): void {
		$eggs = $this->counted(20, '4', '12');
		$this->tick('8');
		$record = $this->row()->getRestock() ?? [];
		$this->row()->setDone(false);
		$this->row()->setRestock(null);

		$result = $this->svc->afterMemberUncheck($this->row(), $record, 'alice');

		$this->assertSame('4', $eggs->getQuantity());
		$this->assertCount(1, $result['undone']);
	}

	// ----- Expiring stock and the date -----

	private static function inDays(int $days): int {
		return StockRules::addDays(StockRules::today(), $days);
	}

	/** Going off tomorrow, with a shelf life of 14 days. */
	private function expiring(ChecklistItem $item): ChecklistItem {
		$item->setExpiresAt(self::inDays(1));
		$item->setShelfLifeDays(14);
		return $item;
	}

	public function testExpiringStockIsReplacedRatherThanAddedTo(): void {
		$eggs = $this->expiring($this->counted(20, '12', '12'));

		$result = $this->tick('12');

		$this->assertSame('12', $eggs->getQuantity());
		$this->assertTrue($result['restocked'][0]['replaced']);
		$this->assertSame('12', $result['restocked'][0]['previousOnHand']);
	}

	public function testAPartialShareStillReplaces(): void {
		$eggs = $this->expiring($this->counted(20, '12', '12'));

		$this->tick('5');

		$this->assertSame('5', $eggs->getQuantity());
	}

	public function testAnExpiringLevelItemIsReplacedWithFull(): void {
		$milk = $this->expiring($this->level(20, StockRules::LEVEL_LOW, '2'));

		$result = $this->tick('2');

		$this->assertSame(StockRules::LEVEL_FULL, $milk->getStockLevel());
		$this->assertTrue($result['restocked'][0]['replaced']);
		$this->assertSame(StockRules::LEVEL_LOW, $result['restocked'][0]['previousLevel']);
	}

	public function testASharedRowReplacesOneAndFillsTheOther(): void {
		$kitchen = $this->expiring($this->counted(20, '12', '12'));
		$garage = $this->counted(21, '2', '6');

		$this->tick('16');

		$this->assertSame('12', $kitchen->getQuantity());
		$this->assertSame('6', $garage->getQuantity());
	}

	public function testEveryRestockRenewsTheDate(): void {
		$eggs = $this->counted(20, '4', '12');
		$eggs->setExpiresAt(self::inDays(5));
		$eggs->setShelfLifeDays(7);

		$result = $this->tick('8');

		$this->assertSame(self::inDays(7), $eggs->getExpiresAt());
		$this->assertSame(self::inDays(5), $result['restocked'][0]['previousExpiresAt']);
	}

	public function testARestockWithoutAShelfLifeClearsTheDate(): void {
		$eggs = $this->counted(20, '4', '12');
		$eggs->setExpiresAt(self::inDays(5));

		$this->tick('8');

		$this->assertNull($eggs->getExpiresAt());
	}

	public function testAnUncountedItemOnlyGetsTheFreshDate(): void {
		$some = $this->expiring($this->counted(20, 'some', '12'));

		$result = $this->tick('4');

		$this->assertSame('some', $some->getQuantity());
		$this->assertSame(self::inDays(14), $some->getExpiresAt());
		$this->assertNull($result['restocked'][0]['added']);
	}

	public function testUndoPutsReplacedStockAndItsDateBack(): void {
		$eggs = $this->expiring($this->counted(20, '3', '12'));
		$this->tick('12');

		$this->svc->toggle(self::ROW, 'alice');

		$this->assertSame('3', $eggs->getQuantity());
		$this->assertSame(self::inDays(1), $eggs->getExpiresAt());
	}

	public function testUndoKeepsADateEditedByHandSince(): void {
		$eggs = $this->expiring($this->counted(20, '3', '12'));
		$this->tick('12');
		$eggs->setExpiresAt(self::inDays(30));

		$this->svc->toggle(self::ROW, 'alice');

		$this->assertSame('3', $eggs->getQuantity());
		$this->assertSame(self::inDays(30), $eggs->getExpiresAt());
	}

	public function testUndoPutsAnUncountedItemsDateBack(): void {
		$some = $this->expiring($this->counted(20, 'some', '12'));
		$this->tick('4');

		$result = $this->svc->toggle(self::ROW, 'alice');

		$this->assertSame(self::inDays(1), $some->getExpiresAt());
		$this->assertCount(1, $result['undone']);
	}

	// ----- Putting away after a trip -----

	public function testPuttingAwayMovesWhatTheTickRestockedToAnotherInventory(): void {
		$kitchen = $this->counted(20, '4 eggs', '12 eggs');
		$garage = $this->counted(21, '0 eggs', '6 eggs');
		$this->tick('8 eggs');
		$this->assertSame('12 eggs', $kitchen->getQuantity(), 'the tick filled the first-linked inventory');

		$result = $this->svc->putAway(self::ROW, [['listId' => 21, 'amount' => 8.0]], 'alice');

		$this->assertSame('4 eggs', $kitchen->getQuantity());
		$this->assertSame('8 eggs', $garage->getQuantity());
		$this->assertSame([(int)$garage->getId()], array_column($this->row()->getRestock() ?? [], 'itemId'));
		$this->assertSame(['8'], array_column($result['restocked'], 'added'));
		$this->assertCount(1, $result['undone']);
	}

	public function testPuttingAwaySplitsBetweenInventories(): void {
		$kitchen = $this->counted(20, '4 eggs', '12 eggs');
		$garage = $this->counted(21, '0 eggs', '6 eggs');
		$this->tick('8 eggs');

		$this->svc->putAway(self::ROW, [['listId' => 20, 'amount' => 5.0], ['listId' => 21, 'amount' => 3.0]], 'alice');

		$this->assertSame('9 eggs', $kitchen->getQuantity());
		$this->assertSame('3 eggs', $garage->getQuantity());
	}

	public function testEachPutAwayItemIsSyncedOnceAgainstItsStockBeforeThePutAway(): void {
		$kitchen = $this->counted(20, '4 eggs', '12 eggs');
		$this->counted(21, '0 eggs', '6 eggs');
		$this->tick('8 eggs');
		$this->synced = [];

		$this->svc->putAway(self::ROW, [['listId' => 21, 'amount' => 8.0]], 'alice');

		$this->assertCount(2, $this->synced);
		$this->assertSame((int)$kitchen->getId(), $this->synced[0][0]);
		$this->assertSame('ok', $this->synced[0][1]['status'], "the kitchen's before is the tick's full stock, not its wound-back one");
	}

	public function testUncheckingAfterPuttingAwayTakesOutWhatThePicksAdded(): void {
		$kitchen = $this->counted(20, '4 eggs', '12 eggs');
		$garage = $this->counted(21, '0 eggs', '6 eggs');
		$this->tick('8 eggs');
		$this->svc->putAway(self::ROW, [['listId' => 21, 'amount' => 8.0]], 'alice');

		$this->svc->toggle(self::ROW, 'alice');

		$this->assertSame('4 eggs', $kitchen->getQuantity());
		$this->assertSame('0 eggs', $garage->getQuantity());
	}

	public function testPuttingAwayInAnInventoryWithoutTheItemAddsItLinked(): void {
		$this->row()->setCategoryId(1);
		$this->tick('2 L');

		$this->svc->putAway(self::ROW, [['listId' => 21, 'amount' => 2.0]], 'alice');

		$this->assertSame(['name' => 'Eggs', 'categoryId' => 1, 'stockMode' => 'count', 'target' => '2 L', 'quantity' => '0 L'], $this->added);
		$new = $this->items[(int)($this->row()->getRestock()[0]['itemId'] ?? 0)];
		$this->assertSame(21, $new->getListId());
		$this->assertSame('2 L', $new->getQuantity());
		$this->assertSame(self::ROW, $new->getLinkedItemId(), 'linked, so the garage is offered first next time');
	}

	public function testANewItemOnlyKeepsAHouseWideCategory(): void {
		$this->row()->setCategoryId(2);
		$this->tick('1');

		$this->svc->putAway(self::ROW, [['listId' => 21, 'amount' => 1.0]], 'alice');

		$this->assertIsArray($this->added);
		$this->assertArrayHasKey('categoryId', $this->added);
		$this->assertNull($this->added['categoryId'], 'a category only the shopping list sees stays behind');
	}

	public function testPuttingAwayLinksAFreeSameNameItem(): void {
		$loose = new ChecklistItem();
		$loose->setId(77);
		$loose->setListId(21);
		$loose->setName(' eggs ');
		$loose->setStockMode(StockRules::MODE_COUNT);
		$loose->setQuantity('1');
		$loose->setTarget('6');
		$this->items[77] = $loose;
		$this->tick('2');

		$this->svc->putAway(self::ROW, [['listId' => 21, 'amount' => 2.0]], 'alice');

		$this->assertSame('3', $loose->getQuantity());
		$this->assertSame(self::ROW, $loose->getLinkedItemId());
		$this->assertNull($this->added, 'no new item when one with the same name is there');
	}

	public function testNoPicksTakesThePurchaseBackOutOfStock(): void {
		$kitchen = $this->counted(20, '4 eggs', '12 eggs');
		$this->tick('8 eggs');

		$this->svc->putAway(self::ROW, [], 'alice');

		$this->assertSame('4 eggs', $kitchen->getQuantity());
		$this->assertNull($this->row()->getRestock());
	}

	public function testAnUntickedItemIsNotPutAway(): void {
		$kitchen = $this->counted(20, '4 eggs', '12 eggs');

		$result = $this->svc->putAway(self::ROW, [['listId' => 20, 'amount' => 3.0]], 'alice');

		$this->assertSame('4 eggs', $kitchen->getQuantity());
		$this->assertSame([], $result['restocked']);
	}

	public function testPuttingAwayOnAChecklistIsRefused(): void {
		$this->tick('2');

		$this->expectException(\InvalidArgumentException::class);
		$this->svc->putAway(self::ROW, [['listId' => 11, 'amount' => 2.0]], 'alice');
	}
}
