<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Db\Note;
use OCA\Pantry\Db\NoteMapper;
use OCA\Pantry\Exception\NotFoundException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\Files\NotPermittedException;
use Psr\Log\LoggerInterface;

/**
 * Mirrors a note and a file onto each other.
 *
 * Both directions are push-driven: a note update writes the file, and a file
 * write raises an event that writes the note. Nothing polls.
 *
 * That leaves two ways a write could echo back as a write in the other
 * direction and loop. Writing the file from {@see pushToFile} dispatches the
 * same file event the listener consumes, so the push suppresses its own file id
 * for the rest of the request. Suppression cannot reach a write that lands in a
 * *different* request (an `occ` run, a background job), so every direction also
 * compares content against `sync_hash` — the bytes both sides last agreed on —
 * and does nothing when they already match.
 *
 * The file name carries the note title, which is what the Notes app shows as a
 * note's title. Since the file system rejects characters a note title allows,
 * the sanitized name wins: renaming the file renames the note to match.
 */
class NoteSyncService {
	public const EXTENSION = 'md';

	/** Refuse to pull a file large enough to be something other than a note. */
	private const MAX_BYTES = 1048576;

	/**
	 * File ids this request is itself writing, which must not be read back as
	 * an incoming change.
	 *
	 * @var array<int, true>
	 */
	private array $suppressed = [];

