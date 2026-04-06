<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Db\Note;
use OCA\Pantry\Db\NoteMapper;
use OCA\Pantry\Exception\NotFoundException;
use OCP\AppFramework\Db\DoesNotExistException;

class NoteService {
	public function __construct(
		private NoteMapper $noteMapper,
	) {
	}

	/**
	 * @return Note[]
	 */
	public function listNotes(int $houseId): array {
		return $this->noteMapper->findByHouse($houseId);
	}

	public function getNote(int $noteId): Note {
		try {
			return $this->noteMapper->findById($noteId);
		} catch (DoesNotExistException) {
			throw new NotFoundException('Note not found');
		}
	}

	public function createNote(int $houseId, string $uid, string $title, ?string $content, ?string $color): Note {
		$title = trim($title);
		if ($title === '') {
			throw new \InvalidArgumentException('Note title cannot be empty');
		}
		if ($color !== null) {
			$this->validateColor($color);
		}
		$now = time();
		$note = new Note();
		$note->setHouseId($houseId);
		$note->setTitle($title);
		$note->setContent($content !== null && trim($content) !== '' ? $content : null);
		$note->setColor($color);
		$note->setCreatedBy($uid);
		$note->setSortOrder(0);
		$note->setCreatedAt($now);
		$note->setUpdatedAt($now);
		/** @var Note $saved */
		$saved = $this->noteMapper->insert($note);
		return $saved;
	}

	public function updateNote(int $noteId, array $patch): Note {
		$note = $this->getNote($noteId);
		if (isset($patch['title'])) {
			$title = trim((string)$patch['title']);
			if ($title === '') {
				throw new \InvalidArgumentException('Note title cannot be empty');
			}
			$note->setTitle($title);
		}
		if (array_key_exists('content', $patch)) {
			$c = $patch['content'];
			$note->setContent(is_string($c) && trim($c) !== '' ? $c : null);
		}
		if (array_key_exists('color', $patch)) {
			$color = $patch['color'];
			if ($color !== null && $color !== '') {
				$this->validateColor((string)$color);
				$note->setColor((string)$color);
			} else {
				$note->setColor(null);
			}
		}
		if (isset($patch['sortOrder'])) {
			$note->setSortOrder((int)$patch['sortOrder']);
		}
		$note->setUpdatedAt(time());
		$this->noteMapper->update($note);
		return $note;
	}

	public function deleteNote(int $noteId): void {
		$note = $this->getNote($noteId);
		$this->noteMapper->delete($note);
	}

	/**
	 * Batch reorder notes.
	 *
	 * @param list<array{id: int, sortOrder: int}> $items
	 */
	public function reorderNotes(int $houseId, array $items): void {
		foreach ($items as $entry) {
			$id = (int)($entry['id'] ?? 0);
			$sortOrder = (int)($entry['sortOrder'] ?? 0);
			if ($id <= 0) {
				continue;
			}
			try {
				$note = $this->noteMapper->findById($id);
			} catch (DoesNotExistException) {
				continue;
			}
			if ($note->getHouseId() !== $houseId) {
				continue;
			}
			$note->setSortOrder($sortOrder);
			$note->setUpdatedAt(time());
			$this->noteMapper->update($note);
		}
	}

	private function validateColor(string $color): void {
		if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
			throw new \InvalidArgumentException('Color must be a hex color code (#RRGGBB)');
		}
	}
}
