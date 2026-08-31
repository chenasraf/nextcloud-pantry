<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Db\FieldValueMapper;
use OCA\Pantry\Db\HouseMember;
use OCA\Pantry\Db\HouseMemberMapper;
use OCA\Pantry\Service\CustomFieldReminderService;
use OCA\Pantry\Service\PermissionService;
use OCP\IURLGenerator;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CustomFieldReminderServiceTest extends TestCase {
	/** @var FieldValueMapper&MockObject */
	private FieldValueMapper $valueMapper;
	/** @var HouseMemberMapper&MockObject */
	private HouseMemberMapper $memberMapper;
	/** @var PermissionService&MockObject */
	private PermissionService $permissions;
	/** @var INotificationManager&MockObject */
	private INotificationManager $notifManager;
	private CustomFieldReminderService $svc;

	protected function setUp(): void {
		$this->valueMapper = $this->createMock(FieldValueMapper::class);
		$this->memberMapper = $this->createMock(HouseMemberMapper::class);
		$this->permissions = $this->createMock(PermissionService::class);
		$this->notifManager = $this->createMock(INotificationManager::class);
		$url = $this->createMock(IURLGenerator::class);
		$url->method('linkToRouteAbsolute')->willReturn('https://example.com/apps/pantry/');
		$url->method('imagePath')->willReturn('/apps/pantry/img/app-dark.svg');
		$url->method('getAbsoluteURL')->willReturn('https://example.com/img.svg');

		$this->svc = new CustomFieldReminderService(
			$this->valueMapper,
			$this->memberMapper,
			$this->permissions,
			$this->notifManager,
			$url,
			$this->createMock(LoggerInterface::class),
		);
	}

	/** A fluent INotification mock whose setters return itself. */
	private function notification(): INotification {
		$n = $this->createMock(INotification::class);
		foreach (['setApp', 'setUser', 'setDateTime', 'setObject', 'setSubject', 'setLink', 'setIcon'] as $m) {
			$n->method($m)->willReturnSelf();
		}
		return $n;
	}

	private function member(string $uid): HouseMember {
		$m = new HouseMember();
		$m->setUserId($uid);
		$m->setHouseId(1);
		$m->setRole('member');
		$m->setJoinedAt(1000);
		return $m;
	}

	/**
	 * A candidate that is enabled by the field default and already due.
	 *
	 * @param array<string, mixed> $overrides
	 */
	private function due(array $overrides = []): array {
		return array_merge([
			'itemId' => 7,
			'fieldId' => 3,
			'valueDate' => 1_700_000_000,
			'listId' => 5,
			'houseId' => 1,
			'itemName' => 'Oat milk',
			'fieldName' => 'Buy before',
			'overridePolicy' => 'field-only',
			'notifyDefault' => true,
			'leadDays' => 0,
			'notifyOverride' => false,
			'notifyEnabled' => false,
			'notifyLeadDays' => null,
		], $overrides);
	}

	public function testSendsToViewMembersAndStamps(): void {
		$this->valueMapper->method('findReminderCandidates')->willReturn([$this->due()]);
		$this->memberMapper->method('findByHouse')->willReturn([
			$this->member('alice'),
			$this->member('bob'),
		]);
		// Alice can view the list; Bob cannot.
		$this->permissions->method('can')->willReturnCallback(
			fn (int $h, string $uid, string $cap) => $uid === 'alice',
		);
		$this->permissions->method('canAccessList')->willReturn(true);
		$this->notifManager->method('createNotification')->willReturnCallback(fn () => $this->notification());

		// One withdrawal (re-arm before send) + one push to alice only.
		$this->notifManager->expects($this->once())->method('markProcessed');
		$this->notifManager->expects($this->once())->method('notify');
		$this->valueMapper->expects($this->once())
			->method('stampNotified')
			->with(7, 3, 1_700_000_000);

		$this->assertSame(1, $this->svc->sendDueReminders(1_700_000_000));
	}

	public function testStampsEvenWhenNoRecipients(): void {
		$this->valueMapper->method('findReminderCandidates')->willReturn([$this->due()]);
		$this->memberMapper->method('findByHouse')->willReturn([]);
		$this->notifManager->method('createNotification')->willReturnCallback(fn () => $this->notification());

		$this->notifManager->expects($this->never())->method('notify');
		// Still stamped once so an empty audience does not re-fire every run.
		$this->valueMapper->expects($this->once())->method('stampNotified');

		$this->assertSame(1, $this->svc->sendDueReminders(1_700_000_000));
	}

	public function testNothingDueSendsNothing(): void {
		$this->valueMapper->method('findReminderCandidates')->willReturn([]);
		$this->notifManager->expects($this->never())->method('notify');
		$this->valueMapper->expects($this->never())->method('stampNotified');

		$this->assertSame(0, $this->svc->sendDueReminders(1_700_000_000));
	}

	public function testCandidateNotYetInWindowIsSkipped(): void {
		// value_date is a full day past `now`, lead 0 → the window has not opened.
		$now = 1_700_000_000;
		$this->valueMapper->method('findReminderCandidates')->willReturn([
			$this->due(['valueDate' => $now + 86400, 'leadDays' => 0]),
		]);
		$this->notifManager->expects($this->never())->method('notify');
		$this->valueMapper->expects($this->never())->method('stampNotified');

		$this->assertSame(0, $this->svc->sendDueReminders($now));
	}

	public function testItemOverrideOffSuppressesFieldDefault(): void {
		// The field reminds by default, but the item overrides it off.
		$this->valueMapper->method('findReminderCandidates')->willReturn([
			$this->due([
				'overridePolicy' => 'item-override',
				'notifyDefault' => true,
				'notifyOverride' => true,
				'notifyEnabled' => false,
			]),
		]);
		$this->notifManager->expects($this->never())->method('notify');
		$this->valueMapper->expects($this->never())->method('stampNotified');

		$this->assertSame(0, $this->svc->sendDueReminders(1_700_000_000));
	}

	public function testItemOverrideLeadWidensWindow(): void {
		// Field lead 0 would not be due yet, but the item's 7-day lead opens it.
		$now = 1_700_000_000;
		$this->valueMapper->method('findReminderCandidates')->willReturn([
			$this->due([
				'valueDate' => $now + 3 * 86400,
				'overridePolicy' => 'item-override',
				'notifyDefault' => false,
				'leadDays' => 0,
				'notifyOverride' => true,
				'notifyEnabled' => true,
				'notifyLeadDays' => 7,
			]),
		]);
		$this->memberMapper->method('findByHouse')->willReturn([$this->member('alice')]);
		$this->permissions->method('can')->willReturn(true);
		$this->permissions->method('canAccessList')->willReturn(true);
		$this->notifManager->method('createNotification')->willReturnCallback(fn () => $this->notification());

		$this->notifManager->expects($this->once())->method('notify');
		$this->valueMapper->expects($this->once())->method('stampNotified');

		$this->assertSame(1, $this->svc->sendDueReminders($now));
	}

	public function testEffectiveReminderResolution(): void {
		// field-only ignores the item's values.
		$this->assertSame(
			['enabled' => true, 'lead' => 2],
			CustomFieldReminderService::effectiveReminder($this->due([
				'overridePolicy' => 'field-only',
				'notifyDefault' => true,
				'leadDays' => 2,
				'notifyOverride' => true,
				'notifyEnabled' => false,
				'notifyLeadDays' => 9,
			])),
		);
		// item-override + opt-in uses the value's enable + lead.
		$this->assertSame(
			['enabled' => true, 'lead' => 5],
			CustomFieldReminderService::effectiveReminder($this->due([
				'overridePolicy' => 'item-override',
				'notifyDefault' => false,
				'leadDays' => 2,
				'notifyOverride' => true,
				'notifyEnabled' => true,
				'notifyLeadDays' => 5,
			])),
		);
		// item-override opt-in with no per-item lead falls back to the field lead.
		$this->assertSame(
			['enabled' => true, 'lead' => 2],
			CustomFieldReminderService::effectiveReminder($this->due([
				'overridePolicy' => 'item-override',
				'notifyDefault' => false,
				'leadDays' => 2,
				'notifyOverride' => true,
				'notifyEnabled' => true,
				'notifyLeadDays' => null,
			])),
		);
		// item-override without opt-in falls back to the field default.
		$this->assertSame(
			['enabled' => false, 'lead' => 2],
			CustomFieldReminderService::effectiveReminder($this->due([
				'overridePolicy' => 'item-override',
				'notifyDefault' => false,
				'leadDays' => 2,
				'notifyOverride' => false,
				'notifyEnabled' => true,
				'notifyLeadDays' => 5,
			])),
		);
	}

	public function testDoneWithStopWhenDoneWithdrawsThoseFields(): void {
		$this->valueMapper->method('findStopWhenDoneFieldIdsForItem')->with(7)->willReturn([3, 9]);
		$this->notifManager->method('createNotification')->willReturnCallback(fn () => $this->notification());

		$this->notifManager->expects($this->exactly(2))->method('markProcessed');
		// Marking done must not re-arm (no stamp clearing).
		$this->valueMapper->expects($this->never())->method('clearStamp');

		$this->svc->onItemDoneChanged(7, true);
	}

	public function testUndoneReArmsStopWhenDoneFields(): void {
		$this->valueMapper->method('findStopWhenDoneFieldIdsForItem')->with(7)->willReturn([3]);
		$this->notifManager->method('createNotification')->willReturnCallback(fn () => $this->notification());

		$this->valueMapper->expects($this->once())->method('clearStamp')->with(7, [3]);
		$this->notifManager->expects($this->once())->method('markProcessed');

		$this->svc->onItemDoneChanged(7, false);
	}

	public function testDoneWithoutStopWhenDoneFieldsDoesNothing(): void {
		$this->valueMapper->method('findStopWhenDoneFieldIdsForItem')->willReturn([]);
		$this->notifManager->expects($this->never())->method('markProcessed');
		$this->svc->onItemDoneChanged(7, true);
	}

	public function testValuesRearmedWithdrawsEachField(): void {
		$this->notifManager->method('createNotification')->willReturnCallback(fn () => $this->notification());
		$this->notifManager->expects($this->exactly(2))->method('markProcessed');
		$this->svc->onValuesRearmed(7, [3, 4]);
	}

	public function testItemDeletedWithdrawsAllDateFields(): void {
		$this->valueMapper->method('findDateFieldIdsForItem')->with(7)->willReturn([3, 4, 5]);
		$this->notifManager->method('createNotification')->willReturnCallback(fn () => $this->notification());
		$this->notifManager->expects($this->exactly(3))->method('markProcessed');
		$this->svc->onItemDeleted(7);
	}

	public function testFieldInvalidatedWithdrawsAcrossItems(): void {
		$this->valueMapper->method('findItemIdsWithDateValueForField')->with(3)->willReturn([7, 8]);
		$this->notifManager->method('createNotification')->willReturnCallback(fn () => $this->notification());
		$this->notifManager->expects($this->exactly(2))->method('markProcessed');
		$this->svc->onFieldRemindersInvalidated(3);
	}
}
