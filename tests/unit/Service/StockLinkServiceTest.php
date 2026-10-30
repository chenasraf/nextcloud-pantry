<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Db\Checklist;
use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Db\ChecklistItemMapper;
use OCA\Pantry\Db\ChecklistMapper;
use OCA\Pantry\Exception\NotFoundException;
use OCA\Pantry\Service\StockLinkService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Runs the service against an in-memory house: a "Shopping" checklist (10), a
 * "Groceries" checklist (11), a "Kitchen" inventory (20) restocking into
 * Shopping, and a "Garage" inventory (21) restocking into Shopping too.
 */
class StockLinkServiceTest extends TestCase {
	/** @var ChecklistMapper&MockObject */
	private ChecklistMapper $listMapper;
	/** @var ChecklistItemMapper&MockObject */
	private ChecklistItemMapper $itemMapper;
	private StockLinkService $svc;

	/** @var array<int, Checklist> */
	private array $lists = [];
	/** @var array<int, ChecklistItem> */
	private array $items = [];
	private int $nextItemId = 100;

	protected function setUp(): void {
		$this->listMapper = $this->createMock(ChecklistMapper::class);
		$this->itemMapper = $this->createMock(ChecklistItemMapper::class);

		$this->addList(10, Checklist::KIND_CHECKLIST, 'Shopping');
		$this->addList(11, Checklist::KIND_CHECKLIST, 'Groceries');
		$this->addList(20, Checklist::KIND_INVENTORY, 'Kitchen', restockInto: 10);
		$this->addList(21, Checklist::KIND_INVENTORY, 'Garage', restockInto: 10);

		$this->listMapper->method('findById')->willReturnCallback(function (int $id): Checklist {
			return $this->lists[$id] ?? throw new DoesNotExistException('missing');
		});
		$this->listMapper->method('findRestockingInto')->willReturnCallback(fn (int $id): array => array_values(array_filter(
			$this->lists,
			static fn (Checklist $l): bool => $l->getKind() === Checklist::KIND_INVENTORY && $l->getRestockChecklistId() === $id
				&& $l->getDeletedAt() === null && $l->getArchivedAt() === null,
		)));
		$this->listMapper->method('findByHouse')->willReturnCallback(fn (int $houseId, string $sort, ?string $kind): array => array_values(array_filter(
			$this->lists,
			static fn (Checklist $l): bool => $l->getKind() === $kind && $l->getDeletedAt() === null && $l->getArchivedAt() === null,
		)));
		$this->itemMapper->method('findByList')->willReturnCallback(fn (int $listId): array => $this->itemsWhere(
			static fn (ChecklistItem $i): bool => $i->getListId() === $listId && $i->getDeletedAt() === null && $i->getArchivedAt() === null,
		));
		$this->itemMapper->method('findArchivedByList')->willReturnCallback(fn (int $listId): array => $this->itemsWhere(
			static fn (ChecklistItem $i): bool => $i->getListId() === $listId && $i->getArchivedAt() !== null,
		));
		$this->itemMapper->method('findByIds')->willReturnCallback(fn (array $ids): array => $this->itemsWhere(
			static fn (ChecklistItem $i): bool => in_array($i->getId(), $ids, true),
		));
		$this->itemMapper->method('findLinkedTo')->willReturnCallback(fn (array $ids): array => $this->itemsWhere(
			static fn (ChecklistItem $i): bool => $i->getLinkedItemId() !== null && in_array($i->getLinkedItemId(), $ids, true),
		));
		$this->itemMapper->method('findLinkedIdsFromList')->willReturnCallback(fn (int $listId): array => array_values(array_unique(array_map(
			static fn (ChecklistItem $i): int => (int)$i->getLinkedItemId(),
			$this->itemsWhere(static fn (ChecklistItem $i): bool => $i->getListId() === $listId && $i->getLinkedItemId() !== null),
		))));
		$this->itemMapper->method('update')->willReturnArgument(0);

		$this->svc = new StockLinkService($this->listMapper, $this->itemMapper);
	}

	private function addList(int $id, string $kind, string $name, ?int $restockInto = null, int $houseId = 1): Checklist {
		$list = new Checklist();
		$list->setId($id);
		$list->setHouseId($houseId);
		$list->setKind($kind);
		$list->setName($name);
		$list->setRestockChecklistId($restockInto);
		return $this->lists[$id] = $list;
	}

