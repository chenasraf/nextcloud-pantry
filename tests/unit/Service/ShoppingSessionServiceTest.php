<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Db\ChecklistItemMapper;
use OCA\Pantry\Db\ItemStoreMapper;
use OCA\Pantry\Db\ShoppingSession;
use OCA\Pantry\Db\ShoppingSessionItem;
use OCA\Pantry\Db\ShoppingSessionItemMapper;
use OCA\Pantry\Db\ShoppingSessionListMapper;
use OCA\Pantry\Db\ShoppingSessionMapper;
use OCA\Pantry\Db\ShoppingSessionStore;
use OCA\Pantry\Db\ShoppingSessionStoreMapper;
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
	/** @var ChecklistItemMapper&MockObject */
	private ChecklistItemMapper $items;
	/** @var ItemStoreMapper&MockObject */
	private ItemStoreMapper $itemStores;
	/** @var ChecklistService&MockObject */
	private ChecklistService $checklists;
	private ShoppingSessionService $svc;

	protected function setUp(): void {
		$this->sessions = $this->createMock(ShoppingSessionMapper::class);
		$this->sessionLists = $this->createMock(ShoppingSessionListMapper::class);
		$this->sessionStores = $this->createMock(ShoppingSessionStoreMapper::class);
		$this->sessionItems = $this->createMock(ShoppingSessionItemMapper::class);
		$this->items = $this->createMock(ChecklistItemMapper::class);
		$this->itemStores = $this->createMock(ItemStoreMapper::class);
		$this->checklists = $this->createMock(ChecklistService::class);
		$this->svc = new ShoppingSessionService(
			$this->sessions,
			$this->sessionLists,
			$this->sessionStores,
			$this->sessionItems,
			$this->items,
			$this->itemStores,
			$this->checklists,
		);
	}

	private function makeItem(array $o = []): ChecklistItem {
		$i = new ChecklistItem();
		$i->setListId($o['listId'] ?? 1);
		$i->setName($o['name'] ?? 'Milk');
		$i->setDone($o['done'] ?? false);
		$i->setPriceType($o['priceType'] ?? null);
		$i->setPriceMin($o['priceMin'] ?? null);
		$i->setPriceMax($o['priceMax'] ?? null);
		$i->setPriceCurrency($o['priceCurrency'] ?? null);
		if (isset($o['id'])) {
			$ref = new \ReflectionProperty($i, 'id');
			$ref->setValue($i, $o['id']);
		}
		return $i;
	}

	private function makeLog(int $itemId, ?int $storeId): ShoppingSessionItem {
		$l = new ShoppingSessionItem();
		$l->setItemId($itemId);
		$l->setStoreId($storeId);
		$l->setCheckedAt(1000);
		return $l;
	}

	private function makeSession(array $overrides = []): ShoppingSession {
		$s = new ShoppingSession();
		$s->setHouseId($overrides['houseId'] ?? 1);
		$s->setUserId($overrides['userId'] ?? 'alice');
		$s->setActiveStoreId($overrides['activeStoreId'] ?? null);
		$s->setClosedAt($overrides['closedAt'] ?? null);
		$s->setIncludeUnassigned($overrides['includeUnassigned'] ?? true);
		if (isset($overrides['id'])) {
			$ref = new \ReflectionProperty($s, 'id');
			$ref->setValue($s, $overrides['id']);
		}
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

	public function testAmendStoreBilledRejectsUnknownStore(): void {
		$session = $this->makeSession(['id' => 5]);
		$this->sessionStores->method('findBySession')->with(5)->willReturn([$this->makeSessionStore(3, 0)]);
		$this->expectException(NotFoundException::class);
		$this->svc->amendStoreBilled($session, 99, 10.0, 'USD');
	}

	public function testGetWrapsMissingInNotFound(): void {
		$this->sessions->method('findById')->willThrowException(new DoesNotExistException(''));
		$this->expectException(NotFoundException::class);
		$this->svc->get(123);
	}
}
