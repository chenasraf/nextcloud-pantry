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

class NotificationService {
	public const PREF_NOTIFY_PHOTO = 'notify_photo';
	public const PREF_NOTIFY_NOTE_CREATE = 'notify_note_create';
	public const PREF_NOTIFY_NOTE_EDIT = 'notify_note_edit';

	public function __construct(
		private INotificationManager $notificationManager,
		private HouseMemberMapper $memberMapper,
		private HouseService $houseService,
		private PrefsService $prefs,
		private IURLGenerator $urlGenerator,
		private IUserManager $userManager,
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

		foreach ($recipients as $uid) {
			$notification = $this->notificationManager->createNotification();
			$notification->setApp(Application::APP_ID)
				->setUser($uid)
				->setDateTime(new \DateTime())
				->setObject($objectType, (string)($params['noteId'] ?? $params['houseId']))
				->setSubject($subject, $params)
				->setLink($link)
				->setIcon($iconUrl);

			$this->notificationManager->notify($notification);
		}
	}

	public function isNotificationEnabled(string $uid, int $houseId, string $prefKey): bool {
		return $this->prefs->getNotificationPref($uid, $houseId, $prefKey);
	}
}