	/**
	 * @param array<string, mixed> $state
	 */
	private function addItem(int $listId, string $name, array $state = []): ChecklistItem {
		$item = new ChecklistItem();
		$item->setId($this->nextItemId++);
		$item->setListId($listId);
		$item->setName($name);
		$item->setDone($state['done'] ?? false);
		$item->setArchivedAt($state['archivedAt'] ?? null);
		$item->setDeletedAt($state['deletedAt'] ?? null);
		$item->setCreatedAt($state['createdAt'] ?? 0);
		$item->setLinkedItemId($state['linkedItemId'] ?? null);
		if (array_key_exists('autoLink', $state)) {
			$item->setAutoLink($state['autoLink']);
		}
		return $this->items[(int)$item->getId()] = $item;
	}

	/**
	 * @param callable(ChecklistItem): bool $filter
	 * @return list<ChecklistItem>
	 */
	private function itemsWhere(callable $filter): array {
		return array_values(array_filter($this->items, $filter));
	}

	// ----- Matching -----

	public function testOneSameNameRowLinksIgnoringCaseAndSpacing(): void {
		$row = $this->addItem(10, '  whole   MILK ');
		$milk = $this->addItem(20, 'Whole milk');

		$this->assertSame($row, $this->svc->match($milk, $this->lists[20]));
		$this->assertSame($row->getId(), $milk->getLinkedItemId());
		$this->assertNotNull($milk->getLinkedAt());
	}

	public function testSeveralMatchesLinkNothing(): void {
		$this->addItem(10, 'Milk');
		$this->addItem(10, 'milk', ['done' => true]);
		$milk = $this->addItem(20, 'Milk');

		$this->assertNull($this->svc->match($milk, $this->lists[20]));
		$this->assertNull($milk->getLinkedItemId());
	}

	public function testMatchingLooksOnlyAtTheRestockChecklist(): void {
		$this->addItem(11, 'Milk');
		$milk = $this->addItem(20, 'Milk');

		$this->assertNull($this->svc->match($milk, $this->lists[20]));
	}

	public function testNoRestockChecklistMatchesNothing(): void {
		$this->lists[20]->setRestockChecklistId(null);
		$this->addItem(10, 'Milk');
		$milk = $this->addItem(20, 'Milk');

		$this->assertNull($this->svc->match($milk, $this->lists[20]));
	}

	/**
	 * @return array<string, array{0: callable(Checklist): void}>
	 */
	public static function unusableRestockChecklists(): array {
		return [
			'archived' => [static fn (Checklist $l) => $l->setArchivedAt(1)],
			'trashed' => [static fn (Checklist $l) => $l->setDeletedAt(1)],
			'not a checklist' => [static fn (Checklist $l) => $l->setKind(Checklist::KIND_TEMPLATE)],
			'another house' => [static fn (Checklist $l) => $l->setHouseId(2)],
		];
	}

	/**
	 * @param callable(Checklist): void $spoil
	 */
	#[DataProvider('unusableRestockChecklists')]
	public function testAnUnusableRestockChecklistBehavesAsUnset(callable $spoil): void {
		$spoil($this->lists[10]);
		$this->addItem(10, 'Milk');
		$milk = $this->addItem(20, 'Milk');

		$this->assertNull($this->svc->restockChecklist($this->lists[20]));
		$this->assertNull($this->svc->match($milk, $this->lists[20]));
	}

	public function testDeletedRestockChecklistBehavesAsUnset(): void {
		$this->lists[20]->setRestockChecklistId(999);

		$this->assertNull($this->svc->restockChecklist($this->lists[20]));
	}

	public function testAutoLinkOffMatchesNothing(): void {
		$this->addItem(10, 'Milk');
		$milk = $this->addItem(20, 'Milk', ['autoLink' => false]);

		$this->assertNull($this->svc->match($milk, $this->lists[20]));
	}

	public function testAnAlreadyLinkedItemIsNotMatchedAgain(): void {
		$other = $this->addItem(11, 'Dairy');
		$this->addItem(10, 'Milk');
		$milk = $this->addItem(20, 'Milk', ['linkedItemId' => $other->getId()]);

		$this->assertNull($this->svc->match($milk, $this->lists[20]));
		$this->assertSame($other->getId(), $milk->getLinkedItemId());
	}

	public function testTrashedRowsAreNotCandidatesButArchivedOnesAre(): void {
		$this->addItem(10, 'Milk', ['deletedAt' => 5]);
		$archived = $this->addItem(10, 'Milk', ['archivedAt' => 5]);
		$milk = $this->addItem(20, 'Milk');

		$this->assertSame($archived, $this->svc->match($milk, $this->lists[20]));
	}

	// ----- Cardinality -----

