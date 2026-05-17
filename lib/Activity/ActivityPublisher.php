<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Activity;

use OCA\Pantry\AppInfo\Application;
use OCA\Pantry\Db\HouseMemberMapper;
use OCP\Activity\IManager as IActivityManager;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

/**
 * Publishes Pantry activity events to the Nextcloud Activity stream.
 *
 * One event is published per house member (including the actor) so each user
 * sees the entry on their own activity feed.
 */
class ActivityPublisher {
	public const TYPE = Application::APP_ID;

	public const SUBJECT_LIST_CREATED = 'list_created';
	public const SUBJECT_LIST_UPDATED = 'list_updated';
	public const SUBJECT_LIST_DELETED = 'list_deleted';

	public const SUBJECT_ITEM_ADDED = 'item_added';
	public const SUBJECT_ITEM_UPDATED = 'item_updated';
	public const SUBJECT_ITEM_DONE = 'item_done';
	public const SUBJECT_ITEM_REOPENED = 'item_reopened';
	public const SUBJECT_ITEM_MOVED = 'item_moved';
	public const SUBJECT_ITEM_DELETED = 'item_deleted';
	public const SUBJECT_ITEM_RESTORED = 'item_restored';
	public const SUBJECT_ITEMS_RECURRED = 'items_recurred';

	public const SUBJECT_PHOTO_UPLOADED = 'photo_uploaded';
	public const SUBJECT_PHOTO_MOVED = 'photo_moved';
	public const SUBJECT_PHOTO_DELETED = 'photo_deleted';

	public const SUBJECT_FOLDER_CREATED = 'folder_created';
	public const SUBJECT_FOLDER_RENAMED = 'folder_renamed';
	public const SUBJECT_FOLDER_DELETED = 'folder_deleted';

	public const SUBJECT_NOTE_CREATED = 'note_created';
	public const SUBJECT_NOTE_EDITED = 'note_edited';
	public const SUBJECT_NOTE_DELETED = 'note_deleted';

	public function __construct(
		private IActivityManager $activityManager,
		private HouseMemberMapper $memberMapper,
		private IURLGenerator $urlGenerator,
		private LoggerInterface $logger,
	) {
	}

	public function publishListCreated(int $houseId, string $houseName, string $authorUid, int $listId, string $listName): void {
		$this->publish(
			$houseId,
			$houseName,
			$authorUid,
			self::SUBJECT_LIST_CREATED,
			'list',
			$listId,
			['listId' => $listId, 'listName' => $listName],
			$this->listLink($houseId, $listId),
		);
	}

	public function publishListUpdated(int $houseId, string $houseName, string $authorUid, int $listId, string $listName): void {
		$this->publish(
			$houseId,
			$houseName,
			$authorUid,
			self::SUBJECT_LIST_UPDATED,
			'list',
			$listId,
			['listId' => $listId, 'listName' => $listName],
			$this->listLink($houseId, $listId),
		);
	}

	public function publishListDeleted(int $houseId, string $houseName, string $authorUid, int $listId, string $listName): void {
		$this->publish(
			$houseId,
			$houseName,
			$authorUid,
			self::SUBJECT_LIST_DELETED,
			'list',
			$listId,
			['listId' => $listId, 'listName' => $listName],
			$this->houseLink($houseId, 'lists'),
		);
	}

	public function publishItemAdded(int $houseId, string $houseName, string $authorUid, int $itemId, string $itemName, int $listId, string $listName): void {
		$this->publish(
			$houseId,
			$houseName,
			$authorUid,
			self::SUBJECT_ITEM_ADDED,
			'item',
			$itemId,
			['itemName' => $itemName, 'listId' => $listId, 'listName' => $listName],
			$this->listLink($houseId, $listId),
		);
	}

	public function publishItemUpdated(int $houseId, string $houseName, string $authorUid, int $itemId, string $itemName, int $listId, string $listName): void {
		$this->publish(
			$houseId,
			$houseName,
			$authorUid,
			self::SUBJECT_ITEM_UPDATED,
			'item',
			$itemId,
			['itemName' => $itemName, 'listId' => $listId, 'listName' => $listName],
			$this->listLink($houseId, $listId),
		);
	}

	public function publishItemDone(int $houseId, string $houseName, string $authorUid, int $itemId, string $itemName, int $listId, string $listName): void {
		$this->publish(
			$houseId,
			$houseName,
			$authorUid,
			self::SUBJECT_ITEM_DONE,
			'item',
			$itemId,
			['itemName' => $itemName, 'listId' => $listId, 'listName' => $listName],
			$this->listLink($houseId, $listId),
		);
	}