	public function __construct(
		private NoteMapper $noteMapper,
		private IRootFolder $rootFolder,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Whether a file event describes this request's own write.
	 */
	public function isSuppressed(int $fileId): bool {
		return isset($this->suppressed[$fileId]);
	}

	/**
	 * Bind a note to a newly created file under `$folderPath` in `$uid`'s
	 * storage, seeded with the note's current content.
	 *
	 * @throws \InvalidArgumentException when the note is already bound, or the
	 *                                   path is not a writable folder
	 */
	public function startSync(Note $note, string $uid, string $folderPath): Note {
		if ($note->isSynced()) {
			throw new \InvalidArgumentException('Note is already synced to a file');
		}

		$folder = $this->resolveFolder($uid, $folderPath);
		$content = $note->getContent() ?? '';
		$name = $this->uniqueName($folder, $this->fileNameFor($note->getTitle()));

		try {
			$file = $folder->newFile($name, $content);
		} catch (NotPermittedException $e) {
			throw new \InvalidArgumentException('Cannot write to ' . $folderPath . ': ' . $e->getMessage(), 0, $e);
		}

		$note->setSyncFileId($file->getId());
		$note->setSyncOwnerUid($uid);
		$note->setSyncHash($this->hash($content));
		$note->setSyncAt(time());
		// A folder that already held a file of this name forced a suffix onto
		// ours; the name is the title, so the title follows it.
		$note->setTitle($this->titleFor($file->getName()));
		$this->noteMapper->update($note);

		return $note;
	}

	/**
	 * Create a note in `$houseId` from an existing file, optionally leaving the
	 * two bound so later edits on either side keep flowing.
	 *
	 * @throws NotFoundException when the path holds no readable file
	 * @throws \InvalidArgumentException when the file is not text, or too large
	 */
	public function importFile(int $houseId, string $uid, string $path, bool $sync = true): Note {
		$file = $this->resolveFile($uid, $path);
		$content = $this->readText($file);

		$now = time();
		$note = new Note();
		$note->setHouseId($houseId);
		$note->setTitle($this->titleFor($file->getName()));
		$note->setContent($content !== '' ? $content : null);
		$note->setCreatedBy($uid);
		$note->setSortOrder(0);
		$note->setIsPinned(false);
		$note->setCreatedAt($now);
		$note->setUpdatedAt($now);

		if ($sync) {
			$note->setSyncFileId($file->getId());
			$note->setSyncOwnerUid($uid);
			$note->setSyncHash($this->hash($content));
			$note->setSyncAt($now);
		}

		return $this->noteMapper->insert($note);
	}

	/**
	 * Unbind a note from its file. Both survive untouched; neither sees the
	 * other's later edits.
	 */
	public function stopSync(Note $note): Note {
		$note->setSyncFileId(null);
		$note->setSyncOwnerUid(null);
		$note->setSyncHash(null);
		$note->setSyncAt(null);
		$this->noteMapper->update($note);

		return $note;
	}

	/**
	 * Write a note's title and content out to its file.
	 *
	 * Mutates `$note` in place without persisting it; the caller is mid-update
	 * and saves once. Failures are logged rather than thrown — a note edit
	 * should not fail because the file behind it moved out of reach.
	 */
	public function pushToFile(Note $note): void {
		$fileId = $note->getSyncFileId();
		$uid = $note->getSyncOwnerUid();
		if ($fileId === null || $uid === null) {
			return;
		}

		$this->suppressed[$fileId] = true;
		try {
			$file = $this->fileById($uid, $fileId);
			if ($file === null) {
				// Out of reach rather than necessarily gone — a file sitting in
				// the trash looks the same from here. The binding stays so a
				// restore resumes sync; releasing it is the owner's call.
				$this->logger->info('Pantry: note {note} is synced to a file that is out of reach', [
					'note' => $note->getId(),
					'file' => $fileId,
				]);
				return;
			}

			$wantedName = $this->fileNameFor($note->getTitle());
			if ($file->getName() !== $wantedName) {
				$parent = $file->getParent();
				$file->move($parent->getPath() . '/' . $this->uniqueName($parent, $wantedName, $fileId));
				// A collision suffixed the name; the name is the title, so the
				// title follows it.
				$note->setTitle($this->titleFor($file->getName()));
			}

			$content = $note->getContent() ?? '';
			$hash = $this->hash($content);
			if ($hash !== $note->getSyncHash()) {
				$file->putContent($content);
				$note->setSyncHash($hash);
			}
			$note->setSyncAt(time());
		} catch (\Throwable $e) {
			$this->logger->warning('Pantry: could not write note {note} to file {file}', [
				'note' => $note->getId(),
				'file' => $fileId,
				'exception' => $e,
			]);
		} finally {
			unset($this->suppressed[$fileId]);
		}
	}

	/**
	 * Take a file's contents into the note bound to it.
	 *
	 * Returns the note when something changed, so callers can tell a real
	 * incoming edit from the echo of their own write.
	 */
	public function pullFromFile(File $file): ?Note {
		$note = $this->noteForFile($file);
		if ($note === null) {
			return null;
		}

		try {
			$content = $this->readText($file);
		} catch (\Throwable $e) {
			$this->logger->warning('Pantry: could not read file {file} into note {note}', [
				'file' => $file->getId(),
				'note' => $note->getId(),
				'exception' => $e,
			]);
			return null;
		}

		if ($this->hash($content) === $note->getSyncHash()) {
			return null;
		}

		$note->setContent($content !== '' ? $content : null);
		$note->setSyncHash($this->hash($content));
		$note->setSyncAt(time());
		$note->setUpdatedAt(time());
		$this->noteMapper->update($note);

		return $note;
	}

	/**
	 * Carry a file's new name onto the note bound to it.
	 *
	 * Moves reach here too, which is what keeps the binding alive when a file
	 * is dragged elsewhere: only the name is read, never the path.
	 */
	public function applyFileRename(File $file): ?Note {
		$note = $this->noteForFile($file);
		if ($note === null) {
			return null;
		}

		$title = $this->titleFor($file->getName());
		if ($title === '' || $title === $note->getTitle()) {
			return null;
		}

		$note->setTitle($title);
		$note->setSyncAt(time());
		$note->setUpdatedAt(time());
		$this->noteMapper->update($note);

		return $note;
	}

	/**
	 * Path of a note's file, relative to the account that owns it, or null when
	 * the note is unsynced or the file is out of reach.
	 *
	 * `$folderCache` memoizes the owner's folder across a batch of notes, so
	 * listing a wall mounts each account's storage at most once.
	 *
	 * @param array<string, Folder|null> $folderCache
	 */
	public function resolvePath(Note $note, array &$folderCache = []): ?string {
		$fileId = $note->getSyncFileId();
		$uid = $note->getSyncOwnerUid();
		if ($fileId === null || $uid === null) {
			return null;
		}

		if (!array_key_exists($uid, $folderCache)) {
			try {
				$folderCache[$uid] = $this->rootFolder->getUserFolder($uid);
			} catch (\Throwable) {
				$folderCache[$uid] = null;
			}
		}
		$userFolder = $folderCache[$uid];
		if ($userFolder === null) {
			return null;
		}

		$node = $userFolder->getFirstNodeById($fileId);

		return $node === null ? null : $userFolder->getRelativePath($node->getPath());
	}

	/**
	 * The note bound to a file, unless this request is the one writing it.
	 */
	private function noteForFile(File $file): ?Note {
		$fileId = $file->getId();
		if ($this->isSuppressed($fileId)) {
			return null;
		}

		return $this->noteMapper->findBySyncFileId($fileId);
	}

	private function fileById(string $uid, int $fileId): ?File {
		try {
			$node = $this->rootFolder->getUserFolder($uid)->getFirstNodeById($fileId);
		} catch (\Throwable) {
			return null;
		}

		return $node instanceof File ? $node : null;
	}

	private function resolveFolder(string $uid, string $path): Folder {
		$userFolder = $this->rootFolder->getUserFolder($uid);
		$relative = trim($path, '/');
		if ($relative === '') {
			return $userFolder;
		}

		try {
			$node = $userFolder->get($relative);
		} catch (FilesNotFoundException $e) {
			throw new \InvalidArgumentException('No such folder: ' . $path, 0, $e);
		}
		if (!$node instanceof Folder) {
			throw new \InvalidArgumentException('Not a folder: ' . $path);
		}

		return $node;
	}

	private function resolveFile(string $uid, string $path): File {
		$userFolder = $this->rootFolder->getUserFolder($uid);
		$relative = trim($path, '/');

		try {
			$node = $userFolder->get($relative);
		} catch (FilesNotFoundException $e) {
			throw new NotFoundException('No such file: ' . $path, 0, $e);
		}
		if (!$node instanceof File) {
			throw new \InvalidArgumentException('Not a file: ' . $path);
		}

		return $node;
	}

	/**
	 * @throws \InvalidArgumentException when the file is not text, or too large
	 */
	private function readText(File $file): string {
		if ($file->getSize() > self::MAX_BYTES) {
			throw new \InvalidArgumentException('File is too large to sync as a note');
		}
		if (!self::isTextMime($file->getMimeType())) {
			throw new \InvalidArgumentException('Only text files can be synced as notes');
		}

		$content = $file->getContent();
		if (!mb_check_encoding($content, 'UTF-8')) {
			throw new \InvalidArgumentException('File is not valid UTF-8 text');
		}

		return $content;
	}

	/**
	 * Cheap enough to run on every file write on the instance, which is what
	 * the write listener does before it touches the database.
	 */
	public static function isTextMime(string $mime): bool {
		return str_starts_with($mime, 'text/') || $mime === 'application/json';
	}

	private function hash(string $content): string {
		return hash('sha256', $content);
	}

	/**
	 * File name for a note title, with the characters Nextcloud rejects in a
	 * name folded away.
	 */
	private function fileNameFor(string $title): string {
		$name = preg_replace('#[/\\\\<>:"|?*\x00-\x1f]#', '_', trim($title)) ?? '';
		$name = trim($name, " \t.");
		if ($name === '') {
			$name = 'Note';
		}
		// Leave room for a uniqueness suffix inside the 255-byte name limit.
		$name = mb_strcut($name, 0, 200);

		return $name . '.' . self::EXTENSION;
	}

	/**
	 * Note title for a file name: the name without its extension.
	 */
	private function titleFor(string $fileName): string {
		$dot = strrpos($fileName, '.');

		return $dot === false || $dot === 0 ? $fileName : substr($fileName, 0, $dot);
	}

	/**
	 * A name free within `$folder`, ignoring `$ignoreFileId` so renaming a file
	 * to a variation of its own name does not collide with itself.
	 */
	private function uniqueName(Folder $folder, string $wanted, ?int $ignoreFileId = null): string {
		$dot = strrpos($wanted, '.');
		$stem = $dot === false ? $wanted : substr($wanted, 0, $dot);
		$ext = $dot === false ? '' : substr($wanted, $dot);

		$candidate = $wanted;
		$i = 1;
		while ($this->isTaken($folder, $candidate, $ignoreFileId)) {
			$candidate = sprintf('%s (%d)%s', $stem, $i, $ext);
			$i++;
		}

		return $candidate;
	}

	private function isTaken(Folder $folder, string $name, ?int $ignoreFileId): bool {
		if (!$folder->nodeExists($name)) {
			return false;
		}
		if ($ignoreFileId === null) {
			return true;
		}

		try {
			$existing = $folder->get($name);
		} catch (FilesNotFoundException) {
			return false;
		}

		return $existing->getId() !== $ignoreFileId;
	}
}
