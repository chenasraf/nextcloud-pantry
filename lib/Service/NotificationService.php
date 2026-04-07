<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\AppInfo\Application;
use OCA\Pantry\Db\HouseMemberMapper;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;

class NotificationService {
	public const PREF_NOTIFY_PHOTO = 'notify_photo';
	public const PREF_NOTIFY_NOTE_CREATE = 'notify_note_create';
	public const PREF_NOTIFY_NOTE_EDIT = 'notify_note_edit';
	public const PREF_NOTIFY_ITEM_ADD = 'notify_item_add';
	public const PREF_NOTIFY_ITEM_RECUR = 'notify_item_recur';
	public const PREF_NOTIFY_ITEM_DONE = 'notify_item_done';

	public function __construct(
		private INotificationManager $notificationManager,
		private HouseMemberMapper $memberMapper,
		private HouseService $houseService,
		private PrefsService $prefs,
		private IURLGenerator $urlGenerator,
		private IUserManager $userManager,
		private LoggerInterface $logger,
	) {
	}

	public function notifyPhotoUploaded(int $houseId, string $authorUid): void {
		$this->sendToHouseMembers($houseId, $authorUid, 'photo_uploaded', 'photo', self::PREF_NOTIFY_PHOTO, function () use ($houseId, $authorUid) {
			$house = $this->houseService->get($houseId);
			$author = $this->userManager->get($authorUid);
			return [
				'userId' => $authorUid,
				'userDisplayName' => $author ? $author->getDisplayName() : $authorUid,
				'houseId' => $houseId,
				'houseName' => $house->getName(),
			];
		});
	}

	public function notifyNoteCreated(int $houseId, string $authorUid, int $noteId, string $noteTitle): void {
		$this->sendToHouseMembers($houseId, $authorUid, 'note_created', 'note', self::PREF_NOTIFY_NOTE_CREATE, function () use ($houseId, $authorUid, $noteId, $noteTitle) {
			$house = $this->houseService->get($houseId);
			$author = $this->userManager->get($authorUid);
			return [
				'userId' => $authorUid,
				'userDisplayName' => $author ? $author->getDisplayName() : $authorUid,
				'houseId' => $houseId,
				'houseName' => $house->getName(),
				'noteId' => $noteId,
				'noteTitle' => $noteTitle,
			];
		});
	}

	public function notifyNoteEdited(int $houseId, string $authorUid, int $noteId, string $noteTitle): void {
		$this->sendToHouseMembers($houseId, $authorUid, 'note_edited', 'note', self::PREF_NOTIFY_NOTE_EDIT, function () use ($houseId, $authorUid, $noteId, $noteTitle) {
			$house = $this->houseService->get($houseId);
			$author = $this->userManager->get($authorUid);
			return [
				'userId' => $authorUid,
				'userDisplayName' => $author ? $author->getDisplayName() : $authorUid,
				'houseId' => $houseId,
				'houseName' => $house->getName(),
				'noteId' => $noteId,
				'noteTitle' => $noteTitle,
			];
		});
	}

	public function notifyItemAdded(int $houseId, string $authorUid, string $itemName, string $listName): void {
		$this->sendToHouseMembers($houseId, $authorUid, 'item_added', 'item', self::PREF_NOTIFY_ITEM_ADD, function () use ($houseId, $authorUid, $itemName, $listName) {
			$house = $this->houseService->get($houseId);
			$author = $this->userManager->get($authorUid);
			return [
				'userId' => $authorUid,
				'userDisplayName' => $author ? $author->getDisplayName() : $authorUid,
				'houseId' => $houseId,
				'houseName' => $house->getName(),
				'itemName' => $itemName,
				'listName' => $listName,
			];
		});
	}

