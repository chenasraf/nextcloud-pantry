<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Db\ChecklistItemMapper;
use OCA\Pantry\Db\ItemCategoryMemory;
use OCA\Pantry\Db\ItemCategoryMemoryMapper;
use OCA\Pantry\Db\ItemQuantityMemory;
use OCA\Pantry\Db\ItemQuantityMemoryMapper;
use OCA\Pantry\Service\ItemMemoryService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ItemMemoryServiceTest extends TestCase {
	private const HOUSE = 1;
	private const LIST = 7;
	private const NOW = 1_800_000_000;

	/** @var ItemCategoryMemoryMapper&MockObject */
	private ItemCategoryMemoryMapper $categories;
	/** @var ItemQuantityMemoryMapper&MockObject */
	private ItemQuantityMemoryMapper $quantities;
	/** @var ChecklistItemMapper&MockObject */
	private ChecklistItemMapper $items;
	private ItemMemoryService $svc;

	/** @var list<array{string, int, int, int}> key, category, uses, rejections */
	private array $bumps = [];
	/** @var list<array{int, string, string|null}> list, key, quantity */
	private array $puts = [];

	protected function setUp(): void {
		$this->categories = $this->createMock(ItemCategoryMemoryMapper::class);
		$this->categories->method('bump')->willReturnCallback(
			function (int $houseId, string $key, int $categoryId, int $uses, int $rejections): void {
				$this->bumps[] = [$key, $categoryId, $uses, $rejections];
			},
		);
		$this->quantities = $this->createMock(ItemQuantityMemoryMapper::class);
		$this->quantities->method('put')->willReturnCallback(
			function (int $houseId, int $listId, string $key, ?string $quantity): void {
				$this->puts[] = [$listId, $key, $quantity];
			},
		);
		$this->items = $this->createMock(ChecklistItemMapper::class);
		$this->svc = new ItemMemoryService($this->categories, $this->quantities, $this->items);
	}

	private function item(string $name, ?int $categoryId = null, ?string $quantity = null): ChecklistItem {
		$item = new ChecklistItem();
		$item->setId(3);
		$item->setListId(self::LIST);
		$item->setName($name);
		$item->setCategoryId($categoryId);
		$item->setQuantity($quantity);
		return $item;
	}

	public function testCreateWithCategoryCountsAUse(): void {
		$this->svc->learnCreated(self::HOUSE, $this->item('Milk (2%)', 12), null, now: self::NOW);

		$this->assertSame([['milk', 12, 1, 0]], $this->bumps);
		$this->assertSame([], $this->puts);
	}

	public function testCreateWithQuantityRemembersItForTheList(): void {
		$this->svc->learnCreated(self::HOUSE, $this->item('Milk'), ' 2 L ', now: self::NOW);

		$this->assertSame([[self::LIST, 'milk', '2 L']], $this->puts);
	}

	public function testCreateWithEmptyQuantityRemembersNothing(): void {
		$this->svc->learnCreated(self::HOUSE, $this->item('Milk'), '  ', now: self::NOW);

		$this->assertSame([], $this->puts);
	}

	public function testNameThatFoldsToNothingTeachesNothing(): void {
		$this->svc->learnCreated(self::HOUSE, $this->item('(organic)', 12), '2', ['categoryId' => 4, 'quantity' => true], self::NOW);

		$this->assertSame([], $this->bumps);
		$this->assertSame([], $this->puts);
	}

	public function testCreateWithRejectedCategoryCountsARejection(): void {
		$this->svc->learnCreated(self::HOUSE, $this->item('Milk', 12), null, ['categoryId' => 4], self::NOW);

		$this->assertSame([['milk', 12, 1, 0], ['milk', 4, 0, 1]], $this->bumps);
	}

	public function testRejectedCategoryPickedAnywayIsNotARejection(): void {
		$this->svc->learnCreated(self::HOUSE, $this->item('Milk', 12), null, ['categoryId' => 12], self::NOW);

		$this->assertSame([['milk', 12, 1, 0]], $this->bumps);
	}

	public function testRejectedQuantityIsForgottenAndNotRelearned(): void {
		$this->svc->learnCreated(self::HOUSE, $this->item('Milk'), '3', ['quantity' => true], self::NOW);

		$this->assertSame([[self::LIST, 'milk', null]], $this->puts);
	}

	public function testUpdateChangingCategoryCountsForNewAndAgainstOld(): void {
		$this->svc->learnUpdated(self::HOUSE, $this->item('Milk', 4), $this->item('Milk', 12), false, true, now: self::NOW);

		$this->assertSame([['milk', 12, 1, 0], ['milk', 4, 0, 1]], $this->bumps);
	}

	public function testUpdateClearingCategoryCountsAgainstOld(): void {
		$this->svc->learnUpdated(self::HOUSE, $this->item('Milk', 4), $this->item('Milk'), false, true, now: self::NOW);

		$this->assertSame([['milk', 4, 0, 1]], $this->bumps);
	}

	public function testUpdateKeepingCategoryTeachesNothing(): void {
		$this->svc->learnUpdated(self::HOUSE, $this->item('Milk', 4), $this->item('Milk', 4), false, true, now: self::NOW);

		$this->assertSame([], $this->bumps);
	}

	public function testRenameCountsTheCategoryForTheNewName(): void {
		$this->svc->learnUpdated(self::HOUSE, $this->item('Milk', 4), $this->item('Oat milk', 4), false, true, now: self::NOW);

		$this->assertSame([['oat milk', 4, 1, 0]], $this->bumps);
	}

	public function testRenameThatFoldsToTheSameKeyIsNotARename(): void {
		$this->svc->learnUpdated(self::HOUSE, $this->item('Milk', 4), $this->item('MILK ', 4), false, true, now: self::NOW);

		$this->assertSame([], $this->bumps);
	}

	public function testUpdateSettingQuantityRemembersIt(): void {
		$this->svc->learnUpdated(self::HOUSE, $this->item('Milk', null, '1'), $this->item('Milk', null, '2'), true, true, now: self::NOW);

		$this->assertSame([[self::LIST, 'milk', '2']], $this->puts);
	}

	public function testUpdateClearingQuantityDoesNotForgetIt(): void {
		$this->svc->learnUpdated(self::HOUSE, $this->item('Milk', null, '2'), $this->item('Milk'), true, true, now: self::NOW);

		$this->assertSame([], $this->puts);
	}

	public function testUpdateOfOnHandAmountIsNotRemembered(): void {
		$this->svc->learnUpdated(self::HOUSE, $this->item('Milk', null, '3'), $this->item('Milk', null, '2'), true, false, now: self::NOW);

		$this->assertSame([], $this->puts);
	}

	public function testUpdateLeavingQuantityAloneDoesNotRememberIt(): void {
		$this->svc->learnUpdated(self::HOUSE, $this->item('Milk', null, '2'), $this->item('Milk', 4, '2'), false, true, now: self::NOW);

		$this->assertSame([], $this->puts);
	}

	public function testUpdateWithRejectionPayload(): void {
		$this->svc->learnUpdated(
			self::HOUSE,
			$this->item('Milk'),
			$this->item('Milk', null, '2'),
			true,
			true,
			['categoryId' => 9, 'quantity' => true],
			self::NOW,
		);

		$this->assertSame([['milk', 9, 0, 1]], $this->bumps);
		$this->assertSame([[self::LIST, 'milk', null]], $this->puts);
	}

	public function testSeedCountsCategoriesAndKeepsLatestQuantityPerList(): void {
		$seed = ItemMemoryService::seedFrom([
			['name' => 'Milk', 'categoryId' => 4, 'listId' => 1, 'listKind' => 'checklist', 'quantity' => '1 L'],
			['name' => 'milk (2%)', 'categoryId' => 4, 'listId' => 2, 'listKind' => 'checklist', 'quantity' => '3'],
			['name' => 'Milk', 'categoryId' => 5, 'listId' => 1, 'listKind' => 'checklist', 'quantity' => '2 L'],
			['name' => 'Milk', 'categoryId' => null, 'listId' => 1, 'listKind' => 'checklist', 'quantity' => ''],
			['name' => 'Milk', 'categoryId' => 4, 'listId' => 9, 'listKind' => 'inventory', 'quantity' => '6'],
			['name' => '(x)', 'categoryId' => 4, 'listId' => 1, 'listKind' => 'checklist', 'quantity' => '1'],
		]);

		$this->assertSame([
			['nameKey' => 'milk', 'categoryId' => 4, 'uses' => 3],
			['nameKey' => 'milk', 'categoryId' => 5, 'uses' => 1],
		], $seed['categories']);
		$this->assertSame([
			['listId' => 1, 'nameKey' => 'milk', 'quantity' => '2 L'],
			['listId' => 2, 'nameKey' => 'milk', 'quantity' => '3'],
		], $seed['quantities']);
	}

	public function testSeedKeepsNumericNamesAsText(): void {
		$seed = ItemMemoryService::seedFrom([
			['name' => '7', 'categoryId' => 4, 'listId' => 1, 'listKind' => 'checklist', 'quantity' => '1'],
		]);

		$this->assertSame('7', $seed['categories'][0]['nameKey']);
		$this->assertSame('7', $seed['quantities'][0]['nameKey']);
	}

	public function testSeedSkipsAHouseThatHasMemory(): void {
		$this->categories->method('hasAnyForHouse')->willReturn(true);
		$this->items->expects($this->never())->method('findMemorySeedRows');

		$this->assertFalse($this->svc->seedIfEmpty(self::HOUSE, self::NOW));
	}

	public function testSeedWritesWhatItCounted(): void {
		$this->items->method('findMemorySeedRows')->willReturn([
			['name' => 'Milk', 'categoryId' => 4, 'listId' => 1, 'listKind' => 'checklist', 'quantity' => '1 L'],
			['name' => 'Milk', 'categoryId' => 4, 'listId' => 1, 'listKind' => 'checklist', 'quantity' => null],
		]);

		$this->assertTrue($this->svc->seedIfEmpty(self::HOUSE, self::NOW));
		$this->assertSame([['milk', 4, 2, 0]], $this->bumps);
		$this->assertSame([[1, 'milk', '1 L']], $this->puts);
	}

	public function testFullSnapshotLeavesOutForgottenAmountsAndHiddenLists(): void {
		$this->categories->method('findForHouse')->with(self::HOUSE, null)->willReturn([$this->catRow('milk', 4)]);
		$this->quantities->method('findForHouse')->with(self::HOUSE, null)->willReturn([
			$this->qtyRow(1, 'milk', '2 L'),
			$this->qtyRow(1, 'eggs', null),
			$this->qtyRow(2, 'milk', '3'),
		]);

		$snapshot = $this->svc->snapshot(self::HOUSE, null, static fn (int $listId): bool => $listId === 1, self::NOW);

		$this->assertSame([['nameKey' => 'milk', 'categoryId' => 4, 'uses' => 2, 'rejections' => 1, 'updatedAt' => 100]], $snapshot['categories']);
		$this->assertSame([['listId' => 1, 'nameKey' => 'milk', 'quantity' => '2 L', 'updatedAt' => 100]], $snapshot['quantities']);
		$this->assertSame(self::NOW, $snapshot['serverTime']);
	}

	public function testDeltaSnapshotReportsForgottenAmounts(): void {
		$this->categories->method('findForHouse')->with(self::HOUSE, 50)->willReturn([]);
		$this->quantities->method('findForHouse')->with(self::HOUSE, 50)->willReturn([$this->qtyRow(1, 'eggs', null)]);

		$snapshot = $this->svc->snapshot(self::HOUSE, 50, static fn (): bool => true, self::NOW);

		$this->assertSame([['listId' => 1, 'nameKey' => 'eggs', 'quantity' => null, 'updatedAt' => 100]], $snapshot['quantities']);
	}

	public function testForgetListDropsItsAmountsAndItsOwnCategories(): void {
		$this->quantities->expects($this->once())->method('deleteByList')->with(self::LIST);
		$this->categories->expects($this->once())->method('deleteByCategories')->with([4, 5]);

		$this->svc->forgetList(self::LIST, [4, 5]);
	}

	public function testForgetListWithoutOwnCategoriesLeavesCategoriesAlone(): void {
		$this->categories->expects($this->never())->method('deleteByCategories');

		$this->svc->forgetList(self::LIST, []);
	}

	private function catRow(string $key, int $categoryId): ItemCategoryMemory {
		$row = new ItemCategoryMemory();
		$row->setNameKey($key);
		$row->setCategoryId($categoryId);
		$row->setUses(2);
		$row->setRejections(1);
		$row->setUpdatedAt(100);
		return $row;
	}

	private function qtyRow(int $listId, string $key, ?string $quantity): ItemQuantityMemory {
		$row = new ItemQuantityMemory();
		$row->setListId($listId);
		$row->setNameKey($key);
		$row->setQuantity($quantity);
		$row->setUpdatedAt(100);
		return $row;
	}
}
