<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Exception\NotFoundException;
use OCA\Pantry\Service\ImageService;
use OCA\Pantry\Service\PrefsService;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ImageServiceTest extends TestCase {
	/** @var Folder&MockObject */
	private Folder $userFolder;
	/** @var PrefsService&MockObject */
	private PrefsService $prefs;
	private ImageService $svc;

	protected function setUp(): void {
		// getUserFolder() returns Folder on older servers and IUserFolder on newer ones.
		$type = (new \ReflectionMethod(IRootFolder::class, 'getUserFolder'))->getReturnType();
		$this->userFolder = $this->createMock($type instanceof \ReflectionNamedType ? $type->getName() : Folder::class);
		$rootFolder = $this->createMock(IRootFolder::class);
		$rootFolder->method('getUserFolder')->with('alice')->willReturn($this->userFolder);
		$this->prefs = $this->createMock(PrefsService::class);
		$this->prefs->method('getImageFolder')->willReturn('');
		$this->svc = new ImageService($rootFolder, $this->prefs);
	}

	/**
	 * @return File&MockObject
	 */
	private function pickedFile(string $mime = 'image/jpeg', bool $readable = true): File {
		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(42);
		$file->method('getName')->willReturn('cake.jpg');
		$file->method('getMimetype')->willReturn($mime);
		$file->method('isReadable')->willReturn($readable);
		$file->method('getContent')->willReturn('bytes');
		$this->userFolder->method('getFirstNodeById')->with(42)->willReturn($file);
		return $file;
	}

	public function testLinkReturnsPickedFileWithoutCopying(): void {
		$this->pickedFile();
		$this->userFolder->expects($this->never())->method('newFolder');
		$this->userFolder->expects($this->never())->method('newFile');

		$this->assertSame(42, $this->svc->attachItemImageFromFiles('alice', 1, 42, ImageService::MODE_LINK));
		$this->assertSame(42, $this->svc->attachPhotoFromFiles('alice', 1, 42, ImageService::MODE_LINK));
	}

	public function testImportCopiesIntoPantryFolder(): void {
		$this->pickedFile();
		$sub = $this->createMock(Folder::class);
		$sub->method('nodeExists')->willReturn(false);
		$copy = $this->createMock(File::class);
		$copy->method('getId')->willReturn(77);
		$sub->expects($this->once())->method('newFile')->with('cake.jpg', 'bytes')->willReturn($copy);
		$this->userFolder->method('nodeExists')->with(ImageService::PHOTOS_SUBDIR)->willReturn(true);
		$this->userFolder->method('get')->with(ImageService::PHOTOS_SUBDIR)->willReturn($sub);

		$this->assertSame(77, $this->svc->attachPhotoFromFiles('alice', 1, 42, ImageService::MODE_IMPORT));
	}

	public function testRejectsNonImage(): void {
		$this->pickedFile('application/pdf');

		$this->expectException(\InvalidArgumentException::class);
		$this->svc->attachItemImageFromFiles('alice', 1, 42, ImageService::MODE_LINK);
	}

	public function testRejectsUnreadableFile(): void {
		$this->pickedFile(readable: false);

		$this->expectException(NotFoundException::class);
		$this->svc->attachItemImageFromFiles('alice', 1, 42, ImageService::MODE_LINK);
	}

	public function testRejectsFileOutsideAccount(): void {
		$this->userFolder->method('getFirstNodeById')->willReturn(null);

		$this->expectException(NotFoundException::class);
		$this->svc->attachItemImageFromFiles('alice', 1, 42, ImageService::MODE_LINK);
	}

	public function testRejectsUnknownMode(): void {
		$this->pickedFile();

		$this->expectException(\InvalidArgumentException::class);
		$this->svc->attachItemImageFromFiles('alice', 1, 42, 'share');
	}
}