	public function testTwoInventoriesShareOneRow(): void {
		$row = $this->addItem(10, 'Soda');
		$kitchen = $this->addItem(20, 'Soda');
		$garage = $this->addItem(21, 'Soda');

		$this->svc->match($kitchen, $this->lists[20]);
		$this->svc->match($garage, $this->lists[21]);

		$this->assertSame($row->getId(), $kitchen->getLinkedItemId());
		$this->assertSame($row->getId(), $garage->getLinkedItemId());
	}

	public function testRowsLinkedFromTheSameInventoryAreLeftOut(): void {
		$first = $this->addItem(10, 'Soda', ['createdAt' => 1]);
		$second = $this->addItem(10, 'Soda', ['createdAt' => 2]);
		$this->addItem(20, 'Soda', ['linkedItemId' => $first->getId()]);
		$soda = $this->addItem(20, 'Soda');

		$this->assertSame($second, $this->svc->match($soda, $this->lists[20]));
	}

	public function testOnlyOneItemPerInventoryLinksARowByName(): void {
		$row = $this->addItem(10, 'Soda');
		$a = $this->addItem(20, 'Soda');
		$b = $this->addItem(20, 'soda');

		$this->svc->matchInventory($this->lists[20]);

		$this->assertSame($row->getId(), $a->getLinkedItemId());
		$this->assertNull($b->getLinkedItemId());
	}

	public function testLinkingByHandRefusesARowAnotherItemOnTheInventoryHolds(): void {
		$row = $this->addItem(10, 'Soda');
		$this->addItem(20, 'Cola', ['linkedItemId' => $row->getId()]);
		$soda = $this->addItem(20, 'Soda');

		$this->expectException(\InvalidArgumentException::class);
		$this->svc->link($soda, $row);
	}

	public function testLinkingByHandWorksOnAnyChecklistAndAnyName(): void {
		$row = $this->addItem(11, 'Whole milk');
		$milk = $this->addItem(20, 'Milk');

		$this->svc->link($milk, $row);

		$this->assertSame($row->getId(), $milk->getLinkedItemId());
	}

	public function testRelinkingTheSameItemReplacesItsLink(): void {
		$first = $this->addItem(10, 'Milk');
		$second = $this->addItem(11, 'Milk');
		$milk = $this->addItem(20, 'Milk', ['linkedItemId' => $first->getId()]);

		$this->svc->link($milk, $second);

		$this->assertSame($second->getId(), $milk->getLinkedItemId());
	}

	public function testLinkingRefusesRowsThatAreNotOnAChecklistOfTheHouse(): void {
		$this->addList(30, Checklist::KIND_CHECKLIST, 'Elsewhere', houseId: 2);
		$milk = $this->addItem(20, 'Milk');

		foreach ([$this->addItem(30, 'Milk'), $this->addItem(21, 'Milk'), $this->addItem(10, 'Milk', ['deletedAt' => 1])] as $row) {
			try {
				$this->svc->link($milk, $row);
				$this->fail('Linked to ' . $row->getListId());
			} catch (\InvalidArgumentException) {
				$this->assertNull($milk->getLinkedItemId());
			}
		}
	}

	public function testUnlinkingTurnsAutoLinkOff(): void {
		$row = $this->addItem(10, 'Milk');
		$milk = $this->addItem(20, 'Milk', ['linkedItemId' => $row->getId()]);

		$this->svc->unlink($milk);

		$this->assertNull($milk->getLinkedItemId());
		$this->assertFalse($milk->autoLinks());
		$this->assertNull($this->svc->match($milk, $this->lists[20]));
	}

	// ----- Triggers -----

	public function testANewRowOnTheRestockChecklistLinksSameNameItems(): void {
		$kitchen = $this->addItem(20, 'Eggs');
		$garage = $this->addItem(21, 'eggs');
		$bread = $this->addItem(20, 'Bread');
		$row = $this->addItem(10, 'Eggs');

		$this->svc->onChecklistRowSaved($row, $this->lists[10]);

		$this->assertSame($row->getId(), $kitchen->getLinkedItemId());
		$this->assertSame($row->getId(), $garage->getLinkedItemId());
		$this->assertNull($bread->getLinkedItemId());
	}

	public function testARowOnAnotherChecklistLinksNothing(): void {
		$eggs = $this->addItem(20, 'Eggs');
		$row = $this->addItem(11, 'Eggs');

		$this->svc->onChecklistRowSaved($row, $this->lists[11]);

		$this->assertNull($eggs->getLinkedItemId());
	}