	public function notifyItemDone(int $houseId, string $authorUid, string $itemName, string $listName): void {
		$this->sendToHouseMembers($houseId, $authorUid, 'item_done', 'item', self::PREF_NOTIFY_ITEM_DONE, function () use ($houseId, $authorUid, $itemName, $listName) {
			$house = $this->houseService->get($houseId);
			$author = $this->userManager->get($authorUid);
			return [
				'userId' => $authorUid,
				'userDisplayName' => $author ? $author->getDisplayName() : $authorUid,
				'houseId' => $houseId,
				'houseName' => $house->getName(),
				'itemName' => $itemName,
				'listName' => $listName,
			];
		});
	}

	/**
	 * Notify that undone fixed-schedule items are due again (reminder nudge).
	 *
	 * @param string[] $itemNames Names of the items that are still undone.
	 */
	public function notifyItemsReminder(int $houseId, array $itemNames, string $listName): void {
		if (empty($itemNames)) {
			return;
		}
		$this->sendToHouseMembers($houseId, '', 'item_reminder', 'item', self::PREF_NOTIFY_ITEM_RECUR, function () use ($houseId, $itemNames, $listName) {
			$house = $this->houseService->get($houseId);
			return [
				'houseId' => $houseId,
				'houseName' => $house->getName(),
				'itemNames' => $itemNames,
				'itemCount' => count($itemNames),
				'listName' => $listName,
			];
		});
	}

	/**
	 * @param string[] $itemNames Names of the items that recurred.
	 */
	public function notifyItemsRecurred(int $houseId, array $itemNames, string $listName): void {
		if (empty($itemNames)) {
			return;
		}
		// No author to exclude — this is a system event.
		$this->sendToHouseMembers($houseId, '', 'item_recurred', 'item', self::PREF_NOTIFY_ITEM_RECUR, function () use ($houseId, $itemNames, $listName) {
			$house = $this->houseService->get($houseId);
			return [
				'houseId' => $houseId,
				'houseName' => $house->getName(),
				'itemNames' => $itemNames,
				'itemCount' => count($itemNames),
				'listName' => $listName,
			];
		});
	}

	/**
	 * @param callable():array $paramsFn Lazy parameter builder (only called if at least one member needs notification)
	 */
	private function sendToHouseMembers(int $houseId, string $authorUid, string $subject, string $objectType, string $prefKey, callable $paramsFn): void {
		$members = $this->memberMapper->findByHouse($houseId);

		$recipients = [];
		foreach ($members as $member) {
			$uid = $member->getUserId();
			if ($uid === $authorUid) {
				continue;
			}
			if (!$this->isNotificationEnabled($uid, $houseId, $prefKey)) {
				continue;
			}
			$recipients[] = $uid;
		}

		if (empty($recipients)) {
			return;
		}

		$params = $paramsFn();
		$link = $this->urlGenerator->linkToRouteAbsolute('pantry.page.index');
		$iconUrl = $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
		);

		$objectId = (string)($params['noteId'] ?? $params['houseId']);

		foreach ($recipients as $uid) {
			try {
				// Dismiss any previous notification for the same object so edits
				// don't pile up — only the latest notification is shown.
				$stale = $this->notificationManager->createNotification();
				$stale->setApp(Application::APP_ID)
					->setUser($uid)
					->setObject($objectType, $objectId);
				$this->notificationManager->markProcessed($stale);

				$notification = $this->notificationManager->createNotification();
				$notification->setApp(Application::APP_ID)
					->setUser($uid)
					->setDateTime(new \DateTime())
					->setObject($objectType, $objectId)
					->setSubject($subject, $params)
					->setLink($link)
					->setIcon($iconUrl);

				$this->notificationManager->notify($notification);
			} catch (\Throwable $e) {
				$this->logger->error('Pantry notify: failed to send {subject} to {uid}: {msg}', [
					'subject' => $subject,
					'uid' => $uid,
					'msg' => $e->getMessage(),
					'exception' => $e,
				]);
			}
		}
	}

	public function isNotificationEnabled(string $uid, int $houseId, string $prefKey): bool {
		return $this->prefs->getNotificationPref($uid, $houseId, $prefKey);
	}
}
