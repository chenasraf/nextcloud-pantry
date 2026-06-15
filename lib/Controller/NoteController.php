<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Controller;

use OCA\Pantry\Activity\ActivityPublisher;
use OCA\Pantry\Exception\ForbiddenException;
use OCA\Pantry\Exception\NotFoundException;
use OCA\Pantry\ResponseDefinitions;
use OCA\Pantry\Service\HouseAuthService;
use OCA\Pantry\Service\HouseService;
use OCA\Pantry\Service\NoteService;
use OCA\Pantry\Service\NotificationService;
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
		private HouseAuthService $auth,
		private HouseService $houses,
		private NotificationService $notifications,
		private ActivityPublisher $activity,
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
	public function indexNotes(int $houseId, string $sortBy = 'custom', int $limit = 100, int $offset = 0): DataResponse {
		return $this->runAction(function () use ($houseId, $sortBy, $limit, $offset): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$all = $this->notes->listNotes($houseId, $sortBy);
			$sliced = array_slice($all, max(0, $offset), max(0, $limit));
			return new DataResponse(array_map(fn ($n) => $n->jsonSerialize(), $sliced));
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
	public function indexDeletedNotes(int $houseId, int $limit = 200, int $offset = 0): DataResponse {
		return $this->runAction(function () use ($houseId, $limit, $offset): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$all = $this->notes->listDeletedNotes($houseId);
			$sliced = array_slice($all, max(0, $offset), max(0, $limit));
			return new DataResponse(array_map(fn ($n) => $n->jsonSerialize(), $sliced));
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
			return new DataResponse($note->jsonSerialize());
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
	public function updateNote(int $houseId, int $noteId, ?string $title = null, ?string $content = null, ?string $color = null, ?int $sortOrder = null, ?bool $isPinned = null): DataResponse {
		return $this->runAction(function () use ($houseId, $noteId, $title, $content, $color, $sortOrder, $isPinned): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$existing = $this->notes->getNote($noteId);
			$this->assertInHouse($existing->getHouseId(), $houseId);
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
			return new DataResponse($note->jsonSerialize());
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
	public function restoreNote(int $houseId, int $noteId): DataResponse {
		return $this->runAction(function () use ($houseId, $noteId): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$existing = $this->notes->getNote($noteId, includeDeleted: true);
			$this->assertInHouse($existing->getHouseId(), $houseId);
			$restored = $this->notes->restoreNote($noteId);
			return new DataResponse($restored->jsonSerialize());
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
	public function reorderNotes(int $houseId, array $items = []): DataResponse {
		return $this->runAction(function () use ($houseId, $items): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->notes->reorderNotes($houseId, $items);
			return new DataResponse(['success' => true]);
		});
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