	public function testASecondSameNameRowStopsMatching(): void {
		$this->addItem(10, 'Eggs');
		$eggs = $this->addItem(20, 'Eggs');
		$row = $this->addItem(10, 'Eggs');

		$this->svc->onChecklistRowSaved($row, $this->lists[10]);

		$this->assertNull($eggs->getLinkedItemId());
	}

	public function testMatchingAnInventoryLinksEveryUnambiguousItem(): void {
		$milkRow = $this->addItem(10, 'Milk');
		$this->addItem(10, 'Eggs');
		$this->addItem(10, 'Eggs');
		$milk = $this->addItem(20, 'Milk');
		$eggs = $this->addItem(20, 'Eggs');
		$off = $this->addItem(20, 'Milk', ['autoLink' => false]);

		$this->svc->matchInventory($this->lists[20]);

		$this->assertSame($milkRow->getId(), $milk->getLinkedItemId());
		$this->assertNull($eggs->getLinkedItemId());
		$this->assertNull($off->getLinkedItemId());
	}

	// ----- Lifecycle -----

	public function testMovingToAnInventoryThatHoldsTheRowDropsTheMovedLink(): void {
		$row = $this->addItem(10, 'Soda');
		$this->addItem(21, 'Soda', ['linkedItemId' => $row->getId()]);
		$soda = $this->addItem(20, 'Soda', ['linkedItemId' => $row->getId()]);
		$soda->setListId(21);

		$this->svc->onItemMoved($soda, $this->lists[20], $this->lists[21]);

		$this->assertNull($soda->getLinkedItemId());
		$this->assertTrue($soda->autoLinks());
	}

	public function testMovingBetweenInventoriesOtherwiseKeepsTheLink(): void {
		$row = $this->addItem(11, 'Soda');
		$soda = $this->addItem(20, 'Soda', ['linkedItemId' => $row->getId()]);
		$soda->setListId(21);

		$this->svc->onItemMoved($soda, $this->lists[20], $this->lists[21]);

		$this->assertSame($row->getId(), $soda->getLinkedItemId());
	}

	public function testMovingAnUnlinkedItemMatchesAtItsNewInventory(): void {
		$row = $this->addItem(10, 'Soda');
		$this->lists[20]->setRestockChecklistId(null);
		$soda = $this->addItem(20, 'Soda');
		$soda->setListId(21);

		$this->svc->onItemMoved($soda, $this->lists[20], $this->lists[21]);

		$this->assertSame($row->getId(), $soda->getLinkedItemId());
	}

	public function testMovingARowBetweenChecklistsKeepsItsLinks(): void {
		$row = $this->addItem(10, 'Soda');
		$soda = $this->addItem(20, 'Soda', ['linkedItemId' => $row->getId()]);
		$this->itemMapper->expects($this->never())->method('clearLinksTo');
		$row->setListId(11);

		$this->svc->onItemMoved($row, $this->lists[10], $this->lists[11]);

		$this->assertSame($row->getId(), $soda->getLinkedItemId());
	}

	public function testAnInventoryItemCrossingKindsDropsItsLink(): void {
		$row = $this->addItem(10, 'Soda');
		$soda = $this->addItem(20, 'Soda', ['linkedItemId' => $row->getId()]);
		$soda->setListId(11);

		$this->svc->onItemMoved($soda, $this->lists[20], $this->lists[11]);

		$this->assertNull($soda->getLinkedItemId());
	}

	public function testARowCrossingKindsDropsTheLinksToIt(): void {
		$row = $this->addItem(10, 'Soda');
		$row->setListId(21);
		$this->itemMapper->expects($this->once())->method('clearLinksTo')->with([$row->getId()]);

		$this->svc->onItemMoved($row, $this->lists[10], $this->lists[21]);
	}

	// ----- Reading -----

	public function testDescribeShowsTheLinkedRowWithItsState(): void {
		$this->lists[11]->setColor('#ff9800');
		$row = $this->addItem(11, 'Eggs', ['done' => true]);
		$row->setQuantity('12');
		$eggs = $this->addItem(20, 'Eggs', ['linkedItemId' => $row->getId()]);

		$described = $this->svc->describe([$eggs])[(int)$eggs->getId()];

		$this->assertTrue($described['autoLink']);
		$this->assertSame([
			'itemId' => $row->getId(),
			'name' => 'Eggs',
			'quantity' => '12',
			'listId' => 11,
			'listName' => 'Groceries',
			'listColor' => '#ff9800',
			'state' => 'checked',
		], $described['stockLink']);
		$this->assertSame([], $described['linkCandidates']);
	}

