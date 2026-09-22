<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Controller;

use OCA\Pantry\Activity\ActivityPublisher;
use OCA\Pantry\Db\Note;
use OCA\Pantry\Db\Share;
use OCA\Pantry\Exception\ForbiddenException;
use OCA\Pantry\Exception\NotFoundException;
use OCA\Pantry\Permission\Permission;
use OCA\Pantry\ResponseDefinitions;
use OCA\Pantry\Service\HouseAuthService;
use OCA\Pantry\Service\HouseService;
use OCA\Pantry\Service\NoteService;
use OCA\Pantry\Service\NoteSyncService;
use OCA\Pantry\Service\NotificationService;
use OCA\Pantry\Service\PermissionService;
use OCA\Pantry\Service\ShareService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @psalm-import-type PantryNote from ResponseDefinitions
 * @psalm-import-type PantrySuccess from ResponseDefinitions
 */
final class NoteController extends OCSController {
	use TranslatesDomainExceptions;

	public function __construct(
		string $appName,
		IRequest $request,
		private NoteService $notes,
		private NoteSyncService $noteSync,
		private HouseAuthService $auth,
		private HouseService $houses,
		private NotificationService $notifications,
		private ActivityPublisher $activity,
		private PermissionService $permissions,
		private ShareService $shares,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * List all notes in a house
	 *
	 * @param int $houseId House id.
	 * @param string $sortBy Sort mode (custom, newest, oldest, title_asc, title_desc).
	 * @param int<1, 500> $limit Maximum number of notes to return.
	 * @param int<0, max> $offset Number of notes to skip.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryNote>, array{}>
	 *
	 * 200: Notes returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/notes')]
	#[NoAdminRequired]
	#[Permission(['canViewNotes'])]
	public function indexNotes(int $houseId, string $sortBy = 'custom', int $limit = 100, int $offset = 0): DataResponse {
		return $this->runAction(function () use ($houseId, $sortBy, $limit, $offset): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$all = $this->notes->listNotes($houseId, $sortBy);
			$sliced = array_slice($all, max(0, $offset), max(0, $limit));
			$roleEdit = $this->permissions->can($houseId, $uid, 'canUpdateNotes');
			$shareMap = $this->shares->userShareMap($houseId, $uid);
			return new DataResponse($this->noteListJson($sliced, $roleEdit, $shareMap));
		});
	}