	public function publishItemReopened(int $houseId, string $houseName, string $authorUid, int $itemId, string $itemName, int $listId, string $listName): void {
		$this->publish(
			$houseId,
			$houseName,
			$authorUid,
			self::SUBJECT_ITEM_REOPENED,
			'item',
			$itemId,
			['itemName' => $itemName, 'listId' => $listId, 'listName' => $listName],
			$this->listLink($houseId, $listId),
		);
	}

	public function publishItemMoved(int $houseId, string $houseName, string $authorUid, int $itemId, string $itemName, int $fromListId, string $fromListName, int $toListId, string $toListName): void {
		$this->publish(
			$houseId,
			$houseName,
			$authorUid,
			self::SUBJECT_ITEM_MOVED,
			'item',
			$itemId,
			[
				'itemName' => $itemName,
				'fromListId' => $fromListId,
				'fromListName' => $fromListName,
				'toListId' => $toListId,
				'toListName' => $toListName,
			],
			$this->listLink($houseId, $toListId),
		);
	}

	public function publishItemDeleted(int $houseId, string $houseName, string $authorUid, int $itemId, string $itemName, int $listId, string $listName): void {
		$this->publish(
			$houseId,
			$houseName,
			$authorUid,
			self::SUBJECT_ITEM_DELETED,
			'item',
			$itemId,
			['itemName' => $itemName, 'listId' => $listId, 'listName' => $listName],
			$this->listLink($houseId, $listId),
		);
	}

	public function publishItemRestored(int $houseId, string $houseName, string $authorUid, int $itemId, string $itemName, int $listId, string $listName): void {
		$this->publish(
			$houseId,
			$houseName,
			$authorUid,
			self::SUBJECT_ITEM_RESTORED,
			'item',
			$itemId,
			['itemName' => $itemName, 'listId' => $listId, 'listName' => $listName],
			$this->listLink($houseId, $listId),
		);
	}

	/**
	 * System event: one or more recurring items were auto-reopened by the
	 * background job because their schedule ticked. No actor.
	 *
	 * @param list<string> $itemNames
	 */
	public function publishItemsRecurred(int $houseId, string $houseName, int $listId, string $listName, array $itemNames): void {
		if (empty($itemNames)) {
			return;
		}
		$this->publish(
			$houseId,
			$houseName,
			'',
			self::SUBJECT_ITEMS_RECURRED,
			'list',
			$listId,
			[
				'listId' => $listId,
				'listName' => $listName,
				'itemNames' => array_values($itemNames),
				'itemCount' => count($itemNames),
			],
			$this->listLink($houseId, $listId),
		);
	}

	public function publishPhotoUploaded(int $houseId, string $houseName, string $authorUid, int $photoId, ?string $caption, ?int $folderId, ?string $folderName): void {
		$this->publish(
			$houseId,
			$houseName,
			$authorUid,
			self::SUBJECT_PHOTO_UPLOADED,
			'photo',
			$photoId,
			[
				'photoId' => $photoId,
				'caption' => $caption,
				'folderId' => $folderId,
				'folderName' => $folderName,
			],
			$folderId !== null ? $this->folderLink($houseId, $folderId) : $this->houseLink($houseId, 'photos'),
		);
	}

	public function publishPhotoMoved(int $houseId, string $houseName, string $authorUid, int $photoId, ?string $caption, ?int $fromFolderId, ?string $fromFolderName, ?int $toFolderId, ?string $toFolderName): void {
		$this->publish(
			$houseId,
			$houseName,
			$authorUid,
			self::SUBJECT_PHOTO_MOVED,
			'photo',
			$photoId,
			[
				'photoId' => $photoId,
				'caption' => $caption,
				'fromFolderId' => $fromFolderId,
				'fromFolderName' => $fromFolderName,
				'toFolderId' => $toFolderId,
				'toFolderName' => $toFolderName,
			],
			$toFolderId !== null ? $this->folderLink($houseId, $toFolderId) : $this->houseLink($houseId, 'photos'),
		);
	}

	public function publishPhotoDeleted(int $houseId, string $houseName, string $authorUid, int $photoId, ?string $caption, ?int $folderId, ?string $folderName): void {
		$this->publish(
			$houseId,
			$houseName,
			$authorUid,
			self::SUBJECT_PHOTO_DELETED,
			'photo',
			$photoId,
			[
				'photoId' => $photoId,
				'caption' => $caption,
				'folderId' => $folderId,
				'folderName' => $folderName,
			],
			$this->houseLink($houseId, 'photos'),
		);
	}

