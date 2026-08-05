<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Db\ShoppingReminder;
use OCA\Pantry\Db\ShoppingReminderMapper;
use OCA\Pantry\Exception\NotFoundException;
use OCA\Pantry\Service\ShoppingReminderService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ShoppingReminderServiceTest extends TestCase {
	/** @var ShoppingReminderMapper&MockObject */
	private ShoppingReminderMapper $mapper;
	private ShoppingReminderService $svc;

	protected function setUp(): void {
		$this->mapper = $this->createMock(ShoppingReminderMapper::class);
		$this->svc = new ShoppingReminderService($this->mapper);
	}

	private function makeReminder(array $o = []): ShoppingReminder {
		$r = new ShoppingReminder();
		$r->setHouseId($o['houseId'] ?? 1);
		$r->setText($o['text'] ?? 'Bring bags');
		$r->setShowOn($o['showOn'] ?? ShoppingReminderService::SHOW_ON_START);
		$r->setPosition($o['position'] ?? 0);
		$r->setEnabled($o['enabled'] ?? true);
		if (isset($o['id'])) {
			$ref = new \ReflectionProperty($r, 'id');
			$ref->setValue($r, $o['id']);
		}
		return $r;
	}

	public function testCreateAppendsAtEndWithNormalizedText(): void {
		$this->mapper->method('maxPositionForHouse')->with(1)->willReturn(4);
		$this->mapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (ShoppingReminder $r) {
				$this->assertSame('Bring bags', $r->getText());
				$this->assertSame(5, $r->getPosition());
				$this->assertSame(ShoppingReminderService::SHOW_ON_CLOSE, $r->getShowOn());
				$this->assertTrue($r->getEnabled());
				return $r;
			});

		$this->svc->create(1, '  Bring bags  ', ShoppingReminderService::SHOW_ON_CLOSE);
	}

	public function testCreateFirstReminderGetsPositionZero(): void {
		$this->mapper->method('maxPositionForHouse')->willReturn(-1);
		$this->mapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (ShoppingReminder $r) {
				$this->assertSame(0, $r->getPosition());
				return $r;
			});

		$this->svc->create(1, 'Cool bag', ShoppingReminderService::SHOW_ON_START);
	}

	public function testCreateRejectsEmptyText(): void {
		$this->mapper->expects($this->never())->method('insert');
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->create(1, '   ', ShoppingReminderService::SHOW_ON_START);
	}

	public function testCreateRejectsUnknownMoment(): void {
		$this->mapper->expects($this->never())->method('insert');
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->create(1, 'Text', 'on_whenever');
	}

	public function testUpdateTouchesOnlyProvidedFields(): void {
		$reminder = $this->makeReminder(['id' => 7, 'text' => 'Old', 'enabled' => true, 'position' => 3]);
		$this->mapper->method('findById')->with(7)->willReturn($reminder);
		$this->mapper->expects($this->once())->method('update')->willReturnArgument(0);

		$updated = $this->svc->update(7, ['text' => 'New', 'enabled' => false]);
		$this->assertSame('New', $updated->getText());
		$this->assertFalse($updated->getEnabled());
		// Untouched.
		$this->assertSame(3, $updated->getPosition());
		$this->assertSame(ShoppingReminderService::SHOW_ON_START, $updated->getShowOn());
	}

	public function testUpdateRejectsUnknownMoment(): void {
		$reminder = $this->makeReminder(['id' => 7]);
		$this->mapper->method('findById')->with(7)->willReturn($reminder);
		$this->mapper->expects($this->never())->method('update');
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->update(7, ['showOn' => 'nope']);
	}

	public function testUpdateWrapsMissingInNotFound(): void {
		$this->mapper->method('findById')->willThrowException(new DoesNotExistException(''));
		$this->expectException(NotFoundException::class);
		$this->svc->update(99, ['text' => 'x']);
	}

	public function testReorderAssignsSequentialPositions(): void {
		$a = $this->makeReminder(['id' => 10, 'position' => 0]);
		$b = $this->makeReminder(['id' => 11, 'position' => 1]);
		$c = $this->makeReminder(['id' => 12, 'position' => 2]);
		// findByHouse called twice: once to index, once to return the new order.
		$this->mapper->method('findByHouse')->with(1)->willReturn([$a, $b, $c]);
		// Only the rows whose position actually changes are written. Reorder to
		// [12, 10, 11]: 12 moves 2→0, 10 moves 0→1, 11 moves 1→2 — all three shift.
		$updated = [];
		$this->mapper->method('update')->willReturnCallback(function (ShoppingReminder $r) use (&$updated) {
			$updated[(int)$r->getId()] = (int)$r->getPosition();
			return $r;
		});

		$this->svc->reorder(1, [12, 10, 11]);

		$this->assertSame([12 => 0, 10 => 1, 11 => 2], $updated);
	}

	public function testReorderIgnoresIdsFromOtherHouses(): void {
		$a = $this->makeReminder(['id' => 10, 'position' => 0]);
		$this->mapper->method('findByHouse')->with(1)->willReturn([$a]);
		// Foreign id 999 is skipped; 10 already at 0, so no writes at all.
		$this->mapper->expects($this->never())->method('update');

		$this->svc->reorder(1, [999, 10]);
	}

	public function testAssertInHouseRejectsMismatch(): void {
		$this->mapper->method('findById')->with(7)->willReturn($this->makeReminder(['id' => 7, 'houseId' => 2]));
		$this->expectException(NotFoundException::class);
		$this->svc->assertInHouse(7, 1);
	}

	public function testDeleteLoadsAndDeletes(): void {
		$reminder = $this->makeReminder(['id' => 7]);
		$this->mapper->method('findById')->with(7)->willReturn($reminder);
		$this->mapper->expects($this->once())->method('delete')->with($reminder);
		$this->svc->delete(7);
	}
}
