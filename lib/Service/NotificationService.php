<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\AppInfo\Application;
use OCA\Pantry\Db\HouseMemberMapper;
use OCP\IConfig;
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

	/**
	 * If two events of the same kind happen within this window, they are
	 * grouped into a single notification with an accumulated count and a
	 * short list of samples. After this window the counter resets.
	 */
	private const GROUP_WINDOW_SECONDS = 30 * 60;

	/** Maximum number of sample names stored per group (used for rendering). */
	private const MAX_SAMPLES = 3;

	public function __construct(
		private INotificationManager $notificationManager,
		private HouseMemberMapper $memberMapper,
		private HouseService $houseService,
		private PrefsService $prefs,
		private IURLGenerator $urlGenerator,
		private IUserManager $userManager,
		private IConfig $config,
		private LoggerInterface $logger,
	) {
	}

	public function notifyPhotoUploaded(int $houseId, string $authorUid): void {
		$this->sendAggregated(
			$houseId,
			$authorUid,
			'photo_uploaded',
			'photo',
			'photo:' . $authorUid . ':' . $houseId,
			self::PREF_NOTIFY_PHOTO,
			null,
			function () use ($houseId, $authorUid) {
				$house = $this->houseService->get($houseId);
				$author = $this->userManager->get($authorUid);
				return [
					'userId' => $authorUid,
					'userDisplayName' => $author ? $author->getDisplayName() : $authorUid,
					'houseId' => $houseId,
					'houseName' => $house->getName(),
				];
			},
		);
	}

	public function notifyNoteCreated(int $houseId, string $authorUid, int $noteId, string $noteTitle): void {
		$this->sendAggregated(
			$houseId,
			$authorUid,
			'note_created',
			'note',
			'note_create:' . $authorUid . ':' . $houseId,
			self::PREF_NOTIFY_NOTE_CREATE,
			$noteTitle,
			function () use ($houseId, $authorUid, $noteId, $noteTitle) {
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
			},
		);
	}

	public function notifyNoteEdited(int $houseId, string $authorUid, int $noteId, string $noteTitle): void {
		$this->sendAggregated(
			$houseId,
			$authorUid,
			'note_edited',
			'note',
			'note_edit:' . $authorUid . ':' . $houseId,
			self::PREF_NOTIFY_NOTE_EDIT,
			$noteTitle,
			function () use ($houseId, $authorUid, $noteId, $noteTitle) {
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
			},
		);
	}

	public function notifyItemAdded(int $houseId, string $authorUid, string $itemName, string $listName): void {
		$this->sendAggregated(
			$houseId,
			$authorUid,
			'item_added',
			'item',
			'item_add:' . $authorUid . ':' . $houseId . ':' . $listName,
			self::PREF_NOTIFY_ITEM_ADD,
			$itemName,
			function () use ($houseId, $authorUid, $itemName, $listName) {
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
			},
		);
	}

	public function notifyItemDone(int $houseId, string $authorUid, string $itemName, string $listName): void {
		$this->sendAggregated(
			$houseId,
			$authorUid,
			'item_done',
			'item',
			'item_done:' . $authorUid . ':' . $houseId . ':' . $listName,
			self::PREF_NOTIFY_ITEM_DONE,
			$itemName,
			function () use ($houseId, $authorUid, $itemName, $listName) {
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
			},
		);
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
	 * Send a notification that is automatically aggregated with other events
	 * in the same scope within GROUP_WINDOW_SECONDS.
	 *
	 * Each recipient has an independent accumulator stored in their user
	 * config (`pantry:notif_state_{hash}`). When a new event arrives we:
	 *   1. load the accumulator
	 *   2. if stale (> window), reset count/samples
	 *   3. increment count, append the sample name
	 *   4. save the accumulator
	 *   5. dismiss the old notification for the same object
	 *   6. create a new notification with `count` and `samples` in params
	 *
	 * The Notifier uses those params to render a singular or plural subject.
	 *
	 * @param string $scope A stable identifier for the event group (must
	 *                      include author + house + any other context like
	 *                      list name that should NOT cross-contaminate).
	 * @param string|null $sample Human-friendly label for the individual
	 *                            event (item/note title). null for photos.
	 * @param callable():array $paramsFn Lazy parameter builder (only called
	 *                                   if at least one recipient needs it).
	 */
	private function sendAggregated(
		int $houseId,
		string $authorUid,
		string $subject,
		string $objectType,
		string $scope,
		string $prefKey,
		?string $sample,
		callable $paramsFn,
	): void {
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

		$baseParams = $paramsFn();
		$link = $this->urlGenerator->linkToRouteAbsolute('pantry.page.index');
		$iconUrl = $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
		);

		// Use a hashed object id so markProcessed matches the same group.
		$objectId = substr(md5($scope), 0, 32);
		$stateKey = 'notif_state_' . substr(md5($scope), 0, 32);
		$now = time();

		foreach ($recipients as $uid) {
			try {
				// Load accumulator state for this recipient.
				$raw = $this->config->getUserValue($uid, Application::APP_ID, $stateKey, '');
				$state = $this->decodeState($raw, $now);

				// Increment count and update samples (keeping latest MAX_SAMPLES).
				$state['count'] = ($state['count'] ?? 0) + 1;
				$state['lastTs'] = $now;
				$samples = $state['samples'] ?? [];
				if ($sample !== null && $sample !== '') {
					// Keep unique, most-recent first.
					$samples = array_values(array_filter($samples, fn ($s) => $s !== $sample));
					array_unshift($samples, $sample);
					if (count($samples) > self::MAX_SAMPLES) {
						$samples = array_slice($samples, 0, self::MAX_SAMPLES);
					}
				}
				$state['samples'] = $samples;

				$this->config->setUserValue(
					$uid,
					Application::APP_ID,
					$stateKey,
					json_encode($state),
				);

				// Build final subject params: merge base params with aggregation data.
				$params = array_merge($baseParams, [
					'count' => (int)$state['count'],
					'samples' => $state['samples'],
				]);

				// Dismiss any previous notification for the same object group.
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

	/**
	 * Decode an accumulator state, resetting it if it is older than the
	 * grouping window (so a long pause between events starts a new group).
	 *
	 * @return array{count: int, lastTs: int, samples: list<string>}
	 */
	private function decodeState(string $raw, int $now): array {
		$empty = ['count' => 0, 'lastTs' => 0, 'samples' => []];
		if ($raw === '') {
			return $empty;
		}
		$decoded = json_decode($raw, true);
		if (!is_array($decoded)) {
			return $empty;
		}
		$lastTs = (int)($decoded['lastTs'] ?? 0);
		if ($lastTs === 0 || ($now - $lastTs) > self::GROUP_WINDOW_SECONDS) {
			return $empty;
		}
		return [
			'count' => (int)($decoded['count'] ?? 0),
			'lastTs' => $lastTs,
			'samples' => array_values(array_filter(
				(array)($decoded['samples'] ?? []),
				fn ($v) => is_string($v),
			)),
		];
	}

	public function isNotificationEnabled(string $uid, int $houseId, string $prefKey): bool {
		return $this->prefs->getNotificationPref($uid, $houseId, $prefKey);
	}
}
