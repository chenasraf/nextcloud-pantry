<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Listener;

use OCA\Pantry\Listener\AddMissingColumnsListener;
use OCA\Pantry\Listener\AddMissingIndicesListener;
use OCP\DB\Events\AddMissingColumnsEvent;
use OCP\DB\Events\AddMissingIndicesEvent;
use OCP\EventDispatcher\Event;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AddMissingSchemaListenerTest extends TestCase {
	public function testColumnsListenerDeclaresDriftProneColumns(): void {
		$declared = [];
		/** @var AddMissingColumnsEvent&MockObject $event */
		$event = $this->createMock(AddMissingColumnsEvent::class);
		$event->method('addMissingColumn')
			->willReturnCallback(function (string $table, string $column) use (&$declared): void {
				$declared[] = $table . '.' . $column;
			});

		(new AddMissingColumnsListener())->handle($event);

		// archived_at (Version13) plus the shopping-session snapshot columns that
		// drift can leave missing.
		$this->assertContains('pantry_list_items.archived_at', $declared);
		$this->assertContains('pantry_shopsess_items.item_name', $declared);
		$this->assertContains('pantry_shopsess_items.price_type', $declared);
		// The list_items price columns moved to pantry_item_prices, so they are no
		// longer declared as drift-prone here.
		$this->assertNotContains('pantry_list_items.price_type', $declared);
		$this->assertNotContains('pantry_list_items.price_currency', $declared);
	}

	public function testColumnsListenerIgnoresUnrelatedEvents(): void {
		$event = $this->createMock(Event::class);
		// Should not throw or interact with anything for a non-matching event.
		(new AddMissingColumnsListener())->handle($event);
		$this->addToAssertionCount(1);
	}

	public function testIndicesListenerDeclaresExpectedIndices(): void {
		$declared = [];

		/** @var AddMissingIndicesEvent&MockObject $event */
		$event = $this->createMock(AddMissingIndicesEvent::class);
		$event->method('addMissingIndex')
			->willReturnCallback(function (string $table, string $name, array $columns) use (&$declared): void {
				$declared[] = [$table, $name, $columns];
			});

		(new AddMissingIndicesListener())->handle($event);

		$this->assertSame([
			['pantry_list_items', 'pantry_items_archived_idx', ['archived_at']],
			['pantry_notes', 'pantry_nsync_file_idx', ['sync_file_id']],
		], $declared);
	}

	public function testIndicesListenerIgnoresUnrelatedEvents(): void {
		$event = $this->createMock(Event::class);
		(new AddMissingIndicesListener())->handle($event);
		$this->addToAssertionCount(1);
	}
}
