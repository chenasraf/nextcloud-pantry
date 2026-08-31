<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\AppInfo\Application;
use OCA\Pantry\Db\FieldDefinition;
use OCA\Pantry\Db\FieldValueMapper;
use OCA\Pantry\Db\HouseMemberMapper;
use OCP\IURLGenerator;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;

/**
 * Date custom-field reminders. Sends one notification per due date value to the
 * house members who can view the item's list, stamps the value so it fires once,
 * and withdraws stale reminders when a value re-arms, an item is done/deleted, or
 * a field's reminder config changes.
 *
 * Each reminder's notification object is `('cf_reminder', "{itemId}:{fieldId}")`,
 * so withdrawal targets a single value while two date fields on one item stay
 * independent.
 */
class CustomFieldReminderService {
	public const OBJECT_TYPE = 'cf_reminder';
	public const SUBJECT = 'field_reminder';

	public function __construct(
		private FieldValueMapper $valueMapper,
		private HouseMemberMapper $memberMapper,
		private PermissionService $permissions,
		private INotificationManager $notificationManager,
		private IURLGenerator $urlGenerator,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Send every due date reminder and stamp it. Per-recipient push failures are
	 * logged and skipped; the value is stamped once after the fan-out so a partial
	 * failure does not re-fire. Returns the number of values notified.
	 */
	public function sendDueReminders(int $now): int {
		$sent = 0;
		/** @var array<int, string[]> $recipientsByList */
		$recipientsByList = [];

		foreach ($this->valueMapper->findReminderCandidates() as $due) {
			$effective = self::effectiveReminder($due);
			if (!$effective['enabled']) {
				continue;
			}
			// No upper bound on the window, so an overdue value still fires once.
			if ($due['valueDate'] - $effective['lead'] * 86400 > $now) {
				continue;
			}

			$listId = $due['listId'];
			$recipientsByList[$listId] ??= $this->recipientsForList($due['houseId'], $listId);
			$recipients = $recipientsByList[$listId];

			$this->withdraw($due['itemId'], $due['fieldId']);
			foreach ($recipients as $uid) {
				try {
					$this->notify($uid, $due);
				} catch (\Throwable $e) {
					$this->logger->error('Pantry custom-field reminder: failed to notify {uid} for item {item} field {field}: {msg}', [
						'uid' => $uid,
						'item' => $due['itemId'],
						'field' => $due['fieldId'],
						'msg' => $e->getMessage(),
						'exception' => $e,
					]);
				}
			}
			$this->valueMapper->stampNotified($due['itemId'], $due['fieldId'], $due['valueDate']);
			$sent++;
		}

		return $sent;
	}

	/**
	 * Resolve a date value's effective reminder. When the field allows per-item
	 * override and the value opts in, the value's own enable flag and lead-time
	 * (falling back to the field lead) apply; otherwise the field defaults do.
	 *
	 * @param array{overridePolicy: ?string, notifyDefault: bool, leadDays: int, notifyOverride: bool, notifyEnabled: bool, notifyLeadDays: ?int, ...} $c
	 *
	 * @return array{enabled: bool, lead: int}
	 */
	public static function effectiveReminder(array $c): array {
		if ($c['overridePolicy'] === FieldDefinition::OVERRIDE_ITEM && $c['notifyOverride']) {
			return [
				'enabled' => $c['notifyEnabled'],
				'lead' => $c['notifyLeadDays'] ?? $c['leadDays'],
			];
		}
		return ['enabled' => $c['notifyDefault'], 'lead' => $c['leadDays']];
	}

	/**
	 * Withdraw the reminders re-armed by a value write (a date newly set or
	 * changed). The scan re-sends any still due.
	 *
	 * @param int[] $fieldIds
	 */
	public function onValuesRearmed(int $itemId, array $fieldIds): void {
		foreach ($fieldIds as $fieldId) {
			$this->withdraw($itemId, $fieldId);
		}
	}

	/**
	 * React to an item's done-state changing: a `stop_when_done` field's reminder
	 * is withdrawn when the item is done and re-armed when it is un-done.
	 */
	public function onItemDoneChanged(int $itemId, bool $done): void {
		$fieldIds = $this->valueMapper->findStopWhenDoneFieldIdsForItem($itemId);
		if ($fieldIds === []) {
			return;
		}
		if ($done) {
			foreach ($fieldIds as $fieldId) {
				$this->withdraw($itemId, $fieldId);
			}
			return;
		}
		$this->valueMapper->clearStamp($itemId, $fieldIds);
		foreach ($fieldIds as $fieldId) {
			$this->withdraw($itemId, $fieldId);
		}
	}

	/**
	 * Withdraw every reminder of a soft-deleted item.
	 */
	public function onItemDeleted(int $itemId): void {
		foreach ($this->valueMapper->findDateFieldIdsForItem($itemId) as $fieldId) {
			$this->withdraw($itemId, $fieldId);
		}
	}

	/**
	 * Withdraw a field's reminders across every item holding one of its date
	 * values. The scan re-evaluates them, so a lead-time change re-fires only the
	 * values whose window is now open and un-stamped.
	 */
	public function onFieldRemindersInvalidated(int $fieldId): void {
		foreach ($this->valueMapper->findItemIdsWithDateValueForField($fieldId) as $itemId) {
			$this->withdraw($itemId, $fieldId);
		}
	}

	/**
	 * Remove the displayed reminder for one value, for every recipient.
	 */
	private function withdraw(int $itemId, int $fieldId): void {
		$notification = $this->notificationManager->createNotification();
		$notification->setApp(Application::APP_ID)
			->setObject(self::OBJECT_TYPE, $itemId . ':' . $fieldId);
		$this->notificationManager->markProcessed($notification);
	}

	/**
	 * @param array{itemId: int, fieldId: int, valueDate: int, listId: int, houseId: int, itemName: string, fieldName: string, ...} $due
	 */
	private function notify(string $uid, array $due): void {
		$link = $this->urlGenerator->linkToRouteAbsolute('pantry.page.index')
			. '/houses/' . $due['houseId'] . '/lists/' . $due['listId'] . '?item=' . $due['itemId'];
		$iconUrl = $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
		);

		$notification = $this->notificationManager->createNotification();
		$notification->setApp(Application::APP_ID)
			->setUser($uid)
			->setDateTime(new \DateTime())
			->setObject(self::OBJECT_TYPE, $due['itemId'] . ':' . $due['fieldId'])
			->setSubject(self::SUBJECT, [
				'itemId' => $due['itemId'],
				'itemName' => $due['itemName'],
				'fieldId' => $due['fieldId'],
				'fieldName' => $due['fieldName'],
				'date' => $due['valueDate'],
			])
			->setLink($link)
			->setIcon($iconUrl);
		$this->notificationManager->notify($notification);
	}

	/**
	 * House members who can view the given list.
	 *
	 * @return string[]
	 */
	private function recipientsForList(int $houseId, int $listId): array {
		$recipients = [];
		foreach ($this->memberMapper->findByHouse($houseId) as $member) {
			$uid = $member->getUserId();
			if (!$this->permissions->can($houseId, $uid, 'canViewLists')) {
				continue;
			}
			if (!$this->permissions->canAccessList($houseId, $uid, $listId)) {
				continue;
			}
			$recipients[] = $uid;
		}
		return $recipients;
	}
}
