<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Db\Note;
use OCA\Pantry\Db\NoteMapper;
use OCA\Pantry\Exception\NotFoundException;
use OCA\Pantry\Service\NoteSyncService;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException as FilesNotFoundException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class NoteSyncServiceTest extends TestCase {
	/** @var NoteMapper&MockObject */
	private NoteMapper $noteMapper;
	/** @var IRootFolder&MockObject */
	private IRootFolder $rootFolder;
	/** @var Folder&MockObject */
	private Folder $userFolder;
	private NoteSyncService $svc;

	protected function setUp(): void {
		$this->noteMapper = $this->createMock(NoteMapper::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->userFolder = $this->createMock(self::userFolderClass());
		$this->rootFolder->method('getUserFolder')->willReturn($this->userFolder);

		$this->svc = new NoteSyncService(
			$this->noteMapper,
			$this->rootFolder,
			$this->createMock(LoggerInterface::class),
		);
	}

	/**
	 * What `getUserFolder()` is declared to return on the server under test.
	 *
	 * The declared type differs across the supported server range — a plain
	 * `Folder`, and `IUserFolder` on newer ones — and PHPUnit rejects a double
	 * of the other one, so the type is read off the interface rather than
	 * named here. Both satisfy the `Folder` the tests use.
	 *
	 * @return class-string
	 */
	private static function userFolderClass(): string {
		$type = (new \ReflectionMethod(IRootFolder::class, 'getUserFolder'))->getReturnType();

		return $type instanceof \ReflectionNamedType ? $type->getName() : Folder::class;
	}

	private function makeNote(array $overrides = []): Note {
		$n = new Note();
		$n->setHouseId($overrides['houseId'] ?? 1);
		$n->setTitle($overrides['title'] ?? 'Groceries');
		$n->setContent($overrides['content'] ?? null);
		$n->setCreatedBy($overrides['createdBy'] ?? 'alice');
		$n->setCreatedAt(1000);
		$n->setUpdatedAt(1000);
		$n->setSyncFileId($overrides['syncFileId'] ?? null);
		$n->setSyncOwnerUid($overrides['syncOwnerUid'] ?? null);
		$n->setSyncHash($overrides['syncHash'] ?? null);
		$ref = new \ReflectionProperty($n, 'id');
		$ref->setValue($n, $overrides['id'] ?? 7);
		return $n;
	}

	/**
	 * @return File&MockObject
	 */
	private function makeFile(int $id, string $name, string $content, string $mime = 'text/markdown'): File {
		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn($id);
		$file->method('getName')->willReturn($name);
		$file->method('getContent')->willReturn($content);
		$file->method('getSize')->willReturn(strlen($content));
		$file->method('getMimeType')->willReturn($mime);
		return $file;
	}

	private static function hash(string $content): string {
		return hash('sha256', $content);
	}

	// ----- startSync -----

	public function testStartSyncCreatesFileNamedAfterTitleAndBindsNote(): void {
		$note = $this->makeNote(['title' => 'Groceries', 'content' => 'milk']);
		$folder = $this->createMock(Folder::class);
		$folder->method('nodeExists')->willReturn(false);
		$this->userFolder->method('get')->with('Notes')->willReturn($folder);

		$folder->expects($this->once())
			->method('newFile')
			->with('Groceries.md', 'milk')
			->willReturn($this->makeFile(42, 'Groceries.md', 'milk'));
		$this->noteMapper->expects($this->once())->method('update');

		$result = $this->svc->startSync($note, 'alice', '/Notes');

		$this->assertSame(42, $result->getSyncFileId());
		$this->assertSame('alice', $result->getSyncOwnerUid());
		$this->assertSame(self::hash('milk'), $result->getSyncHash());
		$this->assertNotNull($result->getSyncAt());
	}

	public function testStartSyncFoldsIllegalTitleCharactersIntoTheFileName(): void {
		$note = $this->makeNote(['title' => 'Trip: 2026/07', 'content' => '']);
		$folder = $this->createMock(Folder::class);
		$folder->method('nodeExists')->willReturn(false);
		$this->userFolder->method('get')->willReturn($folder);

		$folder->expects($this->once())
			->method('newFile')
			->with('Trip_ 2026_07.md', '')
			->willReturn($this->makeFile(42, 'Trip_ 2026_07.md', ''));

		$result = $this->svc->startSync($note, 'alice', '/Notes');

		// The name is the title, so the note follows what the file system allowed.
		$this->assertSame('Trip_ 2026_07', $result->getTitle());
	}

	public function testStartSyncSuffixesACollidingNameAndRetitlesTheNote(): void {
		$note = $this->makeNote(['title' => 'Groceries']);
		$folder = $this->createMock(Folder::class);
		$folder->method('nodeExists')->willReturnCallback(
			fn (string $name): bool => $name === 'Groceries.md',
		);
		$this->userFolder->method('get')->willReturn($folder);

		$folder->expects($this->once())
			->method('newFile')
			->with('Groceries (1).md')
			->willReturn($this->makeFile(42, 'Groceries (1).md', ''));

		$result = $this->svc->startSync($note, 'alice', '/Notes');

		$this->assertSame('Groceries (1)', $result->getTitle());
	}

	public function testStartSyncRejectsAnAlreadySyncedNote(): void {
		$note = $this->makeNote(['syncFileId' => 42, 'syncOwnerUid' => 'alice']);

		$this->expectException(\InvalidArgumentException::class);
		$this->svc->startSync($note, 'alice', '/Notes');
	}

	public function testStartSyncRejectsAPathThatIsNotAFolder(): void {
		$note = $this->makeNote();
		$this->userFolder->method('get')->willReturn($this->makeFile(9, 'a.md', ''));

		$this->expectException(\InvalidArgumentException::class);
		$this->svc->startSync($note, 'alice', '/Notes/a.md');
	}

	// ----- importFile -----

	public function testImportFileCreatesABoundNoteTitledAfterTheFile(): void {
		$file = $this->makeFile(42, 'Shopping.md', '- eggs');
		$this->userFolder->method('get')->with('Notes/Shopping.md')->willReturn($file);
		$this->noteMapper->expects($this->once())
			->method('insert')
			->willReturnArgument(0);

		$note = $this->svc->importFile(3, 'alice', '/Notes/Shopping.md');

		$this->assertSame(3, $note->getHouseId());
		$this->assertSame('Shopping', $note->getTitle());
		$this->assertSame('- eggs', $note->getContent());
		$this->assertSame(42, $note->getSyncFileId());
		$this->assertSame(self::hash('- eggs'), $note->getSyncHash());
	}

	public function testImportFileWithoutSyncLeavesTheNoteUnbound(): void {
		$this->userFolder->method('get')->willReturn($this->makeFile(42, 'Shopping.md', '- eggs'));
		$this->noteMapper->method('insert')->willReturnArgument(0);

		$note = $this->svc->importFile(3, 'alice', '/Notes/Shopping.md', false);

		$this->assertFalse($note->isSynced());
		$this->assertSame('- eggs', $note->getContent());
	}

	public function testImportFileRejectsNonTextFiles(): void {
		$this->userFolder->method('get')->willReturn($this->makeFile(42, 'cat.png', 'binary', 'image/png'));

		$this->expectException(\InvalidArgumentException::class);
		$this->svc->importFile(3, 'alice', '/Photos/cat.png');
	}

	public function testImportFileReportsAMissingPathAsNotFound(): void {
		$this->userFolder->method('get')->willThrowException(new FilesNotFoundException());

		$this->expectException(NotFoundException::class);
		$this->svc->importFile(3, 'alice', '/Notes/gone.md');
	}

	// ----- stopSync -----

	public function testStopSyncClearsTheBinding(): void {
		$note = $this->makeNote([
			'syncFileId' => 42,
			'syncOwnerUid' => 'alice',
			'syncHash' => self::hash('milk'),
			'content' => 'milk',
		]);
		$this->noteMapper->expects($this->once())->method('update');

		$result = $this->svc->stopSync($note);

		$this->assertFalse($result->isSynced());
		$this->assertNull($result->getSyncOwnerUid());
		$this->assertNull($result->getSyncHash());
		// Neither side loses what it had.
		$this->assertSame('milk', $result->getContent());
	}

	// ----- pushToFile -----

	public function testPushToFileWritesChangedContent(): void {
		$note = $this->makeNote([
			'syncFileId' => 42,
			'syncOwnerUid' => 'alice',
			'syncHash' => self::hash('milk'),
			'content' => 'milk, eggs',
		]);
		$file = $this->makeFile(42, 'Groceries.md', 'milk');
		$this->userFolder->method('getFirstNodeById')->with(42)->willReturn($file);

		$file->expects($this->once())->method('putContent')->with('milk, eggs');

		$this->svc->pushToFile($note);

		$this->assertSame(self::hash('milk, eggs'), $note->getSyncHash());
	}

	public function testPushToFileSkipsTheWriteWhenContentAlreadyMatches(): void {
		$note = $this->makeNote([
			'syncFileId' => 42,
			'syncOwnerUid' => 'alice',
			'syncHash' => self::hash('milk'),
			'content' => 'milk',
		]);
		$file = $this->makeFile(42, 'Groceries.md', 'milk');
		$this->userFolder->method('getFirstNodeById')->willReturn($file);

		$file->expects($this->never())->method('putContent');

		$this->svc->pushToFile($note);
	}

	public function testPushToFileRenamesTheFileWhenTheTitleChanges(): void {
		$note = $this->makeNote([
			'title' => 'Weekly shop',
			'syncFileId' => 42,
			'syncOwnerUid' => 'alice',
			'syncHash' => self::hash('milk'),
			'content' => 'milk',
		]);
		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(42);
		$file->method('getName')->willReturn('Groceries.md');
		$parent = $this->createMock(Folder::class);
		$parent->method('getPath')->willReturn('/alice/files/Notes');
		$parent->method('nodeExists')->willReturn(false);
		$file->method('getParent')->willReturn($parent);
		$this->userFolder->method('getFirstNodeById')->willReturn($file);

		$file->expects($this->once())
			->method('move')
			->with('/alice/files/Notes/Weekly shop.md');

		$this->svc->pushToFile($note);
	}

	public function testPushToFileLeavesTheBindingWhenTheFileIsOutOfReach(): void {
		$note = $this->makeNote([
			'syncFileId' => 42,
			'syncOwnerUid' => 'alice',
			'content' => 'milk',
		]);
		$this->userFolder->method('getFirstNodeById')->willReturn(null);
		// A file in the trash looks the same from here, and a binding dropped
		// now could not be rebuilt.
		$this->noteMapper->expects($this->never())->method('update');

		$this->svc->pushToFile($note);

		$this->assertTrue($note->isSynced());
	}

	public function testPushToFileSwallowsFileSystemFailures(): void {
		$note = $this->makeNote([
			'syncFileId' => 42,
			'syncOwnerUid' => 'alice',
			'content' => 'milk',
		]);
		$file = $this->makeFile(42, 'Groceries.md', '');
		$file->method('putContent')->willThrowException(new \RuntimeException('read-only'));
		$this->userFolder->method('getFirstNodeById')->willReturn($file);

		// A note edit must not fail because the file behind it did.
		$this->svc->pushToFile($note);

		$this->assertSame('milk', $note->getContent());
	}

	// ----- pullFromFile -----

	public function testPullFromFileAppliesAnIncomingEdit(): void {
		$note = $this->makeNote([
			'syncFileId' => 42,
			'syncOwnerUid' => 'alice',
			'syncHash' => self::hash('milk'),
			'content' => 'milk',
		]);
		$this->noteMapper->method('findBySyncFileId')->with(42)->willReturn($note);
		$this->noteMapper->expects($this->once())->method('update');

		$result = $this->svc->pullFromFile($this->makeFile(42, 'Groceries.md', 'milk, eggs'));

		$this->assertNotNull($result);
		$this->assertSame('milk, eggs', $result->getContent());
		$this->assertSame(self::hash('milk, eggs'), $result->getSyncHash());
	}

	public function testPullFromFileIgnoresContentBothSidesAlreadyAgreeOn(): void {
		$note = $this->makeNote([
			'syncFileId' => 42,
			'syncOwnerUid' => 'alice',
			'syncHash' => self::hash('milk'),
			'content' => 'milk',
		]);
		$this->noteMapper->method('findBySyncFileId')->willReturn($note);
		$this->noteMapper->expects($this->never())->method('update');

		$this->assertNull($this->svc->pullFromFile($this->makeFile(42, 'Groceries.md', 'milk')));
	}

	public function testPullFromFileIgnoresFilesNoNoteIsBoundTo(): void {
		$this->noteMapper->method('findBySyncFileId')->willReturn(null);

		$this->assertNull($this->svc->pullFromFile($this->makeFile(99, 'unrelated.md', 'hi')));
	}

	public function testPushingDoesNotEchoBackThroughTheFileWriteItTriggers(): void {
		$note = $this->makeNote([
			'syncFileId' => 42,
			'syncOwnerUid' => 'alice',
			'syncHash' => self::hash('milk'),
			'content' => 'milk, eggs',
		]);
		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(42);
		$file->method('getName')->willReturn('Groceries.md');
		$file->method('getMimeType')->willReturn('text/markdown');
		$this->userFolder->method('getFirstNodeById')->willReturn($file);
		$this->noteMapper->method('findBySyncFileId')->willReturn($note);

		// Writing the file dispatches the event the listener consumes; standing
		// in for it here proves the push is not read back as an incoming edit.
		$echoed = 'not called';
		$file->method('putContent')->willReturnCallback(function (string $written) use ($file, &$echoed): void {
			$file->method('getContent')->willReturn($written);
			$file->method('getSize')->willReturn(strlen($written));
			$echoed = $this->svc->pullFromFile($file);
		});
		$this->noteMapper->expects($this->never())->method('update');

		$this->svc->pushToFile($note);

		$this->assertNull($echoed);
	}

	public function testSuppressionIsReleasedAfterThePush(): void {
		$note = $this->makeNote([
			'syncFileId' => 42,
			'syncOwnerUid' => 'alice',
			'syncHash' => self::hash('milk'),
			'content' => 'milk',
		]);
		$this->userFolder->method('getFirstNodeById')->willReturn($this->makeFile(42, 'Groceries.md', 'milk'));

		$this->svc->pushToFile($note);

		$this->assertFalse($this->svc->isSuppressed(42));
	}

	// ----- applyFileRename -----

	public function testApplyFileRenameRetitlesTheNote(): void {
		$note = $this->makeNote(['title' => 'Groceries', 'syncFileId' => 42, 'syncOwnerUid' => 'alice']);
		$this->noteMapper->method('findBySyncFileId')->willReturn($note);
		$this->noteMapper->expects($this->once())->method('update');

		$result = $this->svc->applyFileRename($this->makeFile(42, 'Weekly shop.md', ''));

		$this->assertNotNull($result);
		$this->assertSame('Weekly shop', $result->getTitle());
	}

	public function testApplyFileRenameIgnoresAMoveThatKeptTheName(): void {
		$note = $this->makeNote(['title' => 'Groceries', 'syncFileId' => 42, 'syncOwnerUid' => 'alice']);
		$this->noteMapper->method('findBySyncFileId')->willReturn($note);
		$this->noteMapper->expects($this->never())->method('update');

		$this->assertNull($this->svc->applyFileRename($this->makeFile(42, 'Groceries.md', '')));
	}

	// ----- resolvePath -----

	public function testResolvePathReturnsNullForAnUnsyncedNote(): void {
		$cache = [];
		$this->assertNull($this->svc->resolvePath($this->makeNote(), $cache));
	}

	public function testResolvePathMemoizesTheOwnerFolderAcrossNotes(): void {
		$note = $this->makeNote(['syncFileId' => 42, 'syncOwnerUid' => 'alice']);
		$file = $this->makeFile(42, 'Groceries.md', '');
		$file->method('getPath')->willReturn('/alice/files/Notes/Groceries.md');
		$this->userFolder->method('getFirstNodeById')->willReturn($file);
		$this->userFolder->method('getRelativePath')->willReturn('/Notes/Groceries.md');

		$this->rootFolder->expects($this->once())->method('getUserFolder');

		$cache = [];
		$this->assertSame('/Notes/Groceries.md', $this->svc->resolvePath($note, $cache));
		$this->assertSame('/Notes/Groceries.md', $this->svc->resolvePath($note, $cache));
	}
}
