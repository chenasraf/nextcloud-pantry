<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Db\Checklist;
use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Db\ChecklistItemMapper;
use OCA\Pantry\Db\ChecklistMapper;
use OCA\Pantry\Db\House;
use OCA\Pantry\Db\HouseMapper;
use OCA\Pantry\Db\ListRoleMapper;
use OCA\Pantry\Service\ChecklistService;
use OCA\Pantry\Service\RecurrenceService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ChecklistServiceTest extends TestCase {
	/** @var ChecklistMapper&MockObject */
	private ChecklistMapper $listMapper;
	/** @var ChecklistItemMapper&MockObject */
	private ChecklistItemMapper $itemMapper;
	/** @var \OCA\Pantry\Db\ItemStoreMapper&MockObject */
	private \OCA\Pantry\Db\ItemStoreMapper $itemStoreMapper;
	/** @var \OCA\Pantry\Db\ItemPriceMapper&MockObject */
	private \OCA\Pantry\Db\ItemPriceMapper $itemPriceMapper;
	/** @var \OCP\IDBConnection&MockObject */
	private \OCP\IDBConnection $db;
	private ChecklistService $svc;

	protected function setUp(): void {
		$this->listMapper = $this->createMock(ChecklistMapper::class);
		$this->itemMapper = $this->createMock(ChecklistItemMapper::class);
		$this->itemStoreMapper = $this->createMock(\OCA\Pantry\Db\ItemStoreMapper::class);
		$this->itemPriceMapper = $this->createMock(\OCA\Pantry\Db\ItemPriceMapper::class);
		// Houses reopen recurring items at 08:00 (the default) by which
		// computeNextDueAt snaps the time of day of every non-sub-daily occurrence.
		$house = new House();
		$house->setRecurrenceTime(House::DEFAULT_RECURRENCE_TIME);
		$houseMapper = $this->createMock(HouseMapper::class);
		$houseMapper->method('findById')->willReturn($house);
		$this->db = $this->createMock(\OCP\IDBConnection::class);
		$this->svc = new ChecklistService(
			$this->listMapper,
			$this->itemMapper,
			new RecurrenceService(),
			$this->createMock(ListRoleMapper::class),
			$this->itemStoreMapper,
			$this->itemPriceMapper,
			$houseMapper,
			$this->createMock(\OCA\Pantry\Db\CategoryMapper::class),
			$this->createMock(\OCA\Pantry\Db\ItemLabelMapper::class),
			$this->createMock(\OCA\Pantry\Db\LabelMapper::class),
			$this->createMock(\OCA\Pantry\Db\FieldDefinitionMapper::class),
			$this->createMock(\OCA\Pantry\Db\FieldOptionMapper::class),
			$this->createMock(\OCA\Pantry\Db\FieldValueMapper::class),
			$this->createMock(\OCA\Pantry\Service\CustomFieldReminderService::class),
			$this->db,
		);
	}

	private function makeItem(array $overrides = []): ChecklistItem {
		$item = new ChecklistItem();
		$item->setListId($overrides['listId'] ?? 1);
		$item->setName($overrides['name'] ?? 'Milk');
		$item->setCategoryId($overrides['categoryId'] ?? null);
		$item->setQuantity($overrides['quantity'] ?? null);
		$item->setDone($overrides['done'] ?? false);
		$item->setDoneAt($overrides['doneAt'] ?? null);
		$item->setDoneBy($overrides['doneBy'] ?? null);
		$item->setRrule($overrides['rrule'] ?? null);
		$item->setRepeatFromCompletion($overrides['repeatFromCompletion'] ?? false);
		$item->setDeleteOnDone($overrides['deleteOnDone'] ?? false);
		$item->setNextDueAt($overrides['nextDueAt'] ?? null);
		$item->setSortOrder($overrides['sortOrder'] ?? 0);
		$item->setCreatedAt($overrides['createdAt'] ?? 0);
		$item->setUpdatedAt($overrides['updatedAt'] ?? 0);
		$item->setArchivedAt($overrides['archivedAt'] ?? null);
		return $item;
	}

	public function testListItemsAutoUnchecksDueRecurring(): void {
		$now = strtotime('2033-05-18 12:00:00 UTC'); // past the 08:00 reopen time
		$dueItem = $this->makeItem([
			'done' => true,
			'doneAt' => $now - 86400 * 8,
			'doneBy' => 'alice',
			'rrule' => 'FREQ=WEEKLY',
			'repeatFromCompletion' => true,
			'nextDueAt' => $now - 10,
		]);
		$freshItem = $this->makeItem([
			'done' => true,
			'doneAt' => $now - 3600,
			'doneBy' => 'alice',
			'rrule' => 'FREQ=WEEKLY',
			'nextDueAt' => $now + 86400 * 3,
		]);

		$this->itemMapper->method('findByList')->willReturn([$dueItem, $freshItem]);
		$this->itemMapper->method('findDueRecurring')->willReturn([$dueItem]);
		$this->listMapper->method('findById')->willReturn(new Checklist());
		$this->itemMapper->expects($this->once())
			->method('update')
			->with($this->callback(function (ChecklistItem $i) {
				return $i->getDone() === false
					&& $i->getDoneAt() === null
					&& $i->getDoneBy() === null
					&& $i->getNextDueAt() === null;
			}));

		$result = $this->svc->listItems(1, 'custom', 'name_asc', $now);
		$this->assertCount(2, $result);
		$this->assertFalse($result[0]->getDone(), 'Due item should be reopened');
		$this->assertTrue($result[1]->getDone(), 'Fresh item should stay done');
	}

	public function testToggleItemOnNonRecurringDoesNotSetNextDue(): void {
		$item = $this->makeItem();
		$this->itemMapper->method('findById')->willReturn($item);
		$this->itemMapper->expects($this->once())->method('update')->willReturn($item);

		$toggled = $this->svc->toggleItem(42, 'alice', 1_000_000_000);
		$this->assertTrue($toggled->getDone());
		$this->assertSame('alice', $toggled->getDoneBy());
		$this->assertSame(1_000_000_000, $toggled->getDoneAt());
		$this->assertNull($toggled->getNextDueAt());
	}

	public function testToggleItemFromCompletionModeComputesNextDueFromNow(): void {
		$now = 1_700_000_000; // 2023-11-14 22:13:20 UTC
		$item = $this->makeItem([
			'rrule' => 'FREQ=WEEKLY',
			'repeatFromCompletion' => true,
			'createdAt' => $now - 86400 * 30, // irrelevant in this mode
		]);
		$this->itemMapper->method('findById')->willReturn($item);
		$this->itemMapper->expects($this->once())->method('update')->willReturn($item);

		$toggled = $this->svc->toggleItem(42, 'alice', $now);
		$this->assertTrue($toggled->getDone());
		// Raw next occurrence, one week on. The reopen time is applied later, by
		// the background job, not baked into next_due_at.
		$this->assertSame($now + 7 * 86400, $toggled->getNextDueAt());
	}

	public function testToggleItemFixedScheduleModeComputesFromCreatedAtAnchor(): void {
		// createdAt is a Monday at 00:00 UTC, and we tick off on the following Wednesday.
		$anchor = strtotime('2026-04-06 00:00:00 UTC'); // Monday
		$now = strtotime('2026-04-08 10:00:00 UTC');    // Wednesday
		$expected = strtotime('2026-04-13 00:00:00 UTC'); // next Monday, raw (reopen time applied by the job)

		$item = $this->makeItem([
			'rrule' => 'FREQ=WEEKLY',
			'repeatFromCompletion' => false,
			'createdAt' => $anchor,
		]);
		$this->itemMapper->method('findById')->willReturn($item);
		$this->itemMapper->expects($this->once())->method('update')->willReturn($item);

		$toggled = $this->svc->toggleItem(42, 'alice', $now);
		$this->assertTrue($toggled->getDone());
		$this->assertSame($expected, $toggled->getNextDueAt());
	}

	public function testToggleItemSubDailyComputesNextFromNow(): void {
		// Hourly schedules are day-time agnostic: next_due_at is one hour on.
		$now = 1_700_000_000;
		$item = $this->makeItem([
			'rrule' => 'FREQ=HOURLY',
			'repeatFromCompletion' => true,
		]);
		$this->itemMapper->method('findById')->willReturn($item);
		$this->itemMapper->expects($this->once())->method('update')->willReturn($item);

		$toggled = $this->svc->toggleItem(42, 'alice', $now);
		$this->assertSame($now + 3600, $toggled->getNextDueAt());
	}

	public function testToggleItemFixedDailyAdvancesPastToday(): void {
		// Fixed daily schedule anchored at 11:30, ticked off today at 10:00.
		// next_due_at must be tomorrow's occurrence, not today's — otherwise an
		// early reopen at the house reopen time would re-fire it the same day.
		$anchor = strtotime('2026-04-06 11:30:00 UTC');
		$now = strtotime('2026-04-08 10:00:00 UTC');
		$expected = strtotime('2026-04-09 11:30:00 UTC');

		$item = $this->makeItem([
			'rrule' => 'FREQ=DAILY',
			'repeatFromCompletion' => false,
			'createdAt' => $anchor,
		]);
		$this->itemMapper->method('findById')->willReturn($item);
		$this->itemMapper->expects($this->once())->method('update')->willReturn($item);

		$toggled = $this->svc->toggleItem(42, 'alice', $now);
		$this->assertSame($expected, $toggled->getNextDueAt());
	}

	public function testUncheckItemsClearsDoneStateAndFields(): void {
		$done = $this->makeItem(['done' => true, 'doneAt' => 123, 'doneBy' => 'bob', 'nextDueAt' => 999]);
		$done->setId(42);
		$this->itemMapper->method('findById')->willReturn($done);
		$this->itemMapper->expects($this->once())->method('update')->willReturn($done);

		$changed = $this->svc->uncheckItems([42], 1_000);
		$this->assertCount(1, $changed);
		$this->assertFalse($done->getDone());
		$this->assertNull($done->getDoneAt());
		$this->assertNull($done->getDoneBy());
		$this->assertNull($done->getNextDueAt());
		$this->assertSame(1_000, $done->getUpdatedAt());
	}

	public function testUncheckItemsLeavesAlreadyUncheckedItemsUntouched(): void {
		$undone = $this->makeItem(['done' => false]);
		$undone->setId(7);
		$this->itemMapper->method('findById')->willReturn($undone);
		$this->itemMapper->expects($this->never())->method('update');

		$this->assertSame([], $this->svc->uncheckItems([7]));
	}

	public function testUncheckItemsSkipsMissingItems(): void {
		$this->itemMapper->method('findById')
			->willThrowException(new DoesNotExistException('gone'));
		$this->itemMapper->expects($this->never())->method('update');

		$this->assertSame([], $this->svc->uncheckItems([999]));
	}

	public function testUncheckItemsRunsInASingleTransaction(): void {
		$a = $this->makeItem(['done' => true]);
		$a->setId(1);
		$b = $this->makeItem(['done' => true]);
		$b->setId(2);
		$this->itemMapper->method('findById')->willReturnMap([
			[1, false, $a],
			[2, false, $b],
		]);

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');
		$this->db->expects($this->never())->method('rollBack');
		$this->itemMapper->expects($this->exactly(2))->method('update');

		$changed = $this->svc->uncheckItems([1, 2]);
		$this->assertCount(2, $changed);
	}

	public function testReopenDueItemsWaitsUntilReopenTime(): void {
		// Item due today at 13:00 (raw). House reopen time is 08:00. Before 08:00
		// the job must not reopen it yet.
		$item = $this->makeItem([
			'rrule' => 'FREQ=DAILY',
			'repeatFromCompletion' => false,
			'createdAt' => strtotime('2026-04-06 13:00:00 UTC'),
			'done' => true,
			'nextDueAt' => strtotime('2026-04-08 13:00:00 UTC'),
		]);
		$this->listMapper->method('findById')->willReturn(new Checklist());
		$this->itemMapper->method('findDueRecurring')->willReturn([$item]);
		$this->itemMapper->expects($this->never())->method('update');

		$reopened = $this->svc->reopenDueItems(strtotime('2026-04-08 07:00:00 UTC'));
		$this->assertCount(0, $reopened);
	}

	public function testReopenDueItemsReopensEarlyOnceReopenTimePassed(): void {
		// Same item, now 09:00 — past the 08:00 reopen time but before the raw
		// 13:00 occurrence. It reopens now, and next_due_at advances to tomorrow.
		$item = $this->makeItem([
			'rrule' => 'FREQ=DAILY',
			'repeatFromCompletion' => false,
			'createdAt' => strtotime('2026-04-06 13:00:00 UTC'),
			'done' => true,
			'nextDueAt' => strtotime('2026-04-08 13:00:00 UTC'),
		]);
		$this->listMapper->method('findById')->willReturn(new Checklist());
		$this->itemMapper->method('findDueRecurring')->willReturn([$item]);
		$this->itemMapper->expects($this->once())->method('update')->willReturn($item);

		$reopened = $this->svc->reopenDueItems(strtotime('2026-04-08 09:00:00 UTC'));
		$this->assertCount(1, $reopened);
		$this->assertFalse($reopened[0]->getDone());
		$this->assertSame(strtotime('2026-04-09 13:00:00 UTC'), $reopened[0]->getNextDueAt());
	}

	public function testToggleItemCheckingOffClearsEverything(): void {
		$item = $this->makeItem([
			'done' => true,
			'doneAt' => 123,
			'doneBy' => 'alice',
			'rrule' => 'FREQ=WEEKLY',
			'nextDueAt' => 456,
		]);
		$this->itemMapper->method('findById')->willReturn($item);
		$this->itemMapper->expects($this->once())->method('update')->willReturn($item);

		$toggled = $this->svc->toggleItem(42, 'alice', 999);
		$this->assertFalse($toggled->getDone());
		$this->assertNull($toggled->getDoneAt());
		$this->assertNull($toggled->getDoneBy());
		$this->assertNull($toggled->getNextDueAt());
	}

	public function testAddItemRejectsEmptyName(): void {
		$this->listMapper->method('findById')->willReturn(new Checklist());
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->addItem(1, ['name' => '  ']);
	}

	public function testAddItemRejectsBadRrule(): void {
		$this->listMapper->method('findById')->willReturn(new Checklist());
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->addItem(1, ['name' => 'Eggs', 'rrule' => 'not valid']);
	}

	public function testToggleItemSoftDeletesOnceItemWhenMarkingDone(): void {
		$now = 1_700_000_000;
		$item = $this->makeItem([
			'deleteOnDone' => true,
		]);
		$this->itemMapper->method('findById')->willReturn($item);
		// Once items are soft-deleted (deleted_at set) rather than removed.
		$this->itemMapper->expects($this->never())->method('delete');
		$this->itemMapper->expects($this->once())
			->method('update')
			->with($this->callback(function (ChecklistItem $i) use ($now) {
				return $i->getDone() === true
					&& $i->getDeletedAt() === $now;
			}));

		$toggled = $this->svc->toggleItem(42, 'alice', $now);
		$this->assertTrue($toggled->getDone());
		$this->assertSame($now, $toggled->getDoneAt());
		$this->assertSame('alice', $toggled->getDoneBy());
		$this->assertSame($now, $toggled->getDeletedAt());
	}

	public function testCopyItemClonesFieldsAndStartsUndone(): void {
		$source = $this->makeItem([
			'listId' => 10,
			'name' => 'Milk',
			'categoryId' => 5,
			'quantity' => '1L',
			'done' => true,
			'doneAt' => 999,
			'doneBy' => 'alice',
			'deleteOnDone' => true,
		]);
		$source->setDescription('whole');

		$this->itemMapper->method('findById')->willReturn($source);
		$this->listMapper->method('findById')->willReturn(new Checklist());

		$captured = null;
		$this->itemMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (ChecklistItem $i) use (&$captured) {
				$captured = $i;
				return $i;
			});

		$copy = $this->svc->copyItem(42, 20, 'bob', null, null);

		$this->assertNotNull($captured);
		$this->assertSame(20, $captured->getListId(), 'copy lives on the target list');
		$this->assertSame('Milk', $captured->getName());
		$this->assertSame('whole', $captured->getDescription());
		$this->assertSame(5, $captured->getCategoryId());
		$this->assertSame('1L', $captured->getQuantity());
		$this->assertTrue($captured->getDeleteOnDone());
		$this->assertFalse($captured->getDone(), 'copy is not yet done even if source was');
		$this->assertNull($captured->getDoneAt());
		$this->assertNull($captured->getDoneBy());
		$this->assertSame('bob', $captured->getAddedBy());
		$this->assertSame(0, $captured->getSortOrder());
		$this->assertSame($copy, $captured);
	}

	public function testCopyItemPropagatesNewImageFileIdAndOwner(): void {
		$source = $this->makeItem(['listId' => 10]);
		$source->setImageFileId(111);
		$source->setImageUploadedBy('alice');

		$this->itemMapper->method('findById')->willReturn($source);
		$this->listMapper->method('findById')->willReturn(new Checklist());

		$captured = null;
		$this->itemMapper->method('insert')
			->willReturnCallback(function (ChecklistItem $i) use (&$captured) {
				$captured = $i;
				return $i;
			});

		$this->svc->copyItem(42, 20, 'bob', 222, 'bob');

		$this->assertSame(222, $captured->getImageFileId(), 'image fileId is the freshly duplicated one');
		$this->assertSame('bob', $captured->getImageUploadedBy(), 'image owner is the copier');
	}

	public function testCopyItemRecomputesNextDueForFixedScheduleRecurrence(): void {
		$now = strtotime('2026-04-08 10:00:00 UTC');
		$source = $this->makeItem([
			'listId' => 10,
			'rrule' => 'FREQ=WEEKLY',
			'repeatFromCompletion' => false,
			// Anchor irrelevant: copy uses its own createdAt = now.
		]);

		$this->itemMapper->method('findById')->willReturn($source);
		$this->listMapper->method('findById')->willReturn(new Checklist());

		$captured = null;
		$this->itemMapper->method('insert')
			->willReturnCallback(function (ChecklistItem $i) use (&$captured) {
				$captured = $i;
				return $i;
			});

		// Freeze time so the test is deterministic. We can't override time() in
		// the service directly, so we just assert a non-null next_due_at in the
		// future for fixed-schedule items.
		$copy = $this->svc->copyItem(42, 20, 'bob', null, null);
		$this->assertNotNull($copy->getNextDueAt(), 'fixed-schedule copy gets a scheduled next due');
		$this->assertGreaterThanOrEqual(time(), $copy->getNextDueAt());
	}

	public function testCopyItemLeavesNextDueNullForRepeatFromCompletion(): void {
		$source = $this->makeItem([
			'listId' => 10,
			'rrule' => 'FREQ=WEEKLY',
			'repeatFromCompletion' => true,
		]);

		$this->itemMapper->method('findById')->willReturn($source);
		$this->listMapper->method('findById')->willReturn(new Checklist());

		$this->itemMapper->method('insert')->willReturnArgument(0);

		$copy = $this->svc->copyItem(42, 20, 'bob', null, null);
		$this->assertNull(
			$copy->getNextDueAt(),
			'from-completion items only get a next_due once the user marks them done',
		);
	}

	public function testDeleteItemSoftDeletes(): void {
		$item = $this->makeItem();
		$this->itemMapper->method('findById')->willReturn($item);
		$this->itemMapper->expects($this->never())->method('delete');
		$this->itemMapper->expects($this->once())
			->method('update')
			->with($this->callback(function (ChecklistItem $i) {
				return $i->getDeletedAt() !== null;
			}));

		$this->svc->deleteItem(42);
		$this->assertNotNull($item->getDeletedAt());
	}

	public function testToggleItemOnceItemIgnoresFlagWhenUnchecking(): void {
		// An already-done once item can still be unchecked (e.g., via an
		// already-cached client) — the flag only triggers on the done transition.
		$item = $this->makeItem([
			'done' => true,
			'doneAt' => 123,
			'doneBy' => 'alice',
			'deleteOnDone' => true,
		]);
		$this->itemMapper->method('findById')->willReturn($item);
		$this->itemMapper->expects($this->once())->method('update')->willReturn($item);
		$this->itemMapper->expects($this->never())->method('delete');

		$toggled = $this->svc->toggleItem(42, 'alice', 999);
		$this->assertFalse($toggled->getDone());
		$this->assertNull($toggled->getDoneAt());
		$this->assertNull($toggled->getDoneBy());
	}

	public function testAddItemStoresDeleteOnDoneFlag(): void {
		$this->listMapper->method('findById')->willReturn(new Checklist());
		$captured = null;
		$this->itemMapper->method('insert')
			->willReturnCallback(function (ChecklistItem $i) use (&$captured) {
				$captured = $i;
				return $i;
			});

		$this->svc->addItem(1, ['name' => 'Lightbulb', 'deleteOnDone' => true]);
		$this->assertNotNull($captured);
		$this->assertTrue($captured->getDeleteOnDone());
	}

	public function testAddItemAppendsAtMaxSortOrderPlusOne(): void {
		$this->listMapper->method('findById')->willReturn(new Checklist());
		$this->itemMapper->method('maxSortOrder')->with(1)->willReturn(4);
		$captured = null;
		$this->itemMapper->method('insert')
			->willReturnCallback(function (ChecklistItem $i) use (&$captured) {
				$captured = $i;
				return $i;
			});

		$this->svc->addItem(1, ['name' => 'Bread']);
		$this->assertNotNull($captured);
		$this->assertSame(5, $captured->getSortOrder());
	}

	public function testAddItemUsesZeroSortOrderForEmptyList(): void {
		$this->listMapper->method('findById')->willReturn(new Checklist());
		$this->itemMapper->method('maxSortOrder')->with(1)->willReturn(null);
		$captured = null;
		$this->itemMapper->method('insert')
			->willReturnCallback(function (ChecklistItem $i) use (&$captured) {
				$captured = $i;
				return $i;
			});

		$this->svc->addItem(1, ['name' => 'Bread']);
		$this->assertNotNull($captured);
		$this->assertSame(0, $captured->getSortOrder());
	}

	public function testAddItemHonoursExplicitSortOrderOverMax(): void {
		$this->listMapper->method('findById')->willReturn(new Checklist());
		$this->itemMapper->expects($this->never())->method('maxSortOrder');
		$captured = null;
		$this->itemMapper->method('insert')
			->willReturnCallback(function (ChecklistItem $i) use (&$captured) {
				$captured = $i;
				return $i;
			});

		$this->svc->addItem(1, ['name' => 'Bread', 'sortOrder' => 2]);
		$this->assertNotNull($captured);
		$this->assertSame(2, $captured->getSortOrder());
	}

	public function testAddItemStoresSetPrice(): void {
		$this->listMapper->method('findById')->willReturn(new Checklist());
		$this->itemMapper->method('insert')->willReturnCallback(fn (ChecklistItem $i) => $i);
		$captured = null;
		$this->itemPriceMapper->method('setPricesForItem')
			->willReturnCallback(function (int $id, array $prices) use (&$captured): void {
				$captured = $prices;
			});

		$this->svc->addItem(1, [
			'name' => 'Milk',
			'prices' => [['storeId' => null, 'priceType' => 'set', 'priceMin' => 9.99, 'priceCurrency' => 'usd']],
		]);
		// Currency is uppercased; max is null for a 'set' price.
		$this->assertSame([
			['storeId' => null, 'priceType' => 'set', 'priceMin' => 9.99, 'priceMax' => null, 'priceCurrency' => 'USD'],
		], $captured);
	}

	public function testAddItemNormalizesRangePriceOrder(): void {
		$this->listMapper->method('findById')->willReturn(new Checklist());
		$this->itemMapper->method('insert')->willReturnCallback(fn (ChecklistItem $i) => $i);
		$captured = null;
		$this->itemPriceMapper->method('setPricesForItem')
			->willReturnCallback(function (int $id, array $prices) use (&$captured): void {
				$captured = $prices;
			});

		// Min/max supplied out of order are swapped; a per-store price is kept.
		$this->svc->addItem(1, [
			'name' => 'Milk',
			'prices' => [['storeId' => 5, 'priceType' => 'range', 'priceMin' => 10, 'priceMax' => 1, 'priceCurrency' => 'ILS']],
		]);
		$this->assertSame([
			['storeId' => 5, 'priceType' => 'range', 'priceMin' => 1.0, 'priceMax' => 10.0, 'priceCurrency' => 'ILS'],
		], $captured);
	}

	public function testAddItemDropsPriceWithoutMinAmount(): void {
		$this->listMapper->method('findById')->willReturn(new Checklist());
		$this->itemMapper->method('insert')->willReturnCallback(fn (ChecklistItem $i) => $i);
		$captured = null;
		$this->itemPriceMapper->method('setPricesForItem')
			->willReturnCallback(function (int $id, array $prices) use (&$captured): void {
				$captured = $prices;
			});

		$this->svc->addItem(1, [
			'name' => 'Milk',
			'prices' => [['storeId' => null, 'priceType' => 'set', 'priceCurrency' => 'USD']],
		]);
		$this->assertSame([], $captured);
	}

	public function testAddItemCollapsesDuplicateStorelessAndKeepsPerStore(): void {
		$this->listMapper->method('findById')->willReturn(new Checklist());
		$this->itemMapper->method('insert')->willReturnCallback(fn (ChecklistItem $i) => $i);
		$captured = null;
		$this->itemPriceMapper->method('setPricesForItem')
			->willReturnCallback(function (int $id, array $prices) use (&$captured): void {
				$captured = $prices;
			});

		$this->svc->addItem(1, [
			'name' => 'Milk',
			'prices' => [
				['storeId' => null, 'priceType' => 'set', 'priceMin' => 5, 'priceCurrency' => 'USD'],
				['storeId' => null, 'priceType' => 'set', 'priceMin' => 6, 'priceCurrency' => 'USD'],
				['storeId' => 7, 'priceType' => 'set', 'priceMin' => 9, 'priceCurrency' => 'USD'],
			],
		]);
		// Only the last store-less entry survives; the per-store price is kept.
		$this->assertSame([
			['storeId' => null, 'priceType' => 'set', 'priceMin' => 6.0, 'priceMax' => null, 'priceCurrency' => 'USD'],
			['storeId' => 7, 'priceType' => 'set', 'priceMin' => 9.0, 'priceMax' => null, 'priceCurrency' => 'USD'],
		], $captured);
	}

	public function testUpdateItemClearsPricesWithEmptyArray(): void {
		$item = $this->withId($this->makeItem(), 9);
		$this->itemMapper->method('findById')->willReturn($item);
		$captured = null;
		$this->itemPriceMapper->method('setPricesForItem')
			->willReturnCallback(function (int $id, array $prices) use (&$captured): void {
				$captured = $prices;
			});

		$this->svc->updateItem(9, ['prices' => []]);
		$this->assertSame([], $captured);
	}

	public function testUpdateItemLeavesPricesUntouchedWhenKeyAbsent(): void {
		$item = $this->withId($this->makeItem(), 9);
		$this->itemMapper->method('findById')->willReturn($item);
		// No 'prices' key in the patch means the price set is not rewritten.
		$this->itemPriceMapper->expects($this->never())->method('setPricesForItem');

		$this->svc->updateItem(9, ['name' => 'Renamed']);
		$this->assertSame('Renamed', $item->getName());
	}

	public function testAddItemStoresAddedByUid(): void {
		$this->listMapper->method('findById')->willReturn(new Checklist());
		$captured = null;
		$this->itemMapper->method('insert')
			->willReturnCallback(function (ChecklistItem $i) use (&$captured) {
				$captured = $i;
				return $i;
			});

		$this->svc->addItem(1, ['name' => 'Eggs'], 'alice');
		$this->assertNotNull($captured);
		$this->assertSame('alice', $captured->getAddedBy());
	}

	public function testAddItemAttachesStoresWhenProvided(): void {
		$this->listMapper->method('findById')->willReturn(new Checklist());
		$saved = $this->withId($this->makeItem(), 42);
		$this->itemMapper->method('insert')->willReturn($saved);

		$this->itemStoreMapper->expects($this->once())
			->method('setStoresForItem')
			->with(42, [3, 7]);

		$this->svc->addItem(1, ['name' => 'Milk', 'storeIds' => [3, 7]]);
	}

	public function testAddItemSkipsStoreWriteWhenKeyAbsent(): void {
		$this->listMapper->method('findById')->willReturn(new Checklist());
		$this->itemMapper->method('insert')->willReturn($this->withId($this->makeItem(), 42));

		$this->itemStoreMapper->expects($this->never())->method('setStoresForItem');

		$this->svc->addItem(1, ['name' => 'Milk']);
	}

	public function testUpdateItemReplacesStoresWhenKeyPresent(): void {
		$item = $this->withId($this->makeItem(), 9);
		$this->itemMapper->method('findById')->willReturn($item);

		$this->itemStoreMapper->expects($this->once())
			->method('setStoresForItem')
			->with(9, [1]);

		$this->svc->updateItem(9, ['storeIds' => [1]]);
	}

	public function testPermanentlyDeleteItemRemovesStoreLinks(): void {
		$item = $this->withId($this->makeItem(), 12);
		$this->itemMapper->method('findById')->willReturn($item);

		$this->itemStoreMapper->expects($this->once())->method('deleteByItem')->with(12);
		$this->itemMapper->expects($this->once())->method('delete')->with($item);

		$this->svc->permanentlyDeleteItem(12);
	}

	private function withId(ChecklistItem $item, int $id): ChecklistItem {
		$ref = new \ReflectionProperty($item, 'id');
		$ref->setValue($item, $id);
		return $item;
	}

	public function testListForHousePassesSortBy(): void {
		$this->listMapper->expects($this->once())
			->method('findByHouse')
			->with(42, 'name_asc')
			->willReturn([]);

		$this->svc->listForHouse(42, 'name_asc');
	}

	public function testReorderListsUpdatesSortOrderForHouseLists(): void {
		$a = new Checklist();
		$a->setId(1);
		$a->setHouseId(7);
		$a->setSortOrder(0);
		$b = new Checklist();
		$b->setId(2);
		$b->setHouseId(7);
		$b->setSortOrder(1);

		$this->listMapper->method('findById')->willReturnMap([
			[1, $a],
			[2, $b],
		]);

		$updated = [];
		$this->listMapper->expects($this->exactly(2))
			->method('update')
			->willReturnCallback(function (Checklist $l) use (&$updated) {
				$updated[(int)$l->getId()] = $l->getSortOrder();
				return $l;
			});

		$this->svc->reorderLists(7, [
			['id' => 1, 'sortOrder' => 1],
			['id' => 2, 'sortOrder' => 0],
		]);

		$this->assertSame(1, $updated[1]);
		$this->assertSame(0, $updated[2]);
	}

	public function testReorderListsSkipsListsFromOtherHouses(): void {
		$other = new Checklist();
		$other->setId(5);
		$other->setHouseId(99);
		$other->setSortOrder(0);

		$this->listMapper->method('findById')->willReturn($other);
		$this->listMapper->expects($this->never())->method('update');

		$this->svc->reorderLists(7, [['id' => 5, 'sortOrder' => 3]]);
	}

	public function testReorderItemsRunsInASingleTransaction(): void {
		$a = $this->makeItem(['listId' => 1]);
		$a->setId(1);
		$b = $this->makeItem(['listId' => 1]);
		$b->setId(2);
		$this->itemMapper->method('findById')->willReturnMap([
			[1, false, $a],
			[2, false, $b],
		]);

		// The whole reseed is wrapped in one begin/commit; both rows update inside.
		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');
		$this->db->expects($this->never())->method('rollBack');
		$this->itemMapper->expects($this->exactly(2))->method('update');

		$this->svc->reorderItems(1, [
			['id' => 1, 'sortOrder' => 1],
			['id' => 2, 'sortOrder' => 0],
		]);

		$this->assertSame(1, $a->getSortOrder());
		$this->assertSame(0, $b->getSortOrder());
	}

	public function testReorderItemsRollsBackOnFailure(): void {
		$a = $this->makeItem(['listId' => 1]);
		$a->setId(1);
		$this->itemMapper->method('findById')->willReturn($a);
		$this->itemMapper->method('update')->willThrowException(new \RuntimeException('boom'));

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->never())->method('commit');
		$this->db->expects($this->once())->method('rollBack');

		$this->expectException(\RuntimeException::class);
		$this->svc->reorderItems(1, [['id' => 1, 'sortOrder' => 0]]);
	}

	public function testAddItemLeavesAddedByNullWhenOmitted(): void {
		// Back-compat: rows created without a uid (e.g., older callers, or
		// migrated data) leave added_by null, which the UI treats as "unknown".
		$this->listMapper->method('findById')->willReturn(new Checklist());
		$captured = null;
		$this->itemMapper->method('insert')
			->willReturnCallback(function (ChecklistItem $i) use (&$captured) {
				$captured = $i;
				return $i;
			});

		$this->svc->addItem(1, ['name' => 'Bread']);
		$this->assertNotNull($captured);
		$this->assertNull($captured->getAddedBy());
	}

	// ----- List soft-delete + trash -----

	public function testDeleteListSoftDeletesInsteadOfHardDelete(): void {
		$list = new Checklist();
		$list->setHouseId(7);
		$list->setName('Groceries');
		$this->listMapper->method('findById')->willReturn($list);
		$this->listMapper->expects($this->never())->method('delete');
		$this->itemMapper->expects($this->never())->method('deleteByList');
		$this->listMapper->expects($this->once())
			->method('update')
			->with($this->callback(fn (Checklist $l) => $l->getDeletedAt() !== null));

		$this->svc->deleteList(1);
		$this->assertNotNull($list->getDeletedAt());
	}

	public function testListDeletedForHouseDelegatesToMapper(): void {
		$deleted = [new Checklist()];
		$this->listMapper->expects($this->once())
			->method('findDeletedByHouse')
			->with(7)
			->willReturn($deleted);

		$this->assertSame($deleted, $this->svc->listDeletedForHouse(7));
	}

	public function testRestoreListClearsDeletedAt(): void {
		$list = new Checklist();
		$list->setDeletedAt(123);
		$this->listMapper->method('findById')->willReturn($list);
		$this->listMapper->expects($this->once())
			->method('update')
			->with($this->callback(fn (Checklist $l) => $l->getDeletedAt() === null));

		$restored = $this->svc->restoreList(1);
		$this->assertNull($restored->getDeletedAt());
	}

	public function testPermanentlyDeleteListWipesItemsAndRow(): void {
		$list = new Checklist();
		$ref = new \ReflectionProperty($list, 'id');
		$ref->setValue($list, 99);
		$this->listMapper->method('findById')->willReturn($list);
		$this->itemMapper->expects($this->once())->method('deleteByList')->with(99);
		$this->listMapper->expects($this->once())->method('delete')->with($list);

		$this->svc->permanentlyDeleteList(99);
	}

	public function testEmptyListsTrashRemovesListsAndTheirItems(): void {
		$a = new Checklist();
		$refA = new \ReflectionProperty($a, 'id');
		$refA->setValue($a, 1);
		$b = new Checklist();
		$refB = new \ReflectionProperty($b, 'id');
		$refB->setValue($b, 2);

		$this->listMapper->expects($this->once())
			->method('emptyTrashForHouse')
			->with(7)
			->willReturn([$a, $b]);

		$deletedListIds = [];
		$this->itemMapper->expects($this->exactly(2))
			->method('deleteByList')
			->willReturnCallback(function (int $id) use (&$deletedListIds) {
				$deletedListIds[] = $id;
			});

		$this->svc->emptyListsTrash(7);
		$this->assertSame([1, 2], $deletedListIds);
	}

	// ----- Batch (group) actions -----

	public function testMoveItemsSetsListIdOnEach(): void {
		$a = $this->makeItem(['listId' => 1]);
		$b = $this->makeItem(['listId' => 1]);
		$this->listMapper->method('findById')->willReturn(new Checklist());
		$this->itemMapper->method('findById')->willReturnOnConsecutiveCalls($a, $b);
		$this->itemMapper->expects($this->exactly(2))->method('update');

		$result = $this->svc->moveItems([10, 20], 5);

		$this->assertCount(2, $result);
		$this->assertSame(5, $a->getListId());
		$this->assertSame(5, $b->getListId());
	}

	public function testMoveItemsSkipsMissing(): void {
		$a = $this->makeItem(['listId' => 1]);
		$this->listMapper->method('findById')->willReturn(new Checklist());
		$this->itemMapper->method('findById')->willReturnCallback(function (int $id) use ($a) {
			if ($id === 10) {
				return $a;
			}
			throw new DoesNotExistException('missing');
		});
		$this->itemMapper->expects($this->once())->method('update');

		$result = $this->svc->moveItems([10, 999], 5);

		$this->assertCount(1, $result);
	}

	public function testSetItemsCategoryAssignsEach(): void {
		$a = $this->makeItem(['categoryId' => null]);
		$b = $this->makeItem(['categoryId' => 3]);
		$this->itemMapper->method('findById')->willReturnOnConsecutiveCalls($a, $b);
		$this->itemMapper->expects($this->exactly(2))->method('update');

		$assigned = $this->svc->setItemsCategory([1, 2], 7);

		$this->assertCount(2, $assigned);
		$this->assertSame(7, $a->getCategoryId());
		$this->assertSame(7, $b->getCategoryId());
	}

	public function testSetItemsCategoryClearsWhenNull(): void {
		$c = $this->makeItem(['categoryId' => 7]);
		$this->itemMapper->method('findById')->willReturn($c);
		$this->itemMapper->expects($this->once())->method('update');

		$this->svc->setItemsCategory([1], null);

		$this->assertNull($c->getCategoryId());
	}

	public function testDeleteItemsSoftDeletesEach(): void {
		$a = $this->makeItem();
		$b = $this->makeItem();
		$this->itemMapper->method('findById')->willReturnOnConsecutiveCalls($a, $b);
		$this->itemMapper->expects($this->exactly(2))->method('update');
		$this->itemMapper->expects($this->never())->method('delete');

		$this->svc->deleteItems([1, 2]);

		$this->assertNotNull($a->getDeletedAt());
		$this->assertNotNull($b->getDeletedAt());
	}

	public function testPermanentlyDeleteItemsHardDeletesEach(): void {
		$a = $this->makeItem();
		$b = $this->makeItem();
		$this->itemMapper->method('findById')->willReturnOnConsecutiveCalls($a, $b);
		$this->itemMapper->expects($this->exactly(2))->method('delete');
		$this->itemMapper->expects($this->never())->method('update');

		$this->svc->permanentlyDeleteItems([1, 2]);
	}

	public function testArchiveItemSetsArchivedAt(): void {
		$item = $this->makeItem();
		$this->itemMapper->method('findById')->willReturn($item);
		$this->itemMapper->expects($this->never())->method('delete');
		$this->itemMapper->expects($this->once())
			->method('update')
			->with($this->callback(fn (ChecklistItem $i) => $i->getArchivedAt() !== null));

		$this->svc->archiveItem(42);
		$this->assertNotNull($item->getArchivedAt());
	}

	public function testUnarchiveItemClearsArchivedAt(): void {
		$item = $this->makeItem(['archivedAt' => 123]);
		$this->itemMapper->method('findById')->willReturn($item);
		$this->itemMapper->expects($this->once())
			->method('update')
			->with($this->callback(fn (ChecklistItem $i) => $i->getArchivedAt() === null));

		$unarchived = $this->svc->unarchiveItem(42);
		$this->assertNull($unarchived->getArchivedAt());
	}

	public function testListArchivedItemsDelegatesToMapper(): void {
		$items = [$this->makeItem(), $this->makeItem()];
		$this->itemMapper->expects($this->once())
			->method('findArchivedByList')
			->with(7)
			->willReturn($items);

		$this->assertSame($items, $this->svc->listArchivedItems(7));
	}

	public function testArchiveItemsArchivesEach(): void {
		$a = $this->makeItem();
		$b = $this->makeItem();
		$this->itemMapper->method('findById')->willReturnOnConsecutiveCalls($a, $b);
		$this->itemMapper->expects($this->exactly(2))->method('update');
		$this->itemMapper->expects($this->never())->method('delete');

		$this->svc->archiveItems([1, 2]);

		$this->assertNotNull($a->getArchivedAt());
		$this->assertNotNull($b->getArchivedAt());
	}
}
