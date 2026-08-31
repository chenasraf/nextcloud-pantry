<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Db\HouseMember;
use OCA\Pantry\Db\HouseMemberMapper;
use OCA\Pantry\Db\FieldValueMapper;
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

	private function due(): array {
		return [
			'itemId' => 7,
			'fieldId' => 3,
			'valueDate' => 1_700_000_000,
			'listId' => 5,
			'houseId' => 1,
			'itemName' => 'Oat milk',
			'fieldName' => 'Buy before',
		];
	}

	public function testSendsToViewMembersAndStamps(): void {
		$this->valueMapper->method('findDueReminders')->willReturn([$this->due()]);
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
		$this->valueMapper->method('findDueReminders')->willReturn([$this->due()]);
		$this->memberMapper->method('findByHouse')->willReturn([]);
		$this->notifManager->method('createNotification')->willReturnCallback(fn () => $this->notification());

		$this->notifManager->expects($this->never())->method('notify');
		// Still stamped once so an empty audience does not re-fire every run.
		$this->valueMapper->expects($this->once())->method('stampNotified');

		$this->assertSame(1, $this->svc->sendDueReminders(1_700_000_000));
	}

	public function testNothingDueSendsNothing(): void {
		$this->valueMapper->method('findDueReminders')->willReturn([]);
		$this->notifManager->expects($this->never())->method('notify');
		$this->valueMapper->expects($this->never())->method('stampNotified');

		$this->assertSame(0, $this->svc->sendDueReminders(1_700_000_000));
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
