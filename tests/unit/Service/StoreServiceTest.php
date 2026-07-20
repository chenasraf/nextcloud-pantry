<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Db\ItemStoreMapper;
use OCA\Pantry\Db\Store;
use OCA\Pantry\Db\StoreMapper;
use OCA\Pantry\Exception\NotFoundException;
use OCA\Pantry\Service\StoreService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StoreServiceTest extends TestCase {
	/** @var StoreMapper&MockObject */
	private StoreMapper $mapper;
	/** @var ItemStoreMapper&MockObject */
	private ItemStoreMapper $itemStoreMapper;
	private StoreService $svc;

	protected function setUp(): void {
		$this->mapper = $this->createMock(StoreMapper::class);
		$this->itemStoreMapper = $this->createMock(ItemStoreMapper::class);
		$this->svc = new StoreService($this->mapper, $this->itemStoreMapper);
	}

	private function makeStore(array $overrides = []): Store {
		$s = new Store();
		$s->setHouseId($overrides['houseId'] ?? 1);
		$s->setName($overrides['name'] ?? 'Supermarket');
		$s->setIcon($overrides['icon'] ?? 'store');
		$s->setColor($overrides['color'] ?? '#e11d48');
		$s->setCreatedAt($overrides['createdAt'] ?? 1000);
		$s->setUpdatedAt($overrides['updatedAt'] ?? 1000);
		if (isset($overrides['id'])) {
			$ref = new \ReflectionProperty($s, 'id');
			$ref->setValue($s, $overrides['id']);
		}
		return $s;
	}

	public function testListForHouseDelegatesToMapper(): void {
		$stores = [$this->makeStore()];
		$this->mapper->expects($this->once())
			->method('findByHouse')
			->with(1)
			->willReturn($stores);

		$this->assertSame($stores, $this->svc->listForHouse(1));
	}

	public function testCreateRejectsEmptyName(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->create(1, '   ', 'tag', '#22c55e');
	}

	public function testCreateRejectsDuplicateName(): void {
		$this->mapper->method('findByHouseAndName')->willReturn($this->makeStore(['id' => 5]));
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->create(1, 'Supermarket', 'tag', '#22c55e');
	}

	public function testCreateRejectsUnknownIcon(): void {
		$this->mapper->method('findByHouseAndName')->willReturn(null);
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->create(1, 'Supermarket', 'not-a-real-icon', '#22c55e');
	}

	public function testCreateRejectsBadColor(): void {
		$this->mapper->method('findByHouseAndName')->willReturn(null);
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->create(1, 'Supermarket', 'tag', 'red');
	}

	public function testCreateInsertsNormalizedStore(): void {
		$this->mapper->method('findByHouseAndName')->willReturn(null);
		$this->mapper->expects($this->once())
			->method('insert')
			->willReturnCallback(fn (Store $s) => $s);

		$store = $this->svc->create(1, '  Pharmacy  ', 'PHARMACY', '#E11D48');
		$this->assertSame('Pharmacy', $store->getName());
		$this->assertSame('pharmacy', $store->getIcon());
		$this->assertSame('#e11d48', $store->getColor());
	}

	public function testDeleteDetachesFromItemsBeforeDeletingRow(): void {
		$store = $this->makeStore(['id' => 7]);
		$this->mapper->method('findById')->with(7)->willReturn($store);

		$this->itemStoreMapper->expects($this->once())->method('deleteByStore')->with(7);
		$this->mapper->expects($this->once())->method('delete')->with($store);

		$this->svc->delete(7);
	}

	public function testAssertInHouseThrowsOnMismatch(): void {
		$store = $this->makeStore(['id' => 3, 'houseId' => 99]);
		$this->mapper->method('findById')->with(3)->willReturn($store);

		$this->expectException(NotFoundException::class);
		$this->svc->assertInHouse(3, 1);
	}

	public function testAssertStoresInHouseAcceptsEmptyList(): void {
		$this->mapper->expects($this->never())->method('findByHouse');
		$this->svc->assertStoresInHouse(1, []);
		$this->addToAssertionCount(1);
	}

	public function testAssertStoresInHouseThrowsOnForeignId(): void {
		$this->mapper->method('findByHouse')->with(1)->willReturn([
			$this->makeStore(['id' => 1]),
			$this->makeStore(['id' => 2]),
		]);

		$this->expectException(NotFoundException::class);
		$this->svc->assertStoresInHouse(1, [1, 42]);
	}

	public function testAssertStoresInHousePassesWhenAllBelong(): void {
		$this->mapper->method('findByHouse')->with(1)->willReturn([
			$this->makeStore(['id' => 1]),
			$this->makeStore(['id' => 2]),
		]);

		$this->svc->assertStoresInHouse(1, [1, 2]);
		$this->addToAssertionCount(1);
	}

	public function testGetWrapsMissingInNotFound(): void {
		$this->mapper->method('findById')->willThrowException(new DoesNotExistException(''));
		$this->expectException(NotFoundException::class);
		$this->svc->get(123);
	}
}
