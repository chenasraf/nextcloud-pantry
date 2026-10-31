<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Db\Category;
use OCA\Pantry\Db\CategoryMapper;
use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Db\ChecklistItemMapper;
use OCA\Pantry\Db\ItemLabelMapper;
use OCA\Pantry\Db\ItemStoreMapper;
use OCA\Pantry\Db\Label;
use OCA\Pantry\Db\LabelMapper;
use OCA\Pantry\Service\LinkedProductService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\TestCase;

/**
 * A linked inventory item and its checklist item are one product: what one
 * says about it, the other says too.
 */
class LinkedProductServiceTest extends TestCase {
	private const GROCERIES = 10;
	private const FRIDGE = 20;
	private const FREEZER = 21;

	/** @var array<int, ChecklistItem> */
	private array $items = [];
	/** @var array<int, list<int>> */
	private array $stores = [];
	/** @var array<int, list<int>> */
	private array $labels = [];
	/** @var array<int, Category> */
	private array $categories = [];
	/** @var array<int, Label> */
	private array $labelRows = [];
	/** @var list<int> */
	private array $updated = [];

	private LinkedProductService $svc;

	protected function setUp(): void {
		$itemMapper = $this->createMock(ChecklistItemMapper::class);
		$itemMapper->method('findById')->willReturnCallback(function (int $id): ChecklistItem {
			return $this->items[$id] ?? throw new DoesNotExistException('item');
		});
		$itemMapper->method('findLinkedTo')->willReturnCallback(fn (array $ids): array => array_values(array_filter(
			$this->items,
			static fn (ChecklistItem $i): bool => in_array($i->getLinkedItemId(), $ids, true),
		)));
		$itemMapper->method('update')->willReturnCallback(function (ChecklistItem $item): ChecklistItem {
			$this->updated[] = (int)$item->getId();
			return $item;
		});

		$categoryMapper = $this->createMock(CategoryMapper::class);
		$categoryMapper->method('findById')->willReturnCallback(function (int $id): Category {
			return $this->categories[$id] ?? throw new DoesNotExistException('category');
		});
		$labelMapper = $this->createMock(LabelMapper::class);
		$labelMapper->method('findById')->willReturnCallback(function (int $id): Label {
			return $this->labelRows[$id] ?? throw new DoesNotExistException('label');
		});

		$storeMapper = $this->createMock(ItemStoreMapper::class);
		$storeMapper->method('findStoreIdsForItem')->willReturnCallback(fn (int $id): array => $this->stores[$id] ?? []);
		$storeMapper->method('setStoresForItem')->willReturnCallback(function (int $id, array $ids): void {
			$this->stores[$id] = $ids;
		});
		$labelLinks = $this->createMock(ItemLabelMapper::class);
		$labelLinks->method('findLabelIdsForItem')->willReturnCallback(fn (int $id): array => $this->labels[$id] ?? []);
		$labelLinks->method('setLabelsForItem')->willReturnCallback(function (int $id, array $ids): void {
			$this->labels[$id] = $ids;
		});

		$this->svc = new LinkedProductService($itemMapper, $categoryMapper, $labelMapper, $storeMapper, $labelLinks);

		$this->category(1, null);
		$this->category(2, null);
		$this->category(3, self::FRIDGE);
		$this->label(1, null);
		$this->label(2, self::FRIDGE);
		$this->label(3, self::GROCERIES);
	}

	private function item(int $id, int $listId, ?int $linkedTo = null, ?int $categoryId = null): ChecklistItem {
		$item = new ChecklistItem();
		$item->setId($id);
		$item->setListId($listId);
		$item->setName('Emmental');
		$item->setCategoryId($categoryId);
		$item->setQuantity('200 g');
		$item->setLinkedItemId($linkedTo);
		return $this->items[$id] = $item;
	}

	private function category(int $id, ?int $listId): void {
		$category = new Category();
		$category->setId($id);
		$category->setListId($listId);
		$this->categories[$id] = $category;
	}

