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

		// archived_at (Version13) plus the price columns (Version19) that drift
		// left missing in issue #212.
		$this->assertContains('pantry_list_items.archived_at', $declared);
		$this->assertContains('pantry_list_items.price_type', $declared);
		$this->assertContains('pantry_list_items.price_currency', $declared);
		$this->assertContains('pantry_shopsess_items.item_name', $declared);
	}

	public function testColumnsListenerIgnoresUnrelatedEvents(): void {
		$event = $this->createMock(Event::class);
		// Should not throw or interact with anything for a non-matching event.
		(new AddMissingColumnsListener())->handle($event);
		$this->addToAssertionCount(1);
	}

	public function testIndicesListenerDeclaresArchivedIndex(): void {
		/** @var AddMissingIndicesEvent&MockObject $event */
		$event = $this->createMock(AddMissingIndicesEvent::class);
		$event->expects($this->once())
			->method('addMissingIndex')
			->with('pantry_list_items', 'pantry_items_archived_idx', ['archived_at']);

		(new AddMissingIndicesListener())->handle($event);
	}

	public function testIndicesListenerIgnoresUnrelatedEvents(): void {
		$event = $this->createMock(Event::class);
		(new AddMissingIndicesListener())->handle($event);
		$this->addToAssertionCount(1);
	}
}
