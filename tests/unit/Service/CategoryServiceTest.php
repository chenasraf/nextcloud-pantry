<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Db\Category;
use OCA\Pantry\Db\CategoryMapper;
use OCA\Pantry\Db\Checklist;
use OCA\Pantry\Db\ChecklistMapper;
use OCA\Pantry\Service\CategoryService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CategoryServiceTest extends TestCase {
	/** @var CategoryMapper&MockObject */
	private CategoryMapper $mapper;
	/** @var ChecklistMapper&MockObject */
	private ChecklistMapper $listMapper;
	private CategoryService $svc;

	protected function setUp(): void {
		$this->mapper = $this->createMock(CategoryMapper::class);
		$this->listMapper = $this->createMock(ChecklistMapper::class);
		$this->svc = new CategoryService($this->mapper, $this->listMapper);
	}

	private function makeList(int $id, int $houseId): Checklist {
		$l = new Checklist();
		$l->setHouseId($houseId);
		$ref = new \ReflectionProperty($l, 'id');
		$ref->setValue($l, $id);
		return $l;
	}

	private function makeCategory(array $overrides = []): Category {
		$c = new Category();
		$c->setHouseId($overrides['houseId'] ?? 1);
		$c->setName($overrides['name'] ?? 'Produce');
		$c->setIcon($overrides['icon'] ?? 'tag');
		$c->setColor($overrides['color'] ?? '#22c55e');
		$c->setSortOrder($overrides['sortOrder'] ?? 0);
		$c->setCreatedAt($overrides['createdAt'] ?? 1000);
		$c->setUpdatedAt($overrides['updatedAt'] ?? 1000);
		if (isset($overrides['id'])) {
			$ref = new \ReflectionProperty($c, 'id');
			$ref->setValue($c, $overrides['id']);
		}
		return $c;
	}

	public function testListForHousePassesSortByToMapper(): void {
		$cats = [$this->makeCategory()];
		$this->mapper->expects($this->once())
			->method('findByHouse')
			->with(1, 'custom')
			->willReturn($cats);

		$this->assertSame($cats, $this->svc->listForHouse(1, 'custom'));
	}

	public function testListForHouseDefaultsToNameAsc(): void {
		$this->mapper->expects($this->once())
			->method('findByHouse')
			->with(1, 'name_asc')
			->willReturn([]);

		$this->svc->listForHouse(1);
	}

	public function testReorderUpdatesEachInHouse(): void {
		$c1 = $this->makeCategory(['id' => 1, 'houseId' => 1, 'sortOrder' => 0]);
		$c2 = $this->makeCategory(['id' => 2, 'houseId' => 1, 'sortOrder' => 1]);
		$foreign = $this->makeCategory(['id' => 3, 'houseId' => 99]);

		$this->mapper->method('findById')->willReturnCallback(function (int $id) use ($c1, $c2, $foreign) {
			return match ($id) {
				1 => $c1,
				2 => $c2,
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

		$this->assertSame(1, $c1->getSortOrder());
		$this->assertSame(0, $c2->getSortOrder());
	}

	public function testReorderSkipsMissingId(): void {
		$this->mapper->method('findById')->willThrowException(new DoesNotExistException(''));
		$this->mapper->expects($this->never())->method('update');

		$this->svc->reorder(1, [['id' => 999, 'sortOrder' => 0]]);
	}

	public function testReorderSkipsInvalidIds(): void {
		$this->mapper->expects($this->never())->method('findById');
		$this->mapper->expects($this->never())->method('update');

		$this->svc->reorder(1, [['id' => 0, 'sortOrder' => 0]]);
	}

	public function testCreateAppendsAfterHighestSortOrder(): void {
		$this->mapper->method('findByHouseListAndName')->willReturn(null);
		$this->mapper->method('findMaxSortOrder')->with(1)->willReturn(4);
		$this->mapper->method('insert')->willReturnArgument(0);

		$created = $this->svc->create(1, 'Aisle 8', 'tag', '#ef4444');

		$this->assertSame(5, $created->getSortOrder());
	}

	public function testCreateInEmptyHouseStartsAtZero(): void {
		$this->mapper->method('findByHouseListAndName')->willReturn(null);
		$this->mapper->method('findMaxSortOrder')->with(1)->willReturn(-1);
		$this->mapper->method('insert')->willReturnArgument(0);

		$created = $this->svc->create(1, 'Produce', 'tag', '#22c55e');

		$this->assertSame(0, $created->getSortOrder());
	}

	public function testCreateGlobalCategoryHasNullListId(): void {
		$this->mapper->method('findByHouseListAndName')->willReturn(null);
		$this->mapper->method('findMaxSortOrder')->willReturn(-1);
		$this->mapper->method('insert')->willReturnArgument(0);

		$created = $this->svc->create(1, 'Produce', 'tag', '#22c55e', null);

		$this->assertNull($created->getListId());
	}

	public function testCreateScopedCategoryValidatesAndStoresList(): void {
		$this->listMapper->method('findById')->with(7, true)->willReturn($this->makeList(7, 1));
		$this->mapper->method('findByHouseListAndName')->with(1, 7, 'Produce')->willReturn(null);
		$this->mapper->method('findMaxSortOrder')->willReturn(-1);
		$this->mapper->method('insert')->willReturnArgument(0);

		$created = $this->svc->create(1, 'Produce', 'tag', '#22c55e', 7);

		$this->assertSame(7, $created->getListId());
	}

	public function testCreateRejectsListFromAnotherHouse(): void {
		$this->listMapper->method('findById')->with(7, true)->willReturn($this->makeList(7, 99));

		$this->expectException(\InvalidArgumentException::class);
		$this->svc->create(1, 'Produce', 'tag', '#22c55e', 7);
	}

	public function testCreateRejectsDuplicateWithinSameScope(): void {
		$this->mapper->method('findByHouseListAndName')->with(1, null, 'Produce')
			->willReturn($this->makeCategory(['id' => 5]));

		$this->expectException(\InvalidArgumentException::class);
		$this->svc->create(1, 'Produce', 'tag', '#22c55e', null);
	}

	public function testCreateAllowsSameNameInDifferentScopes(): void {
		// A global "Produce" exists; creating a list-scoped "Produce" checks the
		// list scope (which is empty) and must succeed.
		$this->listMapper->method('findById')->willReturn($this->makeList(7, 1));
		$this->mapper->method('findByHouseListAndName')->with(1, 7, 'Produce')->willReturn(null);
		$this->mapper->method('findMaxSortOrder')->willReturn(0);
		$this->mapper->method('insert')->willReturnArgument(0);

		$created = $this->svc->create(1, 'Produce', 'tag', '#22c55e', 7);

		$this->assertSame(7, $created->getListId());
	}

	public function testUpdateMovesCategoryToGlobalScope(): void {
		$cat = $this->makeCategory(['id' => 5, 'houseId' => 1]);
		$cat->setListId(7);
		$this->mapper->method('findById')->with(5)->willReturn($cat);
		$this->mapper->method('findByHouseListAndName')->with(1, null, 'Produce')->willReturn(null);

		$updated = $this->svc->update(5, ['listId' => null]);

		$this->assertNull($updated->getListId());
	}

	public function testUpdateWithoutListIdKeyKeepsScope(): void {
		$cat = $this->makeCategory(['id' => 5, 'houseId' => 1]);
		$cat->setListId(7);
		$this->mapper->method('findById')->with(5)->willReturn($cat);

		$updated = $this->svc->update(5, ['icon' => 'food']);

		$this->assertSame(7, $updated->getListId());
	}

	public function testUpdateScopingGlobalCategoryDetachesItemsOnOtherLists(): void {
		$cat = $this->makeCategory(['id' => 5, 'houseId' => 1]); // global
		$this->mapper->method('findById')->with(5)->willReturn($cat);
		$this->listMapper->method('findById')->with(7, true)->willReturn($this->makeList(7, 1));
		$this->mapper->method('findByHouseListAndName')->willReturn(null);

		$this->mapper->expects($this->once())
			->method('detachFromItemsNotInList')
			->with(5, 7);

		$this->svc->update(5, ['listId' => 7]);
	}

	public function testUpdateToGlobalDoesNotDetachItems(): void {
		$cat = $this->makeCategory(['id' => 5, 'houseId' => 1]);
		$cat->setListId(7);
		$this->mapper->method('findById')->with(5)->willReturn($cat);
		$this->mapper->method('findByHouseListAndName')->willReturn(null);

		$this->mapper->expects($this->never())->method('detachFromItemsNotInList');

		$this->svc->update(5, ['listId' => null]);
	}

	public function testUpdateWithUnchangedScopeDoesNotDetachItems(): void {
		$cat = $this->makeCategory(['id' => 5, 'houseId' => 1]);
		$cat->setListId(7);
		$this->mapper->method('findById')->with(5)->willReturn($cat);

		$this->mapper->expects($this->never())->method('detachFromItemsNotInList');

		$this->svc->update(5, ['icon' => 'food']);
	}
}
