<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Db;

use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Db\ChecklistItemMapper;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class ChecklistItemMapperTest extends TestCase {
	private ChecklistItemMapper $mapper;

	protected function setUp(): void {
		$this->mapper = new ChecklistItemMapper($this->createMock(IDBConnection::class));
	}

	private function mapRow(array $row): ChecklistItem {
		$method = new \ReflectionMethod(ChecklistItemMapper::class, 'mapRowToEntity');
		$method->setAccessible(true);
		/** @var ChecklistItem $entity */
		$entity = $method->invoke($this->mapper, $row);
		return $entity;
	}

	public function testMapsDeclaredColumns(): void {
		$item = $this->mapRow([
			'id' => 202,
			'list_id' => 15,
			'name' => 'Wine',
			'category_id' => 11,
		]);

		$this->assertSame(202, $item->getId());
		$this->assertSame(15, $item->getListId());
		$this->assertSame('Wine', $item->getName());
		$this->assertSame(11, $item->getCategoryId());
	}

	public function testIgnoresLegacyColumnsLeftBySchemaDrift(): void {
		// Regression: the price_* columns were dropped from list_items by
		// Version26 but schema drift can leave them behind. A `SELECT *` read
		// then fed them to Entity::fromRow(), which threw
		// "priceType is not a valid attribute" and broke the whole checklist.
		$item = $this->mapRow([
			'id' => 202,
			'list_id' => 15,
			'name' => 'Wine',
			'price_type' => 'set',
			'price_min' => 9.5,
			'price_max' => null,
			'price_currency' => 'EUR',
		]);

		$this->assertSame(202, $item->getId());
		$this->assertSame('Wine', $item->getName());
	}

	/**
	 * A mapper whose own queries run against a stub connection, with the link
	 * clearing and id lookup recorded.
	 *
	 * @return ChecklistItemMapper&\PHPUnit\Framework\MockObject\MockObject
	 */
	private function purgingMapper(): ChecklistItemMapper {
		$qb = $this->createMock(IQueryBuilder::class);
		foreach (['delete', 'where', 'andWhere'] as $method) {
			$qb->method($method)->willReturnSelf();
		}
		$qb->method('expr')->willReturn($this->createMock(IExpressionBuilder::class));
		$qb->method('createNamedParameter')->willReturn(':param');
		$db = $this->createMock(IDBConnection::class);
		$db->method('getQueryBuilder')->willReturn($qb);
		$mapper = $this->getMockBuilder(ChecklistItemMapper::class)
			->setConstructorArgs([$db])
			->onlyMethods(['clearLinksTo', 'findIdsByList'])
			->getMock();
		$mapper->method('findIdsByList')->willReturnCallback(
			static fn (int $listId, bool $trashedOnly): array => $trashedOnly ? [31] : [30, 31],
		);
		return $mapper;
	}

	public function testDeletingAnItemForGoodClearsLinksToIt(): void {
		$mapper = $this->purgingMapper();
		$mapper->expects($this->once())->method('clearLinksTo')->with([42]);
		$item = new ChecklistItem();
		$item->setId(42);

		$mapper->delete($item);
	}

	public function testDeletingAListsItemsClearsLinksToEveryOne(): void {
		$mapper = $this->purgingMapper();
		$mapper->expects($this->once())->method('clearLinksTo')->with([30, 31]);

		$mapper->deleteByList(7);
	}

	public function testEmptyingAListsTrashClearsLinksToTheTrashedItems(): void {
		$mapper = $this->purgingMapper();
		$mapper->expects($this->once())->method('clearLinksTo')->with([31]);

		$mapper->emptyTrashForList(7);
	}
}
