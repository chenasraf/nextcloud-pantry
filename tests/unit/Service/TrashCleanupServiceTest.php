<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Db\Checklist;
use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Db\ChecklistItemMapper;
use OCA\Pantry\Db\ChecklistMapper;
use OCA\Pantry\Db\House;
use OCA\Pantry\Db\HouseMapper;
use OCA\Pantry\Db\Note;
use OCA\Pantry\Db\NoteMapper;
use OCA\Pantry\Db\Photo;
use OCA\Pantry\Db\PhotoMapper;
use OCA\Pantry\Service\ImageService;
use OCA\Pantry\Service\TrashCleanupService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class TrashCleanupServiceTest extends TestCase {
	/** @var HouseMapper&MockObject */
	private HouseMapper $houseMapper;
	/** @var ChecklistMapper&MockObject */
	private ChecklistMapper $listMapper;
	/** @var ChecklistItemMapper&MockObject */
	private ChecklistItemMapper $itemMapper;
	/** @var NoteMapper&MockObject */
	private NoteMapper $noteMapper;
	/** @var PhotoMapper&MockObject */
	private PhotoMapper $photoMapper;
	/** @var ImageService&MockObject */
	private ImageService $images;
	private TrashCleanupService $svc;

	protected function setUp(): void {
		$this->houseMapper = $this->createMock(HouseMapper::class);
		$this->listMapper = $this->createMock(ChecklistMapper::class);
		$this->itemMapper = $this->createMock(ChecklistItemMapper::class);
		$this->noteMapper = $this->createMock(NoteMapper::class);
		$this->photoMapper = $this->createMock(PhotoMapper::class);
		$this->images = $this->createMock(ImageService::class);
		$this->svc = new TrashCleanupService(
			$this->houseMapper,
			$this->listMapper,
			$this->itemMapper,
			$this->noteMapper,
			$this->photoMapper,
			$this->images,
			new NullLogger(),
		);
	}

	private function makeHouse(int $id, int $retentionDays, string $ownerUid = 'alice'): House {
		$house = new House();
		$ref = new \ReflectionProperty($house, 'id');
		$ref->setValue($house, $id);
		$house->setOwnerUid($ownerUid);
		$house->setTrashRetentionDays($retentionDays);
		return $house;
	}

	public function testPurgeHouseSkipsWhenRetentionIsZero(): void {
		$house = $this->makeHouse(1, 0);
		$this->itemMapper->expects($this->never())->method('findExpiredTrashByHouse');
		$this->listMapper->expects($this->never())->method('findExpiredTrashByHouse');
		$this->noteMapper->expects($this->never())->method('findExpiredTrashByHouse');
		$this->photoMapper->expects($this->never())->method('findExpiredTrashByHouse');

		$counts = $this->svc->purgeHouse($house);
		$this->assertSame(['lists' => 0, 'items' => 0, 'notes' => 0, 'photos' => 0], $counts);
	}

	public function testPurgeHouseUsesCutoffOfNowMinusRetention(): void {
		$house = $this->makeHouse(7, 30);
		$now = 1_700_000_000;
		$expectedCutoff = $now - 30 * 86400;

		$this->itemMapper->expects($this->once())
			->method('findExpiredTrashByHouse')
			->with(7, $expectedCutoff)
			->willReturn([]);
		$this->listMapper->expects($this->once())
			->method('findExpiredTrashByHouse')
			->with(7, $expectedCutoff)
			->willReturn([]);
		$this->noteMapper->expects($this->once())
			->method('findExpiredTrashByHouse')
			->with(7, $expectedCutoff)
			->willReturn([]);
		$this->photoMapper->expects($this->once())
			->method('findExpiredTrashByHouse')
			->with(7, $expectedCutoff)
			->willReturn([]);

		$this->svc->purgeHouse($house, $now);
	}

	public function testPurgeHouseDeletesExpiredRowsAcrossAllEntities(): void {
		$house = $this->makeHouse(7, 30);
		$now = 1_700_000_000;

		$item = new ChecklistItem();
		$itemRef = new \ReflectionProperty($item, 'id');
		$itemRef->setValue($item, 11);

		$list = new Checklist();
		$listRef = new \ReflectionProperty($list, 'id');
		$listRef->setValue($list, 22);

		$note = new Note();
		$noteRef = new \ReflectionProperty($note, 'id');
		$noteRef->setValue($note, 33);

		$photo = new Photo();
		$photoRef = new \ReflectionProperty($photo, 'id');
		$photoRef->setValue($photo, 44);
		$photo->setFileId(444);
		$photo->setUploadedBy('bob');

		$this->itemMapper->method('findExpiredTrashByHouse')->willReturn([$item]);
		$this->listMapper->method('findExpiredTrashByHouse')->willReturn([$list]);
		$this->noteMapper->method('findExpiredTrashByHouse')->willReturn([$note]);
		$this->photoMapper->method('findExpiredTrashByHouse')->willReturn([$photo]);

		$this->itemMapper->expects($this->once())->method('delete')->with($item);
		$this->itemMapper->expects($this->once())->method('deleteByList')->with(22);
		$this->listMapper->expects($this->once())->method('delete')->with($list);
		$this->noteMapper->expects($this->once())->method('delete')->with($note);
		$this->images->expects($this->once())
			->method('deleteFile')
			->with(444, 'bob');
		$this->photoMapper->expects($this->once())->method('delete')->with($photo);

		$counts = $this->svc->purgeHouse($house, $now);
		$this->assertSame(['lists' => 1, 'items' => 1, 'notes' => 1, 'photos' => 1], $counts);
	}

	public function testPurgeHouseFallsBackToOwnerWhenUploaderCannotDeleteFile(): void {
		$house = $this->makeHouse(7, 30, ownerUid: 'alice');
		$photo = new Photo();
		$photo->setFileId(99);
		$photo->setUploadedBy('bob');

		$this->itemMapper->method('findExpiredTrashByHouse')->willReturn([]);
		$this->listMapper->method('findExpiredTrashByHouse')->willReturn([]);
		$this->noteMapper->method('findExpiredTrashByHouse')->willReturn([]);
		$this->photoMapper->method('findExpiredTrashByHouse')->willReturn([$photo]);

		$attempts = [];
		$this->images->expects($this->exactly(2))
			->method('deleteFile')
			->willReturnCallback(function (int $fileId, string $uid) use (&$attempts) {
				$attempts[] = $uid;
				if ($uid === 'bob') {
					throw new \RuntimeException('bob has no access');
				}
			});

		$this->photoMapper->expects($this->once())->method('delete')->with($photo);

		$this->svc->purgeHouse($house);
		$this->assertSame(['bob', 'alice'], $attempts);
	}

	public function testPurgeAllSumsCountsAcrossHouses(): void {
		$houseA = $this->makeHouse(1, 30);
		$houseB = $this->makeHouse(2, 0); // disabled — contributes zeros
		$this->houseMapper->method('findAll')->willReturn([$houseA, $houseB]);

		// Only house A (retention 30) reaches the mappers; house B short-circuits.
		$this->itemMapper->expects($this->once())
			->method('findExpiredTrashByHouse')
			->with(1, $this->anything())
			->willReturn([new ChecklistItem(), new ChecklistItem()]);
		$this->listMapper->expects($this->once())
			->method('findExpiredTrashByHouse')
			->with(1, $this->anything())
			->willReturn([]);
		$this->noteMapper->expects($this->once())
			->method('findExpiredTrashByHouse')
			->with(1, $this->anything())
			->willReturn([new Note()]);
		$this->photoMapper->expects($this->once())
			->method('findExpiredTrashByHouse')
			->with(1, $this->anything())
			->willReturn([]);

		$totals = $this->svc->purgeAll(1_700_000_000);
		$this->assertSame(['lists' => 0, 'items' => 2, 'notes' => 1, 'photos' => 0], $totals);
	}
}
