<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Db;

use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Db\ChecklistItemMapper;
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
}