	public function publishFolderCreated(int $houseId, string $houseName, string $authorUid, int $folderId, string $folderName): void {
		$this->publish(
			$houseId,
			$houseName,
			$authorUid,
			self::SUBJECT_FOLDER_CREATED,
			'folder',
			$folderId,
			['folderId' => $folderId, 'folderName' => $folderName],
			$this->folderLink($houseId, $folderId),
		);
	}

	public function publishFolderRenamed(int $houseId, string $houseName, string $authorUid, int $folderId, string $oldName, string $newName): void {
		$this->publish(
			$houseId,
			$houseName,
			$authorUid,
			self::SUBJECT_FOLDER_RENAMED,
			'folder',
			$folderId,
			['folderId' => $folderId, 'oldName' => $oldName, 'folderName' => $newName],
			$this->folderLink($houseId, $folderId),
		);
	}

	public function publishFolderDeleted(int $houseId, string $houseName, string $authorUid, int $folderId, string $folderName): void {
		$this->publish(
			$houseId,
			$houseName,
			$authorUid,
			self::SUBJECT_FOLDER_DELETED,
			'folder',
			$folderId,
			['folderId' => $folderId, 'folderName' => $folderName],
			$this->houseLink($houseId, 'photos'),
		);
	}

	public function publishNoteCreated(int $houseId, string $houseName, string $authorUid, int $noteId, string $noteTitle): void {
		$this->publish(
			$houseId,
			$houseName,
			$authorUid,
			self::SUBJECT_NOTE_CREATED,
			'note',
			$noteId,
			['noteId' => $noteId, 'noteTitle' => $noteTitle],
			$this->houseLink($houseId, 'notes'),
		);
	}

	public function publishNoteEdited(int $houseId, string $houseName, string $authorUid, int $noteId, string $noteTitle): void {
		$this->publish(
			$houseId,
			$houseName,
			$authorUid,
			self::SUBJECT_NOTE_EDITED,
			'note',
			$noteId,
			['noteId' => $noteId, 'noteTitle' => $noteTitle],
			$this->houseLink($houseId, 'notes'),
		);
	}

	public function publishNoteDeleted(int $houseId, string $houseName, string $authorUid, int $noteId, string $noteTitle): void {
		$this->publish(
			$houseId,
			$houseName,
			$authorUid,
			self::SUBJECT_NOTE_DELETED,
			'note',
			$noteId,
			['noteId' => $noteId, 'noteTitle' => $noteTitle],
			$this->houseLink($houseId, 'notes'),
		);
	}

	/**
	 * @param array<string, mixed> $extraParams
	 */
	private function publish(
		int $houseId,
		string $houseName,
		string $authorUid,
		string $subject,
		string $objectType,
		int $objectId,
		array $extraParams,
		string $link,
	): void {
		$members = $this->memberMapper->findByHouse($houseId);
		if (empty($members)) {
			return;
		}

		$baseParams = array_merge(
			[
				'author' => $authorUid,
				'houseId' => $houseId,
				'houseName' => $houseName,
			],
			$extraParams,
		);

		$icon = $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
		);

		foreach ($members as $member) {
			$uid = $member->getUserId();
			try {
				$event = $this->activityManager->generateEvent();
				$event->setApp(Application::APP_ID)
					->setType(self::TYPE)
					->setAuthor($authorUid)
					->setAffectedUser($uid)
					->setTimestamp(time())
					->setSubject($subject, $baseParams)
					->setObject($objectType, $objectId)
					->setLink($link)
					->setIcon($icon);
				$this->activityManager->publish($event);
			} catch (\Throwable $e) {
				$this->logger->error('Pantry activity: failed to publish {subject} to {uid}: {msg}', [
					'subject' => $subject,
					'uid' => $uid,
					'msg' => $e->getMessage(),
					'exception' => $e,
				]);
			}
		}
	}

	private function houseLink(int $houseId, string $section): string {
		return $this->urlGenerator->linkToRouteAbsolute('pantry.page.index')
			. '/houses/' . $houseId . '/' . $section;
	}

	private function listLink(int $houseId, int $listId): string {
		return $this->urlGenerator->linkToRouteAbsolute('pantry.page.index')
			. '/houses/' . $houseId . '/lists/' . $listId;
	}

	private function folderLink(int $houseId, int $folderId): string {
		return $this->urlGenerator->linkToRouteAbsolute('pantry.page.index')
			. '/houses/' . $houseId . '/photos/folders/' . $folderId;
	}
}
