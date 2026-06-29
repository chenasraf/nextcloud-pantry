<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Db\Checklist;
use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Db\ChecklistItemMapper;
use OCA\Pantry\Db\ChecklistMapper;
use OCA\Pantry\Db\ListRoleMapper;
use OCA\Pantry\Service\ChecklistService;
use OCA\Pantry\Service\RecurrenceService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ChecklistServiceTest extends TestCase {
	/** @var ChecklistMapper&MockObject */
	private ChecklistMapper $listMapper;
	/** @var ChecklistItemMapper&MockObject */
	private ChecklistItemMapper $itemMapper;
	private ChecklistService $svc;

	protected function setUp(): void {
		$this->listMapper = $this->createMock(ChecklistMapper::class);
		$this->itemMapper = $this->createMock(ChecklistItemMapper::class);
		$this->svc = new ChecklistService(
			$this->listMapper,
			$this->itemMapper,
			new RecurrenceService(),
			$this->createMock(ListRoleMapper::class),
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
		return $item;
	}

	public function testListItemsAutoUnchecksDueRecurring(): void {
		$now = 2_000_000_000;
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
		$this->itemMapper->method('findDueRecurring')->with($now)->willReturn([$dueItem]);
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
		$this->assertSame($now + 7 * 86400, $toggled->getNextDueAt());
	}

	public function testToggleItemFixedScheduleModeComputesFromCreatedAtAnchor(): void {
		// createdAt is a Monday at 00:00 UTC, and we tick off on the following Wednesday.
		$anchor = strtotime('2026-04-06 00:00:00 UTC'); // Monday
		$now = strtotime('2026-04-08 10:00:00 UTC');    // Wednesday
		$expected = strtotime('2026-04-13 00:00:00 UTC'); // next Monday

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
}
