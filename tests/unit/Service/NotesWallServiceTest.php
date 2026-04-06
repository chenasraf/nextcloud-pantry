<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Db\Note;
use OCA\Pantry\Db\NoteMapper;
use OCA\Pantry\Exception\NotFoundException;
use OCA\Pantry\Service\NotesWallService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class NotesWallServiceTest extends TestCase {
	/** @var NoteMapper&MockObject */
	private NoteMapper $noteMapper;
	private NotesWallService $svc;

	protected function setUp(): void {
		$this->noteMapper = $this->createMock(NoteMapper::class);
		$this->svc = new NotesWallService($this->noteMapper);
	}

	private function makeNote(array $overrides = []): Note {
		$n = new Note();
		$n->setHouseId($overrides['houseId'] ?? 1);
		$n->setTitle($overrides['title'] ?? 'Groceries');
		$n->setContent($overrides['content'] ?? null);
		$n->setColor($overrides['color'] ?? null);
		$n->setCreatedBy($overrides['createdBy'] ?? 'admin');
		$n->setSortOrder($overrides['sortOrder'] ?? 0);
		$n->setCreatedAt($overrides['createdAt'] ?? 1000);
		$n->setUpdatedAt($overrides['updatedAt'] ?? 1000);
		if (isset($overrides['id'])) {
			$ref = new \ReflectionProperty($n, 'id');
			$ref->setValue($n, $overrides['id']);
		}
		return $n;
	}

	public function testListNotesDelegatesToMapper(): void {
		$notes = [$this->makeNote()];
		$this->noteMapper->expects($this->once())
			->method('findByHouse')
			->with(1)
			->willReturn($notes);

		$this->assertSame($notes, $this->svc->listNotes(1));
	}

	public function testGetNoteThrowsNotFoundWhenMissing(): void {
		$this->noteMapper->method('findById')
			->willThrowException(new DoesNotExistException(''));

		$this->expectException(NotFoundException::class);
		$this->svc->getNote(999);
	}

	public function testCreateNoteSetsFieldsAndInserts(): void {
		$this->noteMapper->expects($this->once())
			->method('insert')
			->with($this->callback(function (Note $n) {
				return $n->getHouseId() === 1
					&& $n->getTitle() === 'My Note'
					&& $n->getContent() === 'Some content'
					&& $n->getColor() === '#ff0000'
					&& $n->getCreatedBy() === 'alice'
					&& $n->getCreatedAt() > 0;
			}))
			->willReturnArgument(0);

		$result = $this->svc->createNote(1, 'alice', '  My Note  ', 'Some content', '#ff0000');
		$this->assertSame('My Note', $result->getTitle());
	}

	public function testCreateNoteRejectsEmptyTitle(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->createNote(1, 'alice', '   ', null, null);
	}

	public function testCreateNoteRejectsInvalidColor(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->createNote(1, 'alice', 'Test', null, 'red');
	}

	public function testCreateNoteAllowsNullColor(): void {
		$this->noteMapper->expects($this->once())
			->method('insert')
			->with($this->callback(fn (Note $n) => $n->getColor() === null))
			->willReturnArgument(0);

		$this->svc->createNote(1, 'alice', 'Test', null, null);
	}

	public function testUpdateNotePatchesTitleContentColor(): void {
		$note = $this->makeNote(['id' => 1]);
		$this->noteMapper->method('findById')->willReturn($note);
		$this->noteMapper->expects($this->once())->method('update');

		$result = $this->svc->updateNote(1, [
			'title' => 'New Title',
			'content' => 'New content',
			'color' => '#00ff00',
		]);
		$this->assertSame('New Title', $result->getTitle());
		$this->assertSame('New content', $result->getContent());
		$this->assertSame('#00ff00', $result->getColor());
	}

	public function testUpdateNoteRejectsEmptyTitle(): void {
		$note = $this->makeNote(['id' => 1]);
		$this->noteMapper->method('findById')->willReturn($note);

		$this->expectException(\InvalidArgumentException::class);
		$this->svc->updateNote(1, ['title' => '  ']);
	}

	public function testUpdateNoteRejectsInvalidColor(): void {
		$note = $this->makeNote(['id' => 1]);
		$this->noteMapper->method('findById')->willReturn($note);

		$this->expectException(\InvalidArgumentException::class);
		$this->svc->updateNote(1, ['color' => 'nope']);
	}

	public function testUpdateNoteClearsContentWithEmptyString(): void {
		$note = $this->makeNote(['id' => 1, 'content' => 'Old']);
		$this->noteMapper->method('findById')->willReturn($note);
		$this->noteMapper->expects($this->once())->method('update');

		$result = $this->svc->updateNote(1, ['content' => '']);
		$this->assertNull($result->getContent());
	}

	public function testUpdateNoteClearsColorWithEmptyString(): void {
		$note = $this->makeNote(['id' => 1, 'color' => '#ff0000']);
		$this->noteMapper->method('findById')->willReturn($note);
		$this->noteMapper->expects($this->once())->method('update');

		$result = $this->svc->updateNote(1, ['color' => '']);
		$this->assertNull($result->getColor());
	}

	public function testUpdateNoteSortOrder(): void {
		$note = $this->makeNote(['id' => 1, 'sortOrder' => 0]);
		$this->noteMapper->method('findById')->willReturn($note);
		$this->noteMapper->expects($this->once())->method('update');

		$result = $this->svc->updateNote(1, ['sortOrder' => 5]);
		$this->assertSame(5, $result->getSortOrder());
	}

	public function testDeleteNoteRemovesFromMapper(): void {
		$note = $this->makeNote(['id' => 1]);
		$this->noteMapper->method('findById')->willReturn($note);
		$this->noteMapper->expects($this->once())
			->method('delete')
			->with($note);

		$this->svc->deleteNote(1);
	}

	public function testReorderNotesUpdatesMatchingItems(): void {
		$n1 = $this->makeNote(['id' => 1, 'houseId' => 1]);
		$n2 = $this->makeNote(['id' => 2, 'houseId' => 1]);
		$foreign = $this->makeNote(['id' => 3, 'houseId' => 99]);

		$this->noteMapper->method('findById')->willReturnCallback(function (int $id) use ($n1, $n2, $foreign) {
			return match ($id) {
				1 => $n1,
				2 => $n2,
				3 => $foreign,
				default => throw new DoesNotExistException(''),
			};
		});

		$this->noteMapper->expects($this->exactly(2))->method('update');

		$this->svc->reorderNotes(1, [
			['id' => 2, 'sortOrder' => 0],
			['id' => 1, 'sortOrder' => 1],
			['id' => 3, 'sortOrder' => 2],   // wrong house
		]);

		$this->assertSame(1, $n1->getSortOrder());
		$this->assertSame(0, $n2->getSortOrder());
	}
}