	public function testDescribeShowsATrashedLinkedRow(): void {
		$row = $this->addItem(10, 'Eggs', ['deletedAt' => 3]);
		$eggs = $this->addItem(20, 'Eggs', ['linkedItemId' => $row->getId()]);

		$this->assertSame('trashed', $this->svc->describe([$eggs])[(int)$eggs->getId()]['stockLink']['state'] ?? null);
	}

	public function testDescribeListsSeveralCandidatesUncheckedThenCheckedThenArchivedOldestFirst(): void {
		$archived = $this->addItem(10, 'Eggs', ['archivedAt' => 1, 'createdAt' => 1]);
		$checked = $this->addItem(10, 'Eggs', ['done' => true, 'createdAt' => 2]);
		$newer = $this->addItem(10, 'Eggs', ['createdAt' => 9]);
		$older = $this->addItem(10, 'Eggs', ['createdAt' => 3]);
		$eggs = $this->addItem(20, 'Eggs');

		$candidates = $this->svc->describe([$eggs])[(int)$eggs->getId()]['linkCandidates'];

		$this->assertSame(
			[$older->getId(), $newer->getId(), $checked->getId(), $archived->getId()],
			array_column($candidates, 'itemId'),
		);
		$this->assertSame(['open', 'open', 'checked', 'archived'], array_column($candidates, 'state'));
	}

	public function testDescribeListsNoCandidatesForOneMatchOrWithAutoLinkOff(): void {
		$this->addItem(10, 'Milk');
		$this->addItem(10, 'Eggs');
		$this->addItem(10, 'Eggs');
		$milk = $this->addItem(20, 'Milk');
		$eggs = $this->addItem(20, 'Eggs', ['autoLink' => false]);

		$described = $this->svc->describe([$milk, $eggs]);

		$this->assertSame([], $described[(int)$milk->getId()]['linkCandidates']);
		$this->assertSame([], $described[(int)$eggs->getId()]['linkCandidates']);
		$this->assertFalse($described[(int)$eggs->getId()]['autoLink']);
	}

	public function testRestocksListsTheLiveInventoryItemsLinkingEachRow(): void {
		$row = $this->addItem(10, 'Soda');
		$kitchen = $this->addItem(20, 'Soda', ['linkedItemId' => $row->getId()]);
		$this->addItem(21, 'Soda', ['linkedItemId' => $row->getId(), 'deletedAt' => 4]);
		$garage = $this->addItem(21, 'Cola', ['linkedItemId' => $row->getId()]);

		$this->assertSame([(int)$row->getId() => [
			['itemId' => $kitchen->getId(), 'name' => 'Soda', 'listId' => 20, 'listName' => 'Kitchen'],
			['itemId' => $garage->getId(), 'name' => 'Cola', 'listId' => 21, 'listName' => 'Garage'],
		]], $this->svc->restocksFor([$row]));
	}

	public function testLinkTargetsSearchEditableChecklistsAndLeaveOutLinkedRows(): void {
		$linked = $this->addItem(10, 'Milk');
		$this->addItem(20, 'Milk', ['linkedItemId' => $linked->getId()]);
		$archived = $this->addItem(10, 'Milk powder', ['archivedAt' => 1]);
		$this->addItem(10, 'Milk', ['deletedAt' => 1]);
		$groceries = $this->addItem(11, 'Oat milk');
		$this->addItem(11, 'Bread');
		$this->addList(12, Checklist::KIND_CHECKLIST, 'Locked');
		$this->addItem(12, 'Milk');

		$targets = $this->svc->linkTargets($this->lists[20], static fn (Checklist $l): bool => $l->getId() !== 12, 'MILK');

		$this->assertSame([$archived->getId(), $groceries->getId()], array_column($targets, 'itemId'));
		$this->assertSame(['archived', 'open'], array_column($targets, 'state'));
	}

	// ----- Restock checklist -----

	public function testARestockChecklistMustBeAnActiveChecklistOfTheHouse(): void {
		$this->addList(30, Checklist::KIND_CHECKLIST, 'Elsewhere', houseId: 2);
		$this->lists[11]->setArchivedAt(1);
		$this->svc->assertRestockChecklist($this->lists[20], 10);

		foreach ([[30, NotFoundException::class], [999, NotFoundException::class], [11, \InvalidArgumentException::class], [21, \InvalidArgumentException::class]] as [$id, $exception]) {
			try {
				$this->svc->assertRestockChecklist($this->lists[20], $id);
				$this->fail('Accepted ' . $id);
			} catch (\Exception $e) {
				$this->assertInstanceOf($exception, $e);
			}
		}
	}
}
