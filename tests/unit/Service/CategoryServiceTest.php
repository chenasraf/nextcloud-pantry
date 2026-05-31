<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Db\Category;
use OCA\Pantry\Db\CategoryMapper;
use OCA\Pantry\Service\CategoryService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CategoryServiceTest extends TestCase {
	/** @var CategoryMapper&MockObject */
	private CategoryMapper $mapper;
	private CategoryService $svc;

	protected function setUp(): void {
		$this->mapper = $this->createMock(CategoryMapper::class);
		$this->svc = new CategoryService($this->mapper);
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
}
