<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Db\ChecklistItemMapper;
use OCA\Pantry\Db\ItemPriceMapper;
use OCA\Pantry\Db\ItemStoreMapper;
use OCA\Pantry\Db\ShoppingSession;
use OCA\Pantry\Db\ShoppingSessionItem;
use OCA\Pantry\Db\ShoppingSessionItemMapper;
use OCA\Pantry\Db\ShoppingSessionListMapper;
use OCA\Pantry\Db\ShoppingSessionMapper;
use OCA\Pantry\Db\ShoppingSessionSkip;
use OCA\Pantry\Db\ShoppingSessionSkipMapper;
use OCA\Pantry\Db\ShoppingSessionStore;
use OCA\Pantry\Db\ShoppingSessionStoreMapper;
use OCA\Pantry\Db\Store;
use OCA\Pantry\Db\StoreMapper;
use OCA\Pantry\Exception\NotFoundException;
use OCA\Pantry\Exception\ShoppingSessionConflictException;
use OCA\Pantry\Service\ChecklistService;
use OCA\Pantry\Service\ShoppingSessionService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ShoppingSessionServiceTest extends TestCase {
	/** @var ShoppingSessionMapper&MockObject */
	private ShoppingSessionMapper $sessions;
	/** @var ShoppingSessionListMapper&MockObject */
	private ShoppingSessionListMapper $sessionLists;
	/** @var ShoppingSessionStoreMapper&MockObject */
	private ShoppingSessionStoreMapper $sessionStores;
	/** @var ShoppingSessionItemMapper&MockObject */
	private ShoppingSessionItemMapper $sessionItems;
	/** @var ShoppingSessionSkipMapper&MockObject */
	private ShoppingSessionSkipMapper $sessionSkips;
	/** @var ChecklistItemMapper&MockObject */
	private ChecklistItemMapper $items;
	/** @var ItemStoreMapper&MockObject */
	private ItemStoreMapper $itemStores;
	/** @var ItemPriceMapper&MockObject */
	private ItemPriceMapper $itemPrices;
	/** @var ChecklistService&MockObject */
	private ChecklistService $checklists;
	/** @var StoreMapper&MockObject */
	private StoreMapper $storeMapper;
	private ShoppingSessionService $svc;

	/**
	 * Item prices keyed by item id, returned by the price mapper's findForItems.
	 * makeItem() populates this so live-trip estimates resolve real prices.
	 *
	 * @var array<int, list<array{storeId: ?int, priceType: ?string, priceMin: ?float, priceMax: ?float, priceCurrency: ?string}>>
	 */
	private array $priceRegistry = [];

	protected function setUp(): void {
		$this->sessions = $this->createMock(ShoppingSessionMapper::class);
		$this->sessionLists = $this->createMock(ShoppingSessionListMapper::class);
		$this->sessionStores = $this->createMock(ShoppingSessionStoreMapper::class);
		$this->sessionItems = $this->createMock(ShoppingSessionItemMapper::class);
		$this->sessionSkips = $this->createMock(ShoppingSessionSkipMapper::class);
		$this->items = $this->createMock(ChecklistItemMapper::class);
		$this->itemStores = $this->createMock(ItemStoreMapper::class);
		$this->itemPrices = $this->createMock(ItemPriceMapper::class);
		$this->priceRegistry = [];
		$this->itemPrices->method('findForItems')->willReturnCallback(
			fn (array $ids): array => array_intersect_key(
				$this->priceRegistry,
				array_flip(array_map('intval', $ids)),
			),
		);
		$this->checklists = $this->createMock(ChecklistService::class);
		$this->storeMapper = $this->createMock(StoreMapper::class);
		$this->svc = new ShoppingSessionService(
			$this->sessions,
			$this->sessionLists,
			$this->sessionStores,
			$this->sessionItems,
			$this->sessionSkips,
			$this->items,
			$this->itemStores,
			$this->itemPrices,
			$this->checklists,
			$this->storeMapper,
		);
	}

	private function makeItem(array $o = []): ChecklistItem {
		$i = new ChecklistItem();
		$i->setListId($o['listId'] ?? 1);
		$i->setName($o['name'] ?? 'Milk');
		$i->setDone($o['done'] ?? false);
		if (isset($o['id'])) {
			$ref = new \ReflectionProperty($i, 'id');
			$ref->setValue($i, $o['id']);
		}
		// Prices now live in a related table; expose the item's price as a single
		// store-less price and register it so the price-mapper mock returns it.
		if (($o['priceType'] ?? null) !== null) {
			$price = [
				'storeId' => $o['priceStoreId'] ?? null,
				'priceType' => $o['priceType'],
				'priceMin' => $o['priceMin'] ?? null,
				'priceMax' => $o['priceMax'] ?? null,
				'priceCurrency' => $o['priceCurrency'] ?? null,
			];
			$i->setResolvedPrices([$price]);
			if (isset($o['id'])) {
				$this->priceRegistry[(int)$o['id']] = [$price];
			}
		}
		return $i;
	}

	private function makeLog(int $itemId, ?int $storeId, array $snap = []): ShoppingSessionItem {
		$l = new ShoppingSessionItem();
		$l->setItemId($itemId);
		$l->setStoreId($storeId);
		$l->setCheckedAt(1000);
		if ($snap !== []) {
			$l->setItemName($snap['name'] ?? 'Item');
			$l->setQuantity($snap['quantity'] ?? null);
			$l->setPriceType($snap['priceType'] ?? null);
			$l->setPriceMin($snap['priceMin'] ?? null);
			$l->setPriceMax($snap['priceMax'] ?? null);
			$l->setPriceCurrency($snap['priceCurrency'] ?? null);
		}
		return $l;
	}

	private function makeSession(array $overrides = []): ShoppingSession {
		$s = new ShoppingSession();
		$s->setHouseId($overrides['houseId'] ?? 1);
		$s->setUserId($overrides['userId'] ?? 'alice');
		$s->setActiveStoreId($overrides['activeStoreId'] ?? null);
		$s->setLastSeenAt($overrides['lastSeenAt'] ?? 0);
		$s->setClosedAt($overrides['closedAt'] ?? null);
		$s->setIncludeUnassigned($overrides['includeUnassigned'] ?? true);
		$s->setIsPrivate($overrides['isPrivate'] ?? false);
		$s->setCreatedAt($overrides['createdAt'] ?? 0);
		if (isset($overrides['id'])) {
			$ref = new \ReflectionProperty($s, 'id');
			$ref->setValue($s, $overrides['id']);
		}
		return $s;
	}

	private function makeStore(int $id, string $name): Store {
		$s = new Store();
		$s->setName($name);
		$ref = new \ReflectionProperty($s, 'id');
		$ref->setValue($s, $id);
		return $s;
	}

	private function makeSessionStore(int $storeId, int $position): ShoppingSessionStore {
		$s = new ShoppingSessionStore();
		$s->setStoreId($storeId);
		$s->setPosition($position);
		return $s;
	}

	public function testCreateThrowsConflictWhenLiveSessionExists(): void {
		$existing = $this->makeSession(['id' => 9, 'userId' => 'alice']);
		$this->sessions->method('findLiveByUser')->with('alice')->willReturn($existing);
		$this->sessions->expects($this->never())->method('insert');

		try {
			$this->svc->create(1, 'alice', [10], [], true);
			$this->fail('Expected conflict');
		} catch (ShoppingSessionConflictException $e) {
			$this->assertSame($existing, $e->getSession());
		}
	}

	public function testCreateRejectsEmptyLists(): void {
		$this->sessions->method('findLiveByUser')->willReturn(null);
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->create(1, 'alice', [], [2], true);
	}

	public function testCreateInsertsSessionWithFirstStoreActive(): void {
		$this->sessions->method('findLiveByUser')->willReturn(null);
		$this->sessions->expects($this->once())
			->method('insert')
			->willReturnCallback(function (ShoppingSession $s) {
				$ref = new \ReflectionProperty($s, 'id');
				$ref->setValue($s, 42);
				return $s;
			});
		$this->sessionLists->expects($this->once())
			->method('setListsForSession')
			->with(42, [10, 11]);
		$this->sessionStores->expects($this->once())
			->method('setStoresForSession')
			->with(42, [3, 7]);

		$session = $this->svc->create(1, 'alice', [10, 11], [3, 7], false);

		$this->assertSame(3, $session->getActiveStoreId());
		$this->assertFalse($session->getIncludeUnassigned());
		$this->assertNull($session->getClosedAt());
		$this->assertSame('alice', $session->getUserId());
	}

	public function testCreateWithNoStoresLeavesActiveStoreNull(): void {
		$this->sessions->method('findLiveByUser')->willReturn(null);
		$this->sessions->method('insert')->willReturnCallback(function (ShoppingSession $s) {
			$ref = new \ReflectionProperty($s, 'id');
			$ref->setValue($s, 1);
			return $s;
		});

		$session = $this->svc->create(1, 'alice', [10], [], true);
		$this->assertNull($session->getActiveStoreId());
	}

	public function testAdvanceRejectsStoreOutsideSequence(): void {
		$session = $this->makeSession(['id' => 5]);
		$this->sessionStores->method('findBySession')->with(5)->willReturn([
			$this->makeSessionStore(3, 0),
			$this->makeSessionStore(7, 1),
		]);
		$this->sessions->expects($this->never())->method('update');

		$this->expectException(\InvalidArgumentException::class);
		$this->svc->advance($session, 99);
	}

	public function testAdvanceSetsActiveStore(): void {
		$session = $this->makeSession(['id' => 5, 'activeStoreId' => 3]);
		$this->sessionStores->method('findBySession')->with(5)->willReturn([
			$this->makeSessionStore(3, 0),
			$this->makeSessionStore(7, 1),
		]);
		$this->sessions->expects($this->once())->method('update')->willReturnArgument(0);

		$updated = $this->svc->advance($session, 7);
		$this->assertSame(7, $updated->getActiveStoreId());
	}

	public function testCloseThrowsConflictWhenAlreadyClosed(): void {
		$session = $this->makeSession(['id' => 5, 'closedAt' => 1000]);
		$this->sessions->expects($this->never())->method('update');

		try {
			$this->svc->close($session);
			$this->fail('Expected conflict');
		} catch (ShoppingSessionConflictException $e) {
			$this->assertSame($session, $e->getSession());
		}
	}

	public function testCloseStampsClosedAt(): void {
		$session = $this->makeSession(['id' => 5]);
		$this->sessions->expects($this->once())->method('update')->willReturnArgument(0);

		$closed = $this->svc->close($session);
		$this->assertNotNull($closed->getClosedAt());
	}

	public function testItemsForSessionNarrowsByActiveStore(): void {
		$session = $this->makeSession(['id' => 5, 'activeStoreId' => 3, 'includeUnassigned' => false]);
		$this->sessionLists->method('findListIdsForSession')->with(5)->willReturn([10, 11]);
		$this->items->expects($this->once())
			->method('findForShoppingScope')
			->with([10, 11], 3, false)
			->willReturn([]);

		$this->assertSame([], $this->svc->itemsForSession($session));
	}

	public function testComposeDtoEmbedsScopeAndStores(): void {
		$session = $this->makeSession(['id' => 5, 'houseId' => 2, 'activeStoreId' => 3]);
		$this->sessionLists->method('findListIdsForSession')->with(5)->willReturn([10, 11]);
		$this->sessionStores->method('findBySession')->with(5)->willReturn([
			$this->makeSessionStore(3, 0),
			$this->makeSessionStore(7, 1),
		]);

		$dto = $this->svc->composeDto($session);
		$this->assertSame([10, 11], $dto['listIds']);
		$this->assertCount(2, $dto['stores']);
		$this->assertSame(3, $dto['stores'][0]['storeId']);
		$this->assertTrue($dto['live']);
		$this->assertSame(2, $dto['houseId']);
	}

	public function testCheckItemMarksDoneAndLogsAgainstActiveStore(): void {
		$session = $this->makeSession(['id' => 5, 'activeStoreId' => 3]);
		$this->checklists->method('getItem')->with(10)->willReturn($this->makeItem(['id' => 10, 'done' => false]));
		$this->checklists->expects($this->once())->method('toggleItem')->with(10, 'alice');
		$this->sessionItems->method('findBySessionAndItem')->with(5, 10)->willReturn(null);
		$this->sessionItems->expects($this->once())
			->method('insert')
			->willReturnCallback(function (ShoppingSessionItem $row) {
				$this->assertSame(5, $row->getSessionId());
				$this->assertSame(10, $row->getItemId());
				$this->assertSame(3, $row->getStoreId());
				return $row;
			});

		$this->svc->checkItem($session, 10, 'alice');
	}

	public function testCheckItemSkipsToggleWhenAlreadyDoneAndUpdatesLog(): void {
		$session = $this->makeSession(['id' => 5, 'activeStoreId' => 3]);
		$this->checklists->method('getItem')->with(10)->willReturn($this->makeItem(['id' => 10, 'done' => true]));
		$this->checklists->expects($this->never())->method('toggleItem');
		$this->sessionItems->method('findBySessionAndItem')->with(5, 10)->willReturn($this->makeLog(10, null));
		$this->sessionItems->expects($this->once())->method('update');
		$this->sessionItems->expects($this->never())->method('insert');

		$this->svc->checkItem($session, 10, 'alice');
	}

	public function testUncheckItemRemovesLogAndUnchecks(): void {
		$session = $this->makeSession(['id' => 5]);
		$this->sessionItems->expects($this->once())->method('deleteBySessionAndItem')->with(5, 10);
		$this->items->method('findById')->with(10, true)->willReturn($this->makeItem(['id' => 10, 'done' => true]));
		$this->items->expects($this->once())
			->method('update')
			->willReturnCallback(function (ChecklistItem $i) {
				$this->assertFalse($i->getDone());
				return $i;
			});

		$this->svc->uncheckItem($session, 10);
	}

	public function testItemsForSessionDropsSkippedItems(): void {
		$session = $this->makeSession(['id' => 5]);
		$this->sessionLists->method('findListIdsForSession')->with(5)->willReturn([10]);
		$this->items->method('findForShoppingScope')->willReturn([
			$this->makeItem(['id' => 10]),
			$this->makeItem(['id' => 11]),
			$this->makeItem(['id' => 12]),
		]);
		$this->sessionSkips->method('findItemIdsForSession')->with(5)->willReturn([11]);

		$result = $this->svc->itemsForSession($session);
		$this->assertSame([10, 12], array_map(static fn (ChecklistItem $i) => $i->getId(), $result));
	}

	public function testSkipItemInsertsWhenNotAlreadySkipped(): void {
		$session = $this->makeSession(['id' => 5]);
		$this->sessionSkips->method('findBySessionAndItem')->with(5, 10)->willReturn(null);
		$this->sessionSkips->expects($this->once())
			->method('insert')
			->willReturnCallback(function (ShoppingSessionSkip $row) {
				$this->assertSame(5, $row->getSessionId());
				$this->assertSame(10, $row->getItemId());
				return $row;
			});

		$this->svc->skipItem($session, 10);
	}

	public function testSkipItemIsIdempotent(): void {
		$session = $this->makeSession(['id' => 5]);
		$skip = new ShoppingSessionSkip();
		$skip->setSessionId(5);
		$skip->setItemId(10);
		$this->sessionSkips->method('findBySessionAndItem')->with(5, 10)->willReturn($skip);
		$this->sessionSkips->expects($this->never())->method('insert');

		$this->svc->skipItem($session, 10);
	}

	public function testSkipItemDoesNotToggleOrDeleteItem(): void {
		$session = $this->makeSession(['id' => 5]);
		$this->sessionSkips->method('findBySessionAndItem')->willReturn(null);
		$this->checklists->expects($this->never())->method('toggleItem');
		$this->items->expects($this->never())->method('update');

		$this->svc->skipItem($session, 10);
	}

	public function testUnskipItemDeletesSkipRow(): void {
		$session = $this->makeSession(['id' => 5]);
		$this->sessionSkips->expects($this->once())->method('deleteBySessionAndItem')->with(5, 10);

		$this->svc->unskipItem($session, 10);
	}

	public function testRemovedItemsForSessionEmptyWhenNoSkips(): void {
		$session = $this->makeSession(['id' => 5]);
		$this->sessionSkips->method('findItemIdsForSession')->with(5)->willReturn([]);
		$this->items->expects($this->never())->method('findByIds');

		$this->assertSame([], $this->svc->removedItemsForSession($session));
	}

	public function testRemovedItemsForSessionReturnsSkippedItemsSortedByName(): void {
		$session = $this->makeSession(['id' => 5]);
		$this->sessionSkips->method('findItemIdsForSession')->with(5)->willReturn([11, 12]);
		$this->items->method('findByIds')->with([11, 12])->willReturn([
			$this->makeItem(['id' => 11, 'name' => 'Zucchini']),
			$this->makeItem(['id' => 12, 'name' => 'Apples']),
		]);

		$result = $this->svc->removedItemsForSession($session);
		$this->assertSame([12, 11], array_map(static fn (ChecklistItem $i) => $i->getId(), $result));
	}

	public function testRemovedItemsForSessionDropsDeletedAndArchived(): void {
		$session = $this->makeSession(['id' => 5]);
		$this->sessionSkips->method('findItemIdsForSession')->with(5)->willReturn([11, 12, 13]);
		$deleted = $this->makeItem(['id' => 12, 'name' => 'Deleted']);
		$deleted->setDeletedAt(1000);
		$archived = $this->makeItem(['id' => 13, 'name' => 'Archived']);
		$archived->setArchivedAt(1000);
		$this->items->method('findByIds')->willReturn([
			$this->makeItem(['id' => 11, 'name' => 'Live']),
			$deleted,
			$archived,
		]);

		$result = $this->svc->removedItemsForSession($session);
		$this->assertSame([11], array_map(static fn (ChecklistItem $i) => $i->getId(), $result));
	}

	public function testReviewGroupsByStoreWithPerCurrencyEstimate(): void {
		$session = $this->makeSession(['id' => 5, 'houseId' => 1]);
		$this->sessionItems->method('findBySession')->with(5)->willReturn([
			$this->makeLog(10, 3),
			$this->makeLog(11, 7),
		]);
		$this->items->method('findByIds')->willReturn([
			$this->makeItem(['id' => 10, 'done' => true, 'priceType' => 'set', 'priceMin' => 2.0, 'priceCurrency' => 'USD']),
			$this->makeItem(['id' => 11, 'done' => true, 'priceType' => 'set', 'priceMin' => 3.0, 'priceCurrency' => 'USD']),
		]);
		$this->itemStores->method('findStoreIdsForItems')->willReturn([]);
		$this->sessionStores->method('findBySession')->with(5)->willReturn([
			$this->makeSessionStore(3, 0),
			$this->makeSessionStore(7, 1),
		]);
		$this->sessionLists->method('findListIdsForSession')->with(5)->willReturn([1]);
		$this->items->method('findForShoppingScope')->willReturn([]);

		$review = $this->svc->review($session);

		$this->assertCount(2, $review['stores']);
		$this->assertSame(3, $review['stores'][0]['storeId']);
		$this->assertSame(0, $review['uncheckedCount']);
		$this->assertSame([['currency' => 'USD', 'min' => 5.0, 'max' => 5.0]], $review['grandTotal']);
	}

	public function testReviewUsesBilledOverEstimate(): void {
		$session = $this->makeSession(['id' => 5]);
		$this->sessionItems->method('findBySession')->with(5)->willReturn([$this->makeLog(10, 3)]);
		$this->items->method('findByIds')->willReturn([
			$this->makeItem(['id' => 10, 'done' => true, 'priceType' => 'set', 'priceMin' => 2.0, 'priceCurrency' => 'USD']),
		]);
		$this->itemStores->method('findStoreIdsForItems')->willReturn([]);
		$store = $this->makeSessionStore(3, 0);
		$store->setBilledTotal(9.5);
		$store->setBilledCurrency('USD');
		$this->sessionStores->method('findBySession')->with(5)->willReturn([$store]);
		$this->sessionLists->method('findListIdsForSession')->willReturn([1]);
		$this->items->method('findForShoppingScope')->willReturn([]);

		$review = $this->svc->review($session);
		$this->assertSame(9.5, $review['stores'][0]['billedTotal']);
		// Grand total uses the billed amount, not the 2.00 estimate.
		$this->assertSame([['currency' => 'USD', 'min' => 9.5, 'max' => 9.5]], $review['grandTotal']);
	}

	public function testReviewExcludesItemsUndoneSinceCheck(): void {
		$session = $this->makeSession(['id' => 5]);
		$this->sessionItems->method('findBySession')->with(5)->willReturn([
			$this->makeLog(10, 3),
			$this->makeLog(11, 3),
		]);
		// Item 11 was un-done outside shopping mode; its log row is now stale.
		$this->items->method('findByIds')->willReturn([
			$this->makeItem(['id' => 10, 'done' => true, 'priceType' => 'set', 'priceMin' => 2.0, 'priceCurrency' => 'USD']),
			$this->makeItem(['id' => 11, 'done' => false, 'priceType' => 'set', 'priceMin' => 3.0, 'priceCurrency' => 'USD']),
		]);
		$this->itemStores->method('findStoreIdsForItems')->willReturn([]);
		$this->sessionStores->method('findBySession')->with(5)->willReturn([$this->makeSessionStore(3, 0)]);
		$this->sessionLists->method('findListIdsForSession')->willReturn([1]);
		$this->items->method('findForShoppingScope')->willReturn([]);

		$review = $this->svc->review($session);
		$this->assertCount(1, $review['stores'][0]['items']);
		// Only the still-done item contributes to the total.
		$this->assertSame([['currency' => 'USD', 'min' => 2.0, 'max' => 2.0]], $review['grandTotal']);
	}

	public function testClosedReviewRendersFromSnapshotEvenIfItemDeleted(): void {
		// A closed trip is frozen history: it renders from the per-row snapshot,
		// so it survives the item being hard-deleted (findByIds returns nothing).
		$session = $this->makeSession(['id' => 5, 'closedAt' => 500]);
		$this->sessionItems->method('findBySession')->with(5)->willReturn([
			$this->makeLog(10, 3, ['name' => 'Bread', 'priceType' => 'set', 'priceMin' => 4.0, 'priceCurrency' => 'USD']),
		]);
		$this->items->method('findByIds')->willReturn([]);
		$this->itemStores->method('findStoreIdsForItems')->willReturn([]);
		$this->sessionStores->method('findBySession')->with(5)->willReturn([$this->makeSessionStore(3, 0)]);
		// A closed trip must not recompute a live "still to buy" count.
		$this->items->expects($this->never())->method('findForShoppingScope');

		$review = $this->svc->review($session);
		$this->assertCount(1, $review['stores'][0]['items']);
		$this->assertSame('Bread', $review['stores'][0]['items'][0]['name']);
		$this->assertSame([['currency' => 'USD', 'min' => 4.0, 'max' => 4.0]], $review['grandTotal']);
		$this->assertSame(0, $review['uncheckedCount']);
	}

	public function testCloseSnapshotsCheckedItemsFromLiveItem(): void {
		$session = $this->makeSession(['id' => 5]);
		$this->sessionItems->method('findBySession')->with(5)->willReturn([$this->makeLog(10, 3)]);
		$this->items->method('findByIds')->willReturn([
			$this->makeItem(['id' => 10, 'name' => 'Milk', 'done' => true, 'priceType' => 'set', 'priceMin' => 2.5, 'priceCurrency' => 'USD']),
		]);
		$this->sessionItems->expects($this->once())
			->method('update')
			->willReturnCallback(function (ShoppingSessionItem $log) {
				$this->assertSame('Milk', $log->getItemName());
				$this->assertSame(2.5, $log->getPriceMin());
				$this->assertSame('USD', $log->getPriceCurrency());
				return $log;
			});
		$this->sessions->method('update')->willReturnArgument(0);

		$closed = $this->svc->close($session);
		$this->assertNotNull($closed->getClosedAt());
	}

	public function testAmendStoreBilledRejectsUnknownStore(): void {
		$session = $this->makeSession(['id' => 5]);
		$this->sessionStores->method('findBySession')->with(5)->willReturn([$this->makeSessionStore(3, 0)]);
		$this->expectException(NotFoundException::class);
		$this->svc->amendStoreBilled($session, 99, 10.0, 'USD');
	}

	public function testHistoryRollsUpRowFields(): void {
		$session = $this->makeSession([
			'id' => 5, 'houseId' => 1, 'userId' => 'alice', 'createdAt' => 100, 'closedAt' => 500,
		]);
		$this->sessions->method('findClosedForHistory')->with(1, 'alice', 'mine', 30, 0)->willReturn([$session]);
		$this->storeMapper->method('findByHouse')->with(1)->willReturn([
			$this->makeStore(3, 'Aldi'),
			$this->makeStore(7, 'Rewe'),
		]);
		$this->sessionStores->method('findBySession')->with(5)->willReturn([
			$this->makeSessionStore(3, 0),
			$this->makeSessionStore(7, 1),
		]);
		$this->sessionItems->method('countBySession')->with(5)->willReturn(4);
		// A closed trip rolls up from the frozen per-row snapshot, one per store.
		$this->sessionItems->method('findBySession')->with(5)->willReturn([
			$this->makeLog(10, 3, ['name' => 'A', 'priceType' => 'set', 'priceMin' => 2.0, 'priceCurrency' => 'USD']),
			$this->makeLog(11, 7, ['name' => 'B', 'priceType' => 'set', 'priceMin' => 3.0, 'priceCurrency' => 'USD']),
		]);

		$rows = $this->svc->history(1, 'alice', 'mine', 30, 0);

		$this->assertCount(1, $rows);
		$row = $rows[0];
		$this->assertSame(5, $row['id']);
		$this->assertSame('alice', $row['userId']);
		$this->assertSame(100, $row['createdAt']);
		$this->assertSame(500, $row['closedAt']);
		$this->assertSame(['Aldi', 'Rewe'], $row['stores']);
		$this->assertSame(4, $row['itemCount']);
		// Point estimates collapse to their own amount (min === max midpoint).
		$this->assertSame([['currency' => 'USD', 'amount' => 5.0]], $row['grandTotal']);
	}

	public function testHistoryCollapsesRangeToMidpoint(): void {
		$session = $this->makeSession(['id' => 5, 'houseId' => 1, 'userId' => 'alice', 'closedAt' => 500]);
		$this->sessions->method('findClosedForHistory')->willReturn([$session]);
		$this->storeMapper->method('findByHouse')->willReturn([$this->makeStore(3, 'Aldi')]);
		$this->sessionStores->method('findBySession')->with(5)->willReturn([$this->makeSessionStore(3, 0)]);
		$this->sessionItems->method('countBySession')->willReturn(1);
		$this->sessionItems->method('findBySession')->willReturn([
			$this->makeLog(10, 3, ['name' => 'A', 'priceType' => 'range', 'priceMin' => 2.0, 'priceMax' => 6.0, 'priceCurrency' => 'USD']),
		]);

		$rows = $this->svc->history(1, 'alice', 'mine', 30, 0);
		// Range 2–6 collapses to its midpoint 4 for the glanceable row.
		$this->assertSame([['currency' => 'USD', 'amount' => 4.0]], $rows[0]['grandTotal']);
	}

	public function testHistoryReturnsEmptyWithoutTouchingStores(): void {
		$this->sessions->method('findClosedForHistory')->willReturn([]);
		$this->storeMapper->expects($this->never())->method('findByHouse');
		$this->assertSame([], $this->svc->history(1, 'alice', 'house', 30, 0));
	}

	public function testCloseIdleSessionsStampsLastSeenAsClosed(): void {
		$idle = $this->makeSession(['id' => 5, 'lastSeenAt' => 1000]);
		$this->sessions->method('findIdleLive')->willReturn([$idle]);
		$this->sessions->expects($this->once())->method('update')->willReturnArgument(0);

		$count = $this->svc->closeIdleSessions(4 * 3600, 100000);
		$this->assertSame(1, $count);
		// Closed at the last-seen time, not the job's "now".
		$this->assertSame(1000, $idle->getClosedAt());
	}

	public function testPurgeAgedSessionsKeepsForeverWhenRetentionOff(): void {
		$this->sessions->expects($this->never())->method('findClosedBefore');
		$this->assertSame(0, $this->svc->purgeAgedSessions(0));
	}

	public function testPurgeAgedSessionsCascadesChildRows(): void {
		$aged = $this->makeSession(['id' => 5, 'closedAt' => 10]);
		$this->sessions->method('findClosedBefore')->willReturn([$aged]);
		$this->sessionItems->expects($this->once())->method('deleteBySession')->with(5);
		$this->sessionStores->expects($this->once())->method('deleteBySession')->with(5);
		$this->sessionLists->expects($this->once())->method('deleteBySession')->with(5);
		$this->sessions->expects($this->once())->method('delete')->with($aged);

		$this->assertSame(1, $this->svc->purgeAgedSessions(180, 100000000));
	}

	public function testHeartbeatStampsOwnLiveSessionInHouse(): void {
		$session = $this->makeSession(['id' => 5, 'houseId' => 1, 'userId' => 'alice', 'lastSeenAt' => 100]);
		$this->sessions->method('findLiveByUser')->with('alice')->willReturn($session);
		$this->sessions->method('findPresentInHouse')->willReturn([]);
		$this->sessions->expects($this->once())->method('update')->willReturnArgument(0);

		$this->svc->heartbeat(1, 'alice', 5000);
		$this->assertSame(5000, $session->getLastSeenAt());
	}

	public function testHeartbeatSkipsWhenLiveSessionInAnotherHouse(): void {
		$session = $this->makeSession(['id' => 5, 'houseId' => 2, 'userId' => 'alice']);
		$this->sessions->method('findLiveByUser')->with('alice')->willReturn($session);
		$this->sessions->expects($this->never())->method('update');

		// House 1 heartbeat, but the caller's only live trip is in house 2.
		$this->svc->heartbeat(1, 'alice', 5000);
	}

	public function testHeartbeatSkipsWhenNoLiveSession(): void {
		$this->sessions->method('findLiveByUser')->with('alice')->willReturn(null);
		$this->sessions->expects($this->never())->method('update');

		$this->svc->heartbeat(1, 'alice', 5000);
	}

	public function testPresenceMapsFreshSessionsAndCutoff(): void {
		$now = 100000;
		$this->sessions->expects($this->once())
			->method('findPresentInHouse')
			->with(1, $now - ShoppingSessionService::PRESENCE_STALE_SECONDS)
			->willReturn([
				$this->makeSession(['userId' => 'alice', 'activeStoreId' => 3, 'lastSeenAt' => 99000]),
				$this->makeSession(['userId' => 'bob', 'activeStoreId' => null, 'lastSeenAt' => 99500]),
			]);

		$presence = $this->svc->presence(1, $now);

		$this->assertSame([
			['userId' => 'alice', 'activeStoreId' => 3, 'lastSeenAt' => 99000],
			['userId' => 'bob', 'activeStoreId' => null, 'lastSeenAt' => 99500],
		], $presence);
	}

	public function testSetPrivacyPersistsFlag(): void {
		$session = $this->makeSession(['id' => 5, 'isPrivate' => false]);
		$this->sessions->expects($this->once())->method('update')->willReturnArgument(0);

		$updated = $this->svc->setPrivacy($session, true);
		$this->assertTrue($updated->getIsPrivate());
	}

	public function testGetWrapsMissingInNotFound(): void {
		$this->sessions->method('findById')->willThrowException(new DoesNotExistException(''));
		$this->expectException(NotFoundException::class);
		$this->svc->get(123);
	}
}
