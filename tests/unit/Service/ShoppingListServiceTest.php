<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Db\ShoppingList;
use OCA\Pantry\Db\ShoppingListItem;
use OCA\Pantry\Db\ShoppingListItemMapper;
use OCA\Pantry\Db\ShoppingListMapper;
use OCA\Pantry\Service\RecurrenceService;
use OCA\Pantry\Service\ShoppingListService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ShoppingListServiceTest extends TestCase {
	/** @var ShoppingListMapper&MockObject */
	private ShoppingListMapper $listMapper;
	/** @var ShoppingListItemMapper&MockObject */
	private ShoppingListItemMapper $itemMapper;
	private ShoppingListService $svc;

	protected function setUp(): void {
		$this->listMapper = $this->createMock(ShoppingListMapper::class);
		$this->itemMapper = $this->createMock(ShoppingListItemMapper::class);
		$this->svc = new ShoppingListService(
			$this->listMapper,
			$this->itemMapper,
			new RecurrenceService(),
		);
	}

	private function makeItem(array $overrides = []): ShoppingListItem {
		$item = new ShoppingListItem();
		$item->setListId($overrides['listId'] ?? 1);
		$item->setName($overrides['name'] ?? 'Milk');
		$item->setCategory($overrides['category'] ?? null);
		$item->setQuantity($overrides['quantity'] ?? null);
		$item->setBought($overrides['bought'] ?? false);
		$item->setBoughtAt($overrides['boughtAt'] ?? null);
		$item->setBoughtBy($overrides['boughtBy'] ?? null);
		$item->setRrule($overrides['rrule'] ?? null);
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
		$this->itemMapper->expects($this->once())
			->method('update')
			->with($this->callback(function (ShoppingListItem $i) {
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

	public function testToggleItemOnRecurringComputesNextDue(): void {
		$now = 1_700_000_000; // 2023-11-14 22:13:20 UTC
		$item = $this->makeItem(['rrule' => 'FREQ=WEEKLY']);
		$this->itemMapper->method('findById')->willReturn($item);
		$this->itemMapper->expects($this->once())->method('update')->willReturn($item);

		$toggled = $this->svc->toggleItem(42, 'alice', $now);
		$this->assertTrue($toggled->getBought());
		$this->assertNotNull($toggled->getNextDueAt());
		$this->assertSame($now + 7 * 86400, $toggled->getNextDueAt());
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
		$this->listMapper->method('findById')->willReturn(new ShoppingList());
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->addItem(1, ['name' => '  ']);
	}

	public function testAddItemRejectsBadRrule(): void {
		$this->listMapper->method('findById')->willReturn(new ShoppingList());
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->addItem(1, ['name' => 'Eggs', 'rrule' => 'not valid']);
	}
}
