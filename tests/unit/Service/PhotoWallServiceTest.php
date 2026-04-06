<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Db\Photo;
use OCA\Pantry\Db\PhotoFolder;
use OCA\Pantry\Db\PhotoFolderMapper;
use OCA\Pantry\Db\PhotoMapper;
use OCA\Pantry\Exception\NotFoundException;
use OCA\Pantry\Service\PhotoWallService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PhotoWallServiceTest extends TestCase {
	/** @var PhotoMapper&MockObject */
	private PhotoMapper $photoMapper;
	/** @var PhotoFolderMapper&MockObject */
	private PhotoFolderMapper $folderMapper;
	private PhotoWallService $svc;

	protected function setUp(): void {
		$this->photoMapper = $this->createMock(PhotoMapper::class);
		$this->folderMapper = $this->createMock(PhotoFolderMapper::class);
		$this->svc = new PhotoWallService(
			$this->photoMapper,
			$this->folderMapper,
		);
	}

	private function makeFolder(array $overrides = []): PhotoFolder {
		$f = new PhotoFolder();
		$f->setHouseId($overrides['houseId'] ?? 1);
		$f->setName($overrides['name'] ?? 'Recipes');
		$f->setSortOrder($overrides['sortOrder'] ?? 0);
		$f->setCreatedAt($overrides['createdAt'] ?? 1000);
		$f->setUpdatedAt($overrides['updatedAt'] ?? 1000);
		if (isset($overrides['id'])) {
			// Use reflection to set the id since Entity::setId is not always available
			$ref = new \ReflectionProperty($f, 'id');
			$ref->setValue($f, $overrides['id']);
		}
		return $f;
	}

	private function makePhoto(array $overrides = []): Photo {
		$p = new Photo();
		$p->setHouseId($overrides['houseId'] ?? 1);
		$p->setFolderId($overrides['folderId'] ?? null);
		$p->setFileId($overrides['fileId'] ?? 42);
		$p->setCaption($overrides['caption'] ?? null);
		$p->setUploadedBy($overrides['uploadedBy'] ?? 'admin');
		$p->setSortOrder($overrides['sortOrder'] ?? 0);
		$p->setCreatedAt($overrides['createdAt'] ?? 1000);
		$p->setUpdatedAt($overrides['updatedAt'] ?? 1000);
		if (isset($overrides['id'])) {
			$ref = new \ReflectionProperty($p, 'id');
			$ref->setValue($p, $overrides['id']);
		}
		return $p;
	}

	// ----- Folders -----

	public function testListFoldersDelegatesToMapper(): void {
		$folders = [$this->makeFolder()];
		$this->folderMapper->expects($this->once())
			->method('findByHouse')
			->with(1)
			->willReturn($folders);

		$result = $this->svc->listFolders(1);
		$this->assertSame($folders, $result);
	}

	public function testGetFolderThrowsNotFoundWhenMissing(): void {
		$this->folderMapper->method('findById')
			->willThrowException(new DoesNotExistException(''));

		$this->expectException(NotFoundException::class);
		$this->svc->getFolder(999);
	}

	public function testCreateFolderSetsFieldsAndInserts(): void {
		$this->folderMapper->expects($this->once())
			->method('insert')
			->with($this->callback(function (PhotoFolder $f) {
				return $f->getHouseId() === 1
					&& $f->getName() === 'My Folder'
					&& $f->getSortOrder() === 0
					&& $f->getCreatedAt() > 0;
			}))
			->willReturnArgument(0);

		$result = $this->svc->createFolder(1, '  My Folder  ');
		$this->assertSame('My Folder', $result->getName());
	}

	public function testCreateFolderRejectsEmptyName(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->createFolder(1, '   ');
	}

	public function testUpdateFolderPatchesNameAndSortOrder(): void {
		$folder = $this->makeFolder(['id' => 1, 'name' => 'Old']);
		$this->folderMapper->method('findById')->willReturn($folder);
		$this->folderMapper->expects($this->once())->method('update');

		$result = $this->svc->updateFolder(1, ['name' => 'New', 'sortOrder' => 5]);
		$this->assertSame('New', $result->getName());
		$this->assertSame(5, $result->getSortOrder());
	}

	public function testUpdateFolderRejectsEmptyName(): void {
		$folder = $this->makeFolder(['id' => 1]);
		$this->folderMapper->method('findById')->willReturn($folder);

		$this->expectException(\InvalidArgumentException::class);
		$this->svc->updateFolder(1, ['name' => '  ']);
	}

	public function testDeleteFolderMovesPhotosToRootAndDeletes(): void {
		$folder = $this->makeFolder(['id' => 5]);
		$this->folderMapper->method('findById')->willReturn($folder);

		$this->photoMapper->expects($this->once())
			->method('moveToRoot')
			->with(5);
		$this->folderMapper->expects($this->once())
			->method('delete')
			->with($folder);

		$this->svc->deleteFolder(5);
	}

	public function testReorderFoldersUpdatesMatchingItems(): void {
		$f1 = $this->makeFolder(['id' => 1, 'houseId' => 1, 'sortOrder' => 0]);
		$f2 = $this->makeFolder(['id' => 2, 'houseId' => 1, 'sortOrder' => 1]);
		$foreign = $this->makeFolder(['id' => 3, 'houseId' => 99, 'sortOrder' => 0]);

		$this->folderMapper->method('findById')->willReturnCallback(function (int $id) use ($f1, $f2, $foreign) {
			return match ($id) {
				1 => $f1,
				2 => $f2,
				3 => $foreign,
				default => throw new DoesNotExistException(''),
			};
		});

		// Should update f1 and f2 but skip f3 (wrong house) and id=999 (not found)
		$this->folderMapper->expects($this->exactly(2))->method('update');

		$this->svc->reorderFolders(1, [
			['id' => 2, 'sortOrder' => 0],
			['id' => 1, 'sortOrder' => 1],
			['id' => 3, 'sortOrder' => 2],   // wrong house, skipped
			['id' => 999, 'sortOrder' => 3],  // not found, skipped
		]);

		$this->assertSame(1, $f1->getSortOrder());
		$this->assertSame(0, $f2->getSortOrder());
	}

	// ----- Photos -----

	public function testListPhotosDelegatesToMapper(): void {
		$photos = [$this->makePhoto()];
		$this->photoMapper->expects($this->once())
			->method('findByHouse')
			->with(1)
			->willReturn($photos);

		$result = $this->svc->listPhotos(1);
		$this->assertSame($photos, $result);
	}

	public function testGetPhotoThrowsNotFoundWhenMissing(): void {
		$this->photoMapper->method('findById')
			->willThrowException(new DoesNotExistException(''));

		$this->expectException(NotFoundException::class);
		$this->svc->getPhoto(999);
	}

	public function testAddPhotoSetsAllFields(): void {
		$this->photoMapper->expects($this->once())
			->method('insert')
			->with($this->callback(function (Photo $p) {
				return $p->getHouseId() === 1
					&& $p->getFolderId() === 5
					&& $p->getFileId() === 42
					&& $p->getCaption() === 'Nice pic'
					&& $p->getUploadedBy() === 'alice'
					&& $p->getSortOrder() === 0
					&& $p->getCreatedAt() > 0;
			}))
			->willReturnArgument(0);

		$result = $this->svc->addPhoto(1, 'alice', 42, 5, '  Nice pic  ');
		$this->assertSame('Nice pic', $result->getCaption());
	}

	public function testAddPhotoWithNullCaption(): void {
		$this->photoMapper->expects($this->once())
			->method('insert')
			->with($this->callback(function (Photo $p) {
				return $p->getCaption() === null && $p->getFolderId() === null;
			}))
			->willReturnArgument(0);

		$this->svc->addPhoto(1, 'alice', 42, null, null);
	}

	public function testUpdatePhotoPatchesCaption(): void {
		$photo = $this->makePhoto(['id' => 1]);
		$this->photoMapper->method('findById')->willReturn($photo);
		$this->photoMapper->expects($this->once())->method('update');

		$result = $this->svc->updatePhoto(1, ['caption' => '  Updated  ']);
		$this->assertSame('Updated', $result->getCaption());
	}

	public function testUpdatePhotoClearsCaptionWithEmptyString(): void {
		$photo = $this->makePhoto(['id' => 1, 'caption' => 'Old']);
		$this->photoMapper->method('findById')->willReturn($photo);
		$this->photoMapper->expects($this->once())->method('update');

		$result = $this->svc->updatePhoto(1, ['caption' => '']);
		$this->assertNull($result->getCaption());
	}

	public function testUpdatePhotoMoveToFolder(): void {
		$photo = $this->makePhoto(['id' => 1, 'folderId' => null]);
		$this->photoMapper->method('findById')->willReturn($photo);
		$this->photoMapper->expects($this->once())->method('update');

		$result = $this->svc->updatePhoto(1, ['folderId' => 5]);
		$this->assertSame(5, $result->getFolderId());
	}

	public function testUpdatePhotoMoveToRootWithZero(): void {
		$photo = $this->makePhoto(['id' => 1, 'folderId' => 5]);
		$this->photoMapper->method('findById')->willReturn($photo);
		$this->photoMapper->expects($this->once())->method('update');

		$result = $this->svc->updatePhoto(1, ['folderId' => 0]);
		$this->assertNull($result->getFolderId());
	}

	public function testUpdatePhotoSortOrder(): void {
		$photo = $this->makePhoto(['id' => 1, 'sortOrder' => 0]);
		$this->photoMapper->method('findById')->willReturn($photo);
		$this->photoMapper->expects($this->once())->method('update');

		$result = $this->svc->updatePhoto(1, ['sortOrder' => 10]);
		$this->assertSame(10, $result->getSortOrder());
	}

	public function testDeletePhotoRemovesFromMapper(): void {
		$photo = $this->makePhoto(['id' => 1]);
		$this->photoMapper->method('findById')->willReturn($photo);
		$this->photoMapper->expects($this->once())
			->method('delete')
			->with($photo);

		$this->svc->deletePhoto(1);
	}

	public function testReorderPhotosUpdatesMatchingItems(): void {
		$p1 = $this->makePhoto(['id' => 1, 'houseId' => 1]);
		$p2 = $this->makePhoto(['id' => 2, 'houseId' => 1]);
		$foreign = $this->makePhoto(['id' => 3, 'houseId' => 99]);

		$this->photoMapper->method('findById')->willReturnCallback(function (int $id) use ($p1, $p2, $foreign) {
			return match ($id) {
				1 => $p1,
				2 => $p2,
				3 => $foreign,
				default => throw new DoesNotExistException(''),
			};
		});

		$this->photoMapper->expects($this->exactly(2))->method('update');

		$this->svc->reorderPhotos(1, [
			['id' => 2, 'sortOrder' => 0],
			['id' => 1, 'sortOrder' => 1],
			['id' => 3, 'sortOrder' => 2],  // wrong house
		]);

		$this->assertSame(1, $p1->getSortOrder());
		$this->assertSame(0, $p2->getSortOrder());
	}
}
