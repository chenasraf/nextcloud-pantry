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
	public function testColumnsListenerDeclaresArchivedAt(): void {
		/** @var AddMissingColumnsEvent&MockObject $event */
		$event = $this->createMock(AddMissingColumnsEvent::class);
		$event->expects($this->once())
			->method('addMissingColumn')
			->with('pantry_list_items', 'archived_at', 'bigint', $this->anything());

		(new AddMissingColumnsListener())->handle($event);
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
