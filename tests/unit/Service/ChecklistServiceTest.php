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
		$item->setBought($overrides['bought'] ?? false);
		$item->setBoughtAt($overrides['boughtAt'] ?? null);
		$item->setBoughtBy($overrides['boughtBy'] ?? null);
		$item->setRrule($overrides['rrule'] ?? null);
		$item->setRepeatFromCompletion($overrides['repeatFromCompletion'] ?? false);
		$item->setNextDueAt($overrides['nextDueAt'] ?? null);
		$item->setSortOrder($overrides['sortOrder'] ?? 0);
		$item->setCreatedAt($overrides['createdAt'] ?? 0);
		$item->setUpdatedAt($overrides['updatedAt'] ?? 0);
		return $item;
	}

	public function testListItemsAutoUnchecksDueRecurring(): void {
		$now = 2_000_000_000;
		$dueItem = $this->makeItem([
			'bought' => true,
			'boughtAt' => $now - 86400 * 8,
			'boughtBy' => 'alice',
			'rrule' => 'FREQ=WEEKLY',
			'repeatFromCompletion' => true,
			'nextDueAt' => $now - 10,
		]);
		$freshItem = $this->makeItem([
			'bought' => true,
			'boughtAt' => $now - 3600,
			'boughtBy' => 'alice',
			'rrule' => 'FREQ=WEEKLY',
			'nextDueAt' => $now + 86400 * 3,
		]);

		$this->itemMapper->method('findByList')->willReturn([$dueItem, $freshItem]);
		$this->itemMapper->method('findDueRecurring')->with($now)->willReturn([$dueItem]);
		$this->itemMapper->expects($this->once())
			->method('update')
			->with($this->callback(function (ChecklistItem $i) {
				return $i->getBought() === false
					&& $i->getBoughtAt() === null
					&& $i->getBoughtBy() === null
					&& $i->getNextDueAt() === null;
			}));

		$result = $this->svc->listItems(1, $now);
		$this->assertCount(2, $result);
		$this->assertFalse($result[0]->getBought(), 'Due item should be reopened');
		$this->assertTrue($result[1]->getBought(), 'Fresh item should stay bought');
	}

	public function testToggleItemOnNonRecurringDoesNotSetNextDue(): void {
		$item = $this->makeItem();
		$this->itemMapper->method('findById')->willReturn($item);
		$this->itemMapper->expects($this->once())->method('update')->willReturn($item);

		$toggled = $this->svc->toggleItem(42, 'alice', 1_000_000_000);
		$this->assertTrue($toggled->getBought());
		$this->assertSame('alice', $toggled->getBoughtBy());
		$this->assertSame(1_000_000_000, $toggled->getBoughtAt());
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
		$this->assertTrue($toggled->getBought());
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
		$this->assertTrue($toggled->getBought());
		$this->assertSame($expected, $toggled->getNextDueAt());
	}

	public function testToggleItemCheckingOffClearsEverything(): void {
		$item = $this->makeItem([
			'bought' => true,
			'boughtAt' => 123,
			'boughtBy' => 'alice',
			'rrule' => 'FREQ=WEEKLY',
			'nextDueAt' => 456,
		]);
		$this->itemMapper->method('findById')->willReturn($item);
		$this->itemMapper->expects($this->once())->method('update')->willReturn($item);

		$toggled = $this->svc->toggleItem(42, 'alice', 999);
		$this->assertFalse($toggled->getBought());
		$this->assertNull($toggled->getBoughtAt());
		$this->assertNull($toggled->getBoughtBy());
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
}
