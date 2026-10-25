<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

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

	protected function setUp(): void {
		$lists = [];
		foreach ([10 => 'Shopping', 20 => 'Kitchen', 21 => 'Garage'] as $id => $name) {
			$list = new Checklist();
			$list->setId($id);
			$list->setName($name);
			$lists[$id] = $list;
		}
		$listMapper = $this->createMock(ChecklistMapper::class);
		$listMapper->method('findById')->willReturnCallback(fn (int $id): Checklist => $lists[$id] ?? throw new DoesNotExistException('missing'));

		$itemMapper = $this->createMock(ChecklistItemMapper::class);
		$itemMapper->method('findById')->willReturnCallback(fn (int $id): ChecklistItem => $this->items[$id] ?? throw new DoesNotExistException('missing'));
		$itemMapper->method('update')->willReturnArgument(0);

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

		$this->sync = $this->createMock(RestockSyncService::class);
		$this->sync->method('contributors')->willReturnCallback(fn (ChecklistItem $row): array => array_values(array_filter(
			$this->items,
			static fn (ChecklistItem $i): bool => $i->getLinkedItemId() === (int)$row->getId() && $i->getDeletedAt() === null,
		)));
		$this->sync->method('afterWrite')->willReturnCallback(function (ChecklistItem $item, ?array $before): array {
			$this->synced[] = [(int)$item->getId(), $before];
			return [];
		});

		$this->svc = new RestockService($checklists, $itemMapper, $listMapper, $this->sync, $this->createMock(IDBConnection::class));
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
}
