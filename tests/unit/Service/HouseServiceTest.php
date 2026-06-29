<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Db\CategoryMapper;
use OCA\Pantry\Db\ChecklistItemMapper;
use OCA\Pantry\Db\ChecklistMapper;
use OCA\Pantry\Db\House;
use OCA\Pantry\Db\HouseMapper;
use OCA\Pantry\Db\HouseMemberMapper;
use OCA\Pantry\Db\HouseMemberRoleMapper;
use OCA\Pantry\Db\ListRoleMapper;
use OCA\Pantry\Db\NoteMapper;
use OCA\Pantry\Db\PhotoFolderMapper;
use OCA\Pantry\Db\PhotoMapper;
use OCA\Pantry\Db\RoleMapper;
use OCA\Pantry\Service\HouseService;
use OCA\Pantry\Service\RoleService;
use OCP\IDBConnection;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class HouseServiceTest extends TestCase {
	/** @var HouseMapper&MockObject */
	private HouseMapper $houseMapper;
	private HouseService $svc;

	protected function setUp(): void {
		$this->houseMapper = $this->createMock(HouseMapper::class);
		$this->svc = new HouseService(
			$this->houseMapper,
			$this->createMock(HouseMemberMapper::class),
			$this->createMock(ChecklistMapper::class),
			$this->createMock(ChecklistItemMapper::class),
			$this->createMock(CategoryMapper::class),
			$this->createMock(PhotoMapper::class),
			$this->createMock(PhotoFolderMapper::class),
			$this->createMock(NoteMapper::class),
			$this->createMock(RoleMapper::class),
			$this->createMock(HouseMemberRoleMapper::class),
			$this->createMock(ListRoleMapper::class),
			$this->createMock(RoleService::class),
			$this->createMock(IDBConnection::class),
			$this->createMock(IUserManager::class),
		);
	}

	private function makeHouse(): House {
		$house = new House();
		$house->setName('Home');
		$house->setOwnerUid('alice');
		$house->setTrashRetentionDays(30);
		return $house;
	}

	public function testUpdateAcceptsTrashRetentionDays(): void {
		$house = $this->makeHouse();
		$this->houseMapper->method('findById')->willReturn($house);
		$this->houseMapper->expects($this->once())->method('update')->with($house);

		$updated = $this->svc->update(1, ['trashRetentionDays' => 7]);
		$this->assertSame(7, $updated->getTrashRetentionDays());
	}

	public function testUpdateAllowsZeroToDisableAutoPurge(): void {
		$house = $this->makeHouse();
		$this->houseMapper->method('findById')->willReturn($house);
		$this->houseMapper->expects($this->once())->method('update')->with($house);

		$updated = $this->svc->update(1, ['trashRetentionDays' => 0]);
		$this->assertSame(0, $updated->getTrashRetentionDays());
	}

	public function testUpdateCapsTrashRetentionAtMax(): void {
		$house = $this->makeHouse();
		$this->houseMapper->method('findById')->willReturn($house);
		$this->houseMapper->expects($this->once())->method('update')->with($house);

		$updated = $this->svc->update(1, ['trashRetentionDays' => 99_999]);
		$this->assertSame(House::MAX_TRASH_RETENTION_DAYS, $updated->getTrashRetentionDays());
	}

	public function testUpdateRejectsNegativeTrashRetention(): void {
		$house = $this->makeHouse();
		$this->houseMapper->method('findById')->willReturn($house);
		$this->expectException(\InvalidArgumentException::class);

		$this->svc->update(1, ['trashRetentionDays' => -1]);
	}

	public function testUpdateRejectsNonNumericTrashRetention(): void {
		$house = $this->makeHouse();
		$this->houseMapper->method('findById')->willReturn($house);
		$this->expectException(\InvalidArgumentException::class);

		$this->svc->update(1, ['trashRetentionDays' => 'never']);
	}
}
