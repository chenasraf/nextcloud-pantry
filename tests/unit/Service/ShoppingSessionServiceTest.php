<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Db\ChecklistItemMapper;
use OCA\Pantry\Db\ShoppingSession;
use OCA\Pantry\Db\ShoppingSessionListMapper;
use OCA\Pantry\Db\ShoppingSessionMapper;
use OCA\Pantry\Db\ShoppingSessionStore;
use OCA\Pantry\Db\ShoppingSessionStoreMapper;
use OCA\Pantry\Exception\NotFoundException;
use OCA\Pantry\Exception\ShoppingSessionConflictException;
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
	/** @var ChecklistItemMapper&MockObject */
	private ChecklistItemMapper $items;
	private ShoppingSessionService $svc;

	protected function setUp(): void {
		$this->sessions = $this->createMock(ShoppingSessionMapper::class);
		$this->sessionLists = $this->createMock(ShoppingSessionListMapper::class);
		$this->sessionStores = $this->createMock(ShoppingSessionStoreMapper::class);
		$this->items = $this->createMock(ChecklistItemMapper::class);
		$this->svc = new ShoppingSessionService(
			$this->sessions,
			$this->sessionLists,
			$this->sessionStores,
			$this->items,
		);
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

	public function testGetWrapsMissingInNotFound(): void {
		$this->sessions->method('findById')->willThrowException(new DoesNotExistException(''));
		$this->expectException(NotFoundException::class);
		$this->svc->get(123);
	}
}
