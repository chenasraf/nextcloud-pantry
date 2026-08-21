<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Db\Checklist;
use OCA\Pantry\Db\ChecklistMapper;
use OCA\Pantry\Db\ItemLabelMapper;
use OCA\Pantry\Db\Label;
use OCA\Pantry\Db\LabelMapper;
use OCA\Pantry\Service\LabelService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LabelServiceTest extends TestCase {
	/** @var LabelMapper&MockObject */
	private LabelMapper $mapper;
	/** @var ItemLabelMapper&MockObject */
	private ItemLabelMapper $itemLabelMapper;
	/** @var ChecklistMapper&MockObject */
	private ChecklistMapper $listMapper;
	private LabelService $svc;

	protected function setUp(): void {
		$this->mapper = $this->createMock(LabelMapper::class);
		$this->itemLabelMapper = $this->createMock(ItemLabelMapper::class);
		$this->listMapper = $this->createMock(ChecklistMapper::class);
		$this->svc = new LabelService($this->mapper, $this->itemLabelMapper, $this->listMapper);
	}

	private function makeList(int $id, int $houseId): Checklist {
		$l = new Checklist();
		$l->setHouseId($houseId);
		$ref = new \ReflectionProperty($l, 'id');
		$ref->setValue($l, $id);
		return $l;
	}

	private function makeLabel(array $overrides = []): Label {
		$l = new Label();
		$l->setHouseId($overrides['houseId'] ?? 1);
		$l->setName($overrides['name'] ?? 'Urgent');
		$l->setIcon($overrides['icon'] ?? 'tag');
		$l->setColor($overrides['color'] ?? '#ef4444');
		$l->setSortOrder($overrides['sortOrder'] ?? 0);
		$l->setCreatedAt($overrides['createdAt'] ?? 1000);
		$l->setUpdatedAt($overrides['updatedAt'] ?? 1000);
		if (isset($overrides['id'])) {
			$ref = new \ReflectionProperty($l, 'id');
			$ref->setValue($l, $overrides['id']);
		}
		return $l;
	}

	public function testListForHousePassesSortByToMapper(): void {
		$labels = [$this->makeLabel()];
		$this->mapper->expects($this->once())
			->method('findByHouse')
			->with(1, 'custom')
			->willReturn($labels);

		$this->assertSame($labels, $this->svc->listForHouse(1, 'custom'));
	}

	public function testReorderUpdatesEachInHouse(): void {
		$l1 = $this->makeLabel(['id' => 1, 'houseId' => 1, 'sortOrder' => 0]);
		$l2 = $this->makeLabel(['id' => 2, 'houseId' => 1, 'sortOrder' => 1]);
		$foreign = $this->makeLabel(['id' => 3, 'houseId' => 99]);

		$this->mapper->method('findById')->willReturnCallback(function (int $id) use ($l1, $l2, $foreign) {
			return match ($id) {
				1 => $l1,
				2 => $l2,
				3 => $foreign,
				default => throw new DoesNotExistException(''),
			};
		});

		$this->mapper->expects($this->exactly(2))->method('update');

		$this->svc->reorder(1, [
			['id' => 2, 'sortOrder' => 0],
			['id' => 1, 'sortOrder' => 1],
			['id' => 3, 'sortOrder' => 2], // wrong house, ignored
		]);

		$this->assertSame(1, $l1->getSortOrder());
		$this->assertSame(0, $l2->getSortOrder());
	}

	public function testCreateAppendsAfterHighestSortOrder(): void {
		$this->mapper->method('findByHouseListAndName')->willReturn(null);
		$this->mapper->method('findMaxSortOrder')->with(1)->willReturn(4);
		$this->mapper->method('insert')->willReturnArgument(0);

		$created = $this->svc->create(1, 'Sale', 'sale', '#ef4444');

		$this->assertSame(5, $created->getSortOrder());
	}

	public function testCreateGlobalLabelHasNullListId(): void {
		$this->mapper->method('findByHouseListAndName')->willReturn(null);
		$this->mapper->method('findMaxSortOrder')->willReturn(-1);
		$this->mapper->method('insert')->willReturnArgument(0);

		$created = $this->svc->create(1, 'Urgent', 'fire', '#ef4444', null);

		$this->assertNull($created->getListId());
	}

	public function testCreateScopedLabelValidatesAndStoresList(): void {
		$this->listMapper->method('findById')->with(7, true)->willReturn($this->makeList(7, 1));
		$this->mapper->method('findByHouseListAndName')->with(1, 7, 'Urgent')->willReturn(null);
		$this->mapper->method('findMaxSortOrder')->willReturn(-1);
		$this->mapper->method('insert')->willReturnArgument(0);

		$created = $this->svc->create(1, 'Urgent', 'fire', '#ef4444', 7);

		$this->assertSame(7, $created->getListId());
	}

	public function testCreateRejectsListFromAnotherHouse(): void {
		$this->listMapper->method('findById')->with(7, true)->willReturn($this->makeList(7, 99));

		$this->expectException(\InvalidArgumentException::class);
		$this->svc->create(1, 'Urgent', 'fire', '#ef4444', 7);
	}

	public function testCreateRejectsDuplicateWithinSameScope(): void {
		$this->mapper->method('findByHouseListAndName')->with(1, null, 'Urgent')
			->willReturn($this->makeLabel(['id' => 5]));

		$this->expectException(\InvalidArgumentException::class);
		$this->svc->create(1, 'Urgent', 'fire', '#ef4444', null);
	}

	public function testCreateRejectsUnsupportedIcon(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->create(1, 'Urgent', 'not-a-real-icon', '#ef4444');
	}

	public function testUpdateScopingGlobalLabelDetachesItemsOnOtherLists(): void {
		$label = $this->makeLabel(['id' => 5, 'houseId' => 1]); // global
		$this->mapper->method('findById')->with(5)->willReturn($label);
		$this->listMapper->method('findById')->with(7, true)->willReturn($this->makeList(7, 1));
		$this->mapper->method('findByHouseListAndName')->willReturn(null);

		$this->itemLabelMapper->expects($this->once())
			->method('detachFromItemsNotInList')
			->with(5, 7);

		$this->svc->update(5, ['listId' => 7]);
	}

	public function testUpdateToGlobalDoesNotDetachItems(): void {
		$label = $this->makeLabel(['id' => 5, 'houseId' => 1]);
		$label->setListId(7);
		$this->mapper->method('findById')->with(5)->willReturn($label);
		$this->mapper->method('findByHouseListAndName')->willReturn(null);

		$this->itemLabelMapper->expects($this->never())->method('detachFromItemsNotInList');

		$this->svc->update(5, ['listId' => null]);
	}

	public function testDeleteDetachesFromItemsThenRemovesLabel(): void {
		$label = $this->makeLabel(['id' => 5, 'houseId' => 1]);
		$this->mapper->method('findById')->with(5)->willReturn($label);

		$this->itemLabelMapper->expects($this->once())->method('deleteByLabel')->with(5);
		$this->mapper->expects($this->once())->method('delete')->with($label);

		$this->svc->delete(5);
	}

	public function testAssertLabelsInHouseReturnsUniqueIntIds(): void {
		$this->mapper->method('findById')->willReturnCallback(function (int $id) {
			return $this->makeLabel(['id' => $id, 'houseId' => 1]);
		});

		$ids = $this->svc->assertLabelsInHouse(1, [3, 3, 5]);

		$this->assertSame([3, 5], $ids);
	}

	public function testAssertLabelsInHouseRejectsForeignLabel(): void {
		$this->mapper->method('findById')->with(9)->willReturn($this->makeLabel(['id' => 9, 'houseId' => 99]));

		$this->expectException(\OCA\Pantry\Exception\NotFoundException::class);
		$this->svc->assertLabelsInHouse(1, [9]);
	}
}
