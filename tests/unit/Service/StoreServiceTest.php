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
	/** @var \OCA\Pantry\Db\ItemPriceMapper&MockObject */
	private \OCA\Pantry\Db\ItemPriceMapper $itemPriceMapper;
	private StoreService $svc;

	protected function setUp(): void {
		$this->mapper = $this->createMock(StoreMapper::class);
		$this->itemStoreMapper = $this->createMock(ItemStoreMapper::class);
		$this->itemPriceMapper = $this->createMock(\OCA\Pantry\Db\ItemPriceMapper::class);
		$this->svc = new StoreService($this->mapper, $this->itemStoreMapper, $this->itemPriceMapper);
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
			->with(1, 'name_asc')
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

	public function testCreateNormalizesInfoFields(): void {
		$this->mapper->method('findByHouseAndName')->willReturn(null);
		$this->mapper->expects($this->once())
			->method('insert')
			->willReturnCallback(fn (Store $s) => $s);

		$store = $this->svc->create(1, 'Corner Shop', 'store', '#e11d48', [
			'brand' => '  Walmart  ',
			'location' => '  12 Main Street  ',
			'contact' => "  555-1234\n",
			'responsible' => '   ',
			'notes' => '',
			'openingHours' => [
				['day' => 1, 'start' => '16:00', 'end' => '20:00'],
				['day' => 1, 'start' => '09:00', 'end' => '13:00'],
			],
		]);

		$this->assertSame('Walmart', $store->getBrand());
		$this->assertSame('12 Main Street', $store->getLocation());
		$this->assertSame('555-1234', $store->getContact());
		// Empty / whitespace-only values collapse to null.
		$this->assertNull($store->getResponsible());
		$this->assertNull($store->getNotes());

		$decoded = json_decode((string)$store->getOpeningHours(), true);
		// Sorted by day then start.
		$this->assertSame([
			['day' => 1, 'start' => '09:00', 'end' => '13:00'],
			['day' => 1, 'start' => '16:00', 'end' => '20:00'],
		], $decoded);

		// The API output decodes opening hours back into a structured list.
		$json = $store->jsonSerialize();
		$this->assertSame($decoded, $json['openingHours']);
		$this->assertSame('Walmart', $json['brand']);
		$this->assertSame('12 Main Street', $json['location']);
		$this->assertNull($json['notes']);
	}

	public function testCreateEmptyOpeningHoursStoresNull(): void {
		$this->mapper->method('findByHouseAndName')->willReturn(null);
		$this->mapper->method('insert')->willReturnCallback(fn (Store $s) => $s);

		$store = $this->svc->create(1, 'Shop', 'store', '#e11d48', ['openingHours' => []]);
		$this->assertNull($store->getOpeningHours());
	}

	public function testCreateRejectsBadOpeningHoursDay(): void {
		$this->mapper->method('findByHouseAndName')->willReturn(null);
		$this->expectException(\InvalidArgumentException::class);
		// 0 is out of the ISO-8601 range (1 = Monday .. 7 = Sunday).
		$this->svc->create(1, 'Shop', 'store', '#e11d48', [
			'openingHours' => [['day' => 0, 'start' => '09:00', 'end' => '17:00']],
		]);
	}

	public function testCreateRejectsBadOpeningHoursTime(): void {
		$this->mapper->method('findByHouseAndName')->willReturn(null);
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->create(1, 'Shop', 'store', '#e11d48', [
			'openingHours' => [['day' => 1, 'start' => '9am', 'end' => '17:00']],
		]);
	}

	public function testCreateRejectsOpeningHoursEndBeforeStart(): void {
		$this->mapper->method('findByHouseAndName')->willReturn(null);
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->create(1, 'Shop', 'store', '#e11d48', [
			'openingHours' => [['day' => 1, 'start' => '17:00', 'end' => '09:00']],
		]);
	}

	public function testUpdateAppliesInfoFields(): void {
		$store = $this->makeStore(['id' => 4]);
		$this->mapper->method('findById')->with(4)->willReturn($store);
		$this->mapper->expects($this->once())->method('update')->willReturnArgument(0);

		$updated = $this->svc->update(4, [
			'location' => 'Downtown',
			'openingHours' => [['day' => 7, 'start' => '10:00', 'end' => '14:00']],
		]);

		$this->assertSame('Downtown', $updated->getLocation());
		$this->assertSame(
			[['day' => 7, 'start' => '10:00', 'end' => '14:00']],
			json_decode((string)$updated->getOpeningHours(), true),
		);
	}

	public function testUpdateClearsFieldWithEmptyValue(): void {
		$store = $this->makeStore(['id' => 4]);
		$store->setLocation('Old address');
		$this->mapper->method('findById')->with(4)->willReturn($store);
		$this->mapper->method('update')->willReturnArgument(0);

		$updated = $this->svc->update(4, ['location' => '']);
		$this->assertNull($updated->getLocation());
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

	public function testListForHousePassesSortByToMapper(): void {
		$stores = [$this->makeStore()];
		$this->mapper->expects($this->once())
			->method('findByHouse')
			->with(1, 'custom')
			->willReturn($stores);

		$this->assertSame($stores, $this->svc->listForHouse(1, 'custom'));
	}

	public function testCreateAppendsAfterHighestSortOrder(): void {
		$this->mapper->method('findByHouseAndName')->willReturn(null);
		$this->mapper->method('findMaxSortOrder')->with(1)->willReturn(4);
		$this->mapper->method('insert')->willReturnArgument(0);

		$created = $this->svc->create(1, 'Aisle 8', 'store', '#e11d48');

		$this->assertSame(5, $created->getSortOrder());
	}

	public function testCreateInEmptyHouseStartsAtZero(): void {
		$this->mapper->method('findByHouseAndName')->willReturn(null);
		$this->mapper->method('findMaxSortOrder')->with(1)->willReturn(-1);
		$this->mapper->method('insert')->willReturnArgument(0);

		$created = $this->svc->create(1, 'Produce', 'store', '#22c55e');

		$this->assertSame(0, $created->getSortOrder());
	}

	public function testReorderUpdatesEachInHouse(): void {
		$s1 = $this->makeStore(['id' => 1, 'houseId' => 1]);
		$s2 = $this->makeStore(['id' => 2, 'houseId' => 1]);
		$foreign = $this->makeStore(['id' => 3, 'houseId' => 99]);

		$this->mapper->method('findById')->willReturnCallback(function (int $id) use ($s1, $s2, $foreign) {
			return match ($id) {
				1 => $s1,
				2 => $s2,
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

		$this->assertSame(1, $s1->getSortOrder());
		$this->assertSame(0, $s2->getSortOrder());
	}

	public function testReorderSkipsMissingId(): void {
		$this->mapper->method('findById')->willThrowException(new DoesNotExistException(''));
		$this->mapper->expects($this->never())->method('update');

		$this->svc->reorder(1, [['id' => 999, 'sortOrder' => 0]]);
	}
}