	/**
	 * List soft-deleted notes in a house (trash)
	 *
	 * Returns notes whose deleted_at is set, most recently deleted first.
	 *
	 * @param int $houseId House id.
	 * @param int<1, 500> $limit Maximum number of notes to return.
	 * @param int<0, max> $offset Number of notes to skip.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryNote>, array{}>
	 *
	 * 200: Deleted notes returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/notes/trash')]
	#[NoAdminRequired]
	#[Permission(['canViewNotes'])]
	public function indexDeletedNotes(int $houseId, int $limit = 200, int $offset = 0): DataResponse {
		return $this->runAction(function () use ($houseId, $limit, $offset): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$all = $this->notes->listDeletedNotes($houseId);
			$sliced = array_slice($all, max(0, $offset), max(0, $limit));
			$roleEdit = $this->permissions->can($houseId, $uid, 'canUpdateNotes');
			$shareMap = $this->shares->userShareMap($houseId, $uid);
			return new DataResponse($this->noteListJson($sliced, $roleEdit, $shareMap));
		});
	}

	/**
	 * Empty the notes trash, permanently deleting every soft-deleted note
	 *
	 * @param int $houseId House id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Trash emptied
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/notes/trash')]
	#[NoAdminRequired]
	#[Permission(['canDeleteNotes'])]
	public function emptyTrash(int $houseId): DataResponse {
		return $this->runAction(function () use ($houseId): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->notes->emptyTrash($houseId);
			return new DataResponse(['success' => true]);
		});
	}

	/**
	 * Create a note
	 *
	 * @param int $houseId House id.
	 * @param string $title Note title.
	 * @param string|null $content Markdown content.
	 * @param string|null $color Hex color (#RRGGBB).
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryNote, array{}>
	 *
	 * 200: Note created
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/notes')]
	#[NoAdminRequired]
	#[Permission(['canCreateNotes'])]
	public function createNote(int $houseId, string $title, ?string $content = null, ?string $color = null): DataResponse {
		return $this->runAction(function () use ($houseId, $title, $content, $color): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$note = $this->notes->createNote($houseId, $uid, $title, $content, $color);
			$this->notifications->notifyNoteCreated($houseId, $uid, (int)$note->getId(), $note->getTitle());
			$this->activity->publishNoteCreated(
				$houseId,
				$this->houses->get($houseId)->getName(),
				$uid,
				(int)$note->getId(),
				$note->getTitle(),
			);
			$roleEdit = $this->permissions->can($houseId, $uid, 'canUpdateNotes');
			return new DataResponse($this->noteJson($note, $roleEdit, $this->shares->userShareMap($houseId, $uid)));
		});
	}

	/**
	 * Update a note
	 *
	 * @param int $houseId House id.
	 * @param int $noteId Note id.
	 * @param string|null $title New title.
	 * @param string|null $content New content (empty string clears).
	 * @param string|null $color New color (empty string clears).
	 * @param int|null $sortOrder New sort order.
	 * @param bool|null $isPinned New pinned state.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryNote, array{}>
	 *
	 * 200: Note updated
	 */
	#[ApiRoute(verb: 'PATCH', url: '/api/houses/{houseId}/notes/{noteId}', requirements: ['noteId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canViewNotes'])]
	public function updateNote(int $houseId, int $noteId, ?string $title = null, ?string $content = null, ?string $color = null, ?int $sortOrder = null, ?bool $isPinned = null): DataResponse {
		return $this->runAction(function () use ($houseId, $noteId, $title, $content, $color, $sortOrder, $isPinned): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$existing = $this->notes->getNote($noteId);
			$this->assertInHouse($existing->getHouseId(), $houseId);
			// Editing needs the role capability or an editor share on this note.
			if (!$this->permissions->can($houseId, $uid, 'canUpdateNotes')
				&& !$this->shares->hasEditShare($uid, Share::TYPE_NOTE, $noteId)) {
				throw new ForbiddenException('Missing permission: canUpdateNotes');
			}
			$patch = [];
			if ($title !== null) {
				$patch['title'] = $title;
			}
			if ($content !== null) {
				$patch['content'] = $content;
			}
			if ($color !== null) {
				$patch['color'] = $color;
			}
			if ($sortOrder !== null) {
				$patch['sortOrder'] = $sortOrder;
			}
			if ($isPinned !== null) {
				$patch['isPinned'] = $isPinned;
			}
			$note = $this->notes->updateNote($noteId, $patch);
			// Only notify for content/title changes, not color/sort-order-only changes
			if ($title !== null || $content !== null) {
				$this->notifications->notifyNoteEdited($houseId, $uid, (int)$note->getId(), $note->getTitle());
				$this->activity->publishNoteEdited(
					$houseId,
					$this->houses->get($houseId)->getName(),
					$uid,
					(int)$note->getId(),
					$note->getTitle(),
				);
			}
			$roleEdit = $this->permissions->can($houseId, $uid, 'canUpdateNotes');
			return new DataResponse($this->noteJson($note, $roleEdit, $this->shares->userShareMap($houseId, $uid)));
		});
	}

	/**
	 * Delete a note
	 *
	 * @param int $houseId House id.
	 * @param int $noteId Note id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Note deleted
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/notes/{noteId}', requirements: ['noteId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canDeleteNotes'])]
	public function deleteNote(int $houseId, int $noteId): DataResponse {
		return $this->runAction(function () use ($houseId, $noteId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$existing = $this->notes->getNote($noteId);
			$this->assertInHouse($existing->getHouseId(), $houseId);
			$noteTitle = $existing->getTitle();
			$this->notes->deleteNote($noteId);
			$this->activity->publishNoteDeleted(
				$houseId,
				$this->houses->get($houseId)->getName(),
				$uid,
				$noteId,
				$noteTitle,
			);
			return new DataResponse(['success' => true]);
		});
	}

	/**
	 * Restore a soft-deleted note back into the active notes wall
	 *
	 * @param int $houseId House id.
	 * @param int $noteId Note id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryNote, array{}>
	 *
	 * 200: Note restored
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/notes/{noteId}/restore', requirements: ['noteId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canDeleteNotes'])]
	public function restoreNote(int $houseId, int $noteId): DataResponse {
		return $this->runAction(function () use ($houseId, $noteId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$existing = $this->notes->getNote($noteId, includeDeleted: true);
			$this->assertInHouse($existing->getHouseId(), $houseId);
			$restored = $this->notes->restoreNote($noteId);
			$roleEdit = $this->permissions->can($houseId, $uid, 'canUpdateNotes');
			return new DataResponse($this->noteJson($restored, $roleEdit, $this->shares->userShareMap($houseId, $uid)));
		});
	}

	/**
	 * Permanently delete a note, bypassing the trash
	 *
	 * Works on both live notes and notes already in trash.
	 *
	 * @param int $houseId House id.
	 * @param int $noteId Note id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Note permanently deleted
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/notes/{noteId}/permanent', requirements: ['noteId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canDeleteNotes'])]
	public function permanentlyDeleteNote(int $houseId, int $noteId): DataResponse {
		return $this->runAction(function () use ($houseId, $noteId): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$existing = $this->notes->getNote($noteId, includeDeleted: true);
			$this->assertInHouse($existing->getHouseId(), $houseId);
			$this->notes->permanentlyDeleteNote($noteId);
			return new DataResponse(['success' => true]);
		});
	}

	/**
	 * Start syncing a note with a file
	 *
	 * Creates the file under `folderPath` in the calling account's storage,
	 * seeded with the note's content, and binds the two. From then on an edit to
	 * either side is written straight through to the other.
	 *
	 * @param int $houseId House id.
	 * @param int $noteId Note id.
	 * @param string $folderPath Folder to create the file in, relative to the account's files root.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryNote, array{}>
	 *
	 * 200: Sync started
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/notes/{noteId}/sync', requirements: ['noteId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canUpdateNotes'])]
	public function startNoteSync(int $houseId, int $noteId, string $folderPath = '/'): DataResponse {
		return $this->runAction(function () use ($houseId, $noteId, $folderPath): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$note = $this->notes->getNote($noteId);
			$this->assertInHouse($note->getHouseId(), $houseId);
			$synced = $this->noteSync->startSync($note, $uid, $folderPath);
			$roleEdit = $this->permissions->can($houseId, $uid, 'canUpdateNotes');
			return new DataResponse($this->noteJson($synced, $roleEdit, $this->shares->userShareMap($houseId, $uid)));
		});
	}

	/**
	 * Stop syncing a note with its file
	 *
	 * The note and the file both survive with their current content; neither
	 * sees the other's later edits.
	 *
	 * @param int $houseId House id.
	 * @param int $noteId Note id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryNote, array{}>
	 *
	 * 200: Sync stopped
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/notes/{noteId}/sync', requirements: ['noteId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canUpdateNotes'])]
	public function stopNoteSync(int $houseId, int $noteId): DataResponse {
		return $this->runAction(function () use ($houseId, $noteId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$note = $this->notes->getNote($noteId, includeDeleted: true);
			$this->assertInHouse($note->getHouseId(), $houseId);
			$unlinked = $this->noteSync->stopSync($note);
			$roleEdit = $this->permissions->can($houseId, $uid, 'canUpdateNotes');
			return new DataResponse($this->noteJson($unlinked, $roleEdit, $this->shares->userShareMap($houseId, $uid)));
		});
	}

	/**
	 * Create a note from a text file
	 *
	 * @param int $houseId House id.
	 * @param string $path File to import, relative to the account's files root.
	 * @param bool $sync Whether to keep the note and the file in sync afterwards.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryNote, array{}>
	 *
	 * 200: Note created from the file
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/notes/import')]
	#[NoAdminRequired]
	#[Permission(['canCreateNotes'])]
	public function importNoteFromFile(int $houseId, string $path, bool $sync = true): DataResponse {
		return $this->runAction(function () use ($houseId, $path, $sync): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$note = $this->noteSync->importFile($houseId, $uid, $path, $sync);
			$this->notifications->notifyNoteCreated($houseId, $uid, (int)$note->getId(), $note->getTitle());
			$this->activity->publishNoteCreated(
				$houseId,
				$this->houses->get($houseId)->getName(),
				$uid,
				(int)$note->getId(),
				$note->getTitle(),
			);
			$roleEdit = $this->permissions->can($houseId, $uid, 'canUpdateNotes');
			return new DataResponse($this->noteJson($note, $roleEdit, $this->shares->userShareMap($houseId, $uid)));
		});
	}

	/**
	 * Batch reorder notes
	 *
	 * @param int $houseId House id.
	 * @param list<array{id: int, sortOrder: int}> $items Reorder entries.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Notes reordered
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/notes/reorder')]
	#[NoAdminRequired]
	#[Permission(['canUpdateNotes'])]
	public function reorderNotes(int $houseId, array $items = []): DataResponse {
		return $this->runAction(function () use ($houseId, $items): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->notes->reorderNotes($houseId, $items);
			return new DataResponse(['success' => true]);
		});
	}

	/**
	 * Serialize a note with the current user's effective `canEdit` flag
	 * (role capability or an editor share on the note), plus the path of the
	 * file it syncs with.
	 *
	 * `syncPath` is resolved rather than stored, so it can never name a file
	 * that has since moved. It costs a lookup in the syncing account's storage,
	 * which `$folderCache` shares across a batch — and which unsynced notes,
	 * the overwhelming majority, skip entirely.
	 *
	 * @param array<string, array<int, string>> $shareMap
	 * @param array<string, \OCP\Files\Folder|null> $folderCache
	 */
	private function noteJson(Note $note, bool $roleEdit, array $shareMap, array &$folderCache = []): array {
		return array_merge($note->jsonSerialize(), [
			'canEdit' => $this->shares->canEditFromMap(Share::TYPE_NOTE, (int)$note->getId(), $roleEdit, $shareMap),
			'syncPath' => $this->noteSync->resolvePath($note, $folderCache),
		]);
	}

	/**
	 * Serialize a batch of notes, resolving every sync path against one shared
	 * folder cache.
	 *
	 * @param Note[] $notes
	 * @param array<string, array<int, string>> $shareMap
	 * @return list<array<string, mixed>>
	 */
	private function noteListJson(array $notes, bool $roleEdit, array $shareMap): array {
		// By reference: an arrow function would copy the cache per note and the
		// sharing that makes the batch cheap would be lost.
		$folderCache = [];
		$serialize = function (Note $n) use ($roleEdit, $shareMap, &$folderCache): array {
			return $this->noteJson($n, $roleEdit, $shareMap, $folderCache);
		};

		return array_values(array_map($serialize, $notes));
	}

	private function requireUid(): string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new ForbiddenException('Not authenticated');
		}
		return $user->getUID();
	}

	private function assertInHouse(int $entityHouseId, int $routeHouseId): void {
		if ($entityHouseId !== $routeHouseId) {
			throw new NotFoundException('Note does not belong to this house');
		}
	}
}
