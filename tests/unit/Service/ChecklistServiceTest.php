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

		$result = $this->svc->listItems(1, 'custom', $now);
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

	public function testToggleItemDeletesOnceItemWhenMarkingDone(): void {
		$now = 1_700_000_000;
		$item = $this->makeItem([
			'deleteOnDone' => true,
		]);
		$this->itemMapper->method('findById')->willReturn($item);
		// Once items are deleted rather than updated when marked done.
		$this->itemMapper->expects($this->once())->method('delete')->with($item);
		$this->itemMapper->expects($this->never())->method('update');

		$toggled = $this->svc->toggleItem(42, 'alice', $now);
		$this->assertTrue($toggled->getDone());
		$this->assertSame($now, $toggled->getDoneAt());
		$this->assertSame('alice', $toggled->getDoneBy());
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
}