	private function label(int $id, ?int $listId): void {
		$label = new Label();
		$label->setId($id);
		$label->setListId($listId);
		$this->labelRows[$id] = $label;
	}

	public function testCategoryFromTheInventoryReachesTheRowAndItsOtherInventories(): void {
		$row = $this->item(100, self::GROCERIES, categoryId: 1);
		$fridge = $this->item(200, self::FRIDGE, linkedTo: 100, categoryId: 1);
		$freezer = $this->item(210, self::FREEZER, linkedTo: 100, categoryId: 1);

		$fridge->setCategoryId(2);
		$this->svc->afterChange($fridge, ['categoryId']);

		$this->assertSame(2, $row->getCategoryId());
		$this->assertSame(2, $freezer->getCategoryId());
		$this->assertSame('200 g', $row->getQuantity(), 'stock stays each side\'s own');
		$this->assertEqualsCanonicalizing([100, 210], $this->updated);
	}

	public function testRenamingTheRowRenamesItsInventoryItems(): void {
		$row = $this->item(100, self::GROCERIES);
		$fridge = $this->item(200, self::FRIDGE, linkedTo: 100);

		$row->setName('Comté');
		$this->svc->afterChange($row, ['name', 'quantity']);

		$this->assertSame('Comté', $fridge->getName());
		$this->assertSame('200 g', $fridge->getQuantity());
	}

	public function testACategoryScopedToTheInventoryStaysThere(): void {
		$row = $this->item(100, self::GROCERIES, categoryId: 1);
		$fridge = $this->item(200, self::FRIDGE, linkedTo: 100, categoryId: 3);

		$this->svc->afterChange($fridge, ['categoryId']);

		$this->assertSame(1, $row->getCategoryId());
	}

	public function testStoresAndHouseWideLabelsAreShared(): void {
		$this->item(100, self::GROCERIES);
		$fridge = $this->item(200, self::FRIDGE, linkedTo: 100);
		$this->stores[200] = [5, 6];
		$this->labels[200] = [1, 2];
		$this->labels[100] = [3];

		$this->svc->afterChange($fridge, ['storeIds', 'labelIds']);

		$this->assertSame([5, 6], $this->stores[100]);
		$this->assertEqualsCanonicalizing(
			[1, 3],
			$this->labels[100],
			'the fridge\'s own label stays behind, the groceries\' own one stays on',
		);
	}

	public function testAnUnlinkedItemTouchesNothing(): void {
		$item = $this->item(200, self::FRIDGE, categoryId: 1);
		$this->item(100, self::GROCERIES);

		$this->svc->afterChange($item, ['categoryId']);

		$this->assertSame([], $this->updated);
	}

	public function testATrashedPartnerIsLeftAlone(): void {
		$row = $this->item(100, self::GROCERIES, categoryId: 1);
		$row->setDeletedAt(123);
		$fridge = $this->item(200, self::FRIDGE, linkedTo: 100, categoryId: 2);

		$this->svc->afterChange($fridge, ['categoryId']);

		$this->assertSame(1, $row->getCategoryId());
	}

	public function testLinkingFillsWhatEitherSideLeavesEmpty(): void {
		$row = $this->item(100, self::GROCERIES);
		$fridge = $this->item(200, self::FRIDGE, linkedTo: 100, categoryId: 1);
		$fridge->setBarcode('3017620422003');
		$this->stores[100] = [5];

		$this->svc->align($fridge, $row);

		$this->assertSame(1, $row->getCategoryId());
		$this->assertSame('3017620422003', $row->getBarcode());
		$this->assertSame([5], $this->stores[200]);
	}

	public function testLinkingOverwritesNothing(): void {
		$row = $this->item(100, self::GROCERIES, categoryId: 2);
		$fridge = $this->item(200, self::FRIDGE, linkedTo: 100, categoryId: 1);

		$this->svc->align($fridge, $row);

		$this->assertSame(2, $row->getCategoryId());
		$this->assertSame(1, $fridge->getCategoryId());
		$this->assertSame([], $this->updated);
	}
}
