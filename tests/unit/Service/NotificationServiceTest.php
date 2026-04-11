<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Db\House;
use OCA\Pantry\Db\HouseMember;
use OCA\Pantry\Db\HouseMemberMapper;
use OCA\Pantry\Service\HouseService;
use OCA\Pantry\Service\NotificationService;
use OCA\Pantry\Service\PrefsService;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class NotificationServiceTest extends TestCase {
	/** @var INotificationManager&MockObject */
	private INotificationManager $notifManager;
	/** @var HouseMemberMapper&MockObject */
	private HouseMemberMapper $memberMapper;
	/** @var HouseService&MockObject */
	private HouseService $houseService;
	/** @var PrefsService&MockObject */
	private PrefsService $prefs;
	/** @var IURLGenerator&MockObject */
	private IURLGenerator $urlGenerator;
	/** @var IUserManager&MockObject */
	private IUserManager $userManager;
	/** @var IConfig&MockObject */
	private IConfig $config;
	private NotificationService $svc;

	protected function setUp(): void {
		$this->notifManager = $this->createMock(INotificationManager::class);
		$this->memberMapper = $this->createMock(HouseMemberMapper::class);
		$this->houseService = $this->createMock(HouseService::class);
		$this->prefs = $this->createMock(PrefsService::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->config = $this->createMock(IConfig::class);
		// Default: no stored accumulator state (fresh group each call).
		$this->config->method('getUserValue')->willReturn('');

		$this->svc = new NotificationService(
			$this->notifManager,
			$this->memberMapper,
			$this->houseService,
			$this->prefs,
			$this->urlGenerator,
			$this->userManager,
			$this->config,
			$this->createMock(LoggerInterface::class),
		);

		$this->urlGenerator->method('linkToRouteAbsolute')->willReturn('https://example.com/apps/pantry/');
		$this->urlGenerator->method('imagePath')->willReturn('/apps/pantry/img/app-dark.svg');
		$this->urlGenerator->method('getAbsoluteURL')->willReturn('https://example.com/apps/pantry/img/app-dark.svg');
	}

	private function makeMember(string $userId): HouseMember {
		$m = new HouseMember();
		$m->setUserId($userId);
		$m->setHouseId(1);
		$m->setRole('member');
		$m->setJoinedAt(1000);
		return $m;
	}

	private function makeHouse(int $id = 1, string $name = 'Test House'): House {
		$h = new House();
		$ref = new \ReflectionProperty($h, 'id');
		$ref->setValue($h, $id);
		$h->setName($name);
		$h->setOwnerUid('owner');
		$h->setCreatedAt(1000);
		$h->setUpdatedAt(1000);
		return $h;
	}

	private function makeUser(string $uid, string $displayName): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$user->method('getDisplayName')->willReturn($displayName);
		return $user;
	}

	public function testNotifyPhotoUploadedSendsToOtherMembers(): void {
		$this->memberMapper->method('findByHouse')->willReturn([
			$this->makeMember('alice'),
			$this->makeMember('bob'),
			$this->makeMember('charlie'),
		]);
		$this->houseService->method('get')->willReturn($this->makeHouse());
		$this->userManager->method('get')->willReturn($this->makeUser('alice', 'Alice'));
		$this->prefs->method('getNotificationPref')->willReturn(true);

		$notification = $this->createMock(INotification::class);
		$notification->method('setApp')->willReturnSelf();
		$notification->method('setUser')->willReturnSelf();
		$notification->method('setDateTime')->willReturnSelf();
		$notification->method('setObject')->willReturnSelf();
		$notification->method('setSubject')->willReturnSelf();
		$notification->method('setLink')->willReturnSelf();
		$notification->method('setIcon')->willReturnSelf();

		$this->notifManager->method('createNotification')->willReturn($notification);

		// Should notify bob and charlie (not alice who is the author)
		$this->notifManager->expects($this->exactly(2))->method('notify');

		$this->svc->notifyPhotoUploaded(1, 'alice');
	}

	public function testNotifySkipsAuthor(): void {
		$this->memberMapper->method('findByHouse')->willReturn([
			$this->makeMember('alice'),
		]);

		// Only member is the author — no notifications
		$this->notifManager->expects($this->never())->method('notify');

		$this->svc->notifyPhotoUploaded(1, 'alice');
	}

	public function testNotifyRespectsDisabledPref(): void {
		$this->memberMapper->method('findByHouse')->willReturn([
			$this->makeMember('alice'),
			$this->makeMember('bob'),
		]);
		// bob has photo notifications disabled
		$this->prefs->method('getNotificationPref')->willReturnCallback(
			function (string $uid, int $houseId, string $key): bool {
				return $uid !== 'bob';
			}
		);

		$this->notifManager->expects($this->never())->method('notify');

		$this->svc->notifyPhotoUploaded(1, 'alice');
	}

	public function testNotifyNoteCreatedSendsToMembers(): void {
		$this->memberMapper->method('findByHouse')->willReturn([
			$this->makeMember('alice'),
			$this->makeMember('bob'),
		]);
		$this->houseService->method('get')->willReturn($this->makeHouse());
		$this->userManager->method('get')->willReturn($this->makeUser('alice', 'Alice'));
		$this->prefs->method('getNotificationPref')->willReturn(true);

		$notification = $this->createMock(INotification::class);
		$notification->method('setApp')->willReturnSelf();
		$notification->method('setUser')->willReturnSelf();
		$notification->method('setDateTime')->willReturnSelf();
		$notification->method('setObject')->willReturnSelf();
		$notification->method('setSubject')->willReturnSelf();
		$notification->method('setLink')->willReturnSelf();
		$notification->method('setIcon')->willReturnSelf();

		$this->notifManager->method('createNotification')->willReturn($notification);
		$this->notifManager->expects($this->once())->method('notify');

		$this->svc->notifyNoteCreated(1, 'alice', 42, 'My Note');
	}

	public function testNotifyNoteEditedSendsToMembers(): void {
		$this->memberMapper->method('findByHouse')->willReturn([
			$this->makeMember('alice'),
			$this->makeMember('bob'),
		]);
		$this->houseService->method('get')->willReturn($this->makeHouse());
		$this->userManager->method('get')->willReturn($this->makeUser('alice', 'Alice'));
		$this->prefs->method('getNotificationPref')->willReturn(true);

		$notification = $this->createMock(INotification::class);
		$notification->method('setApp')->willReturnSelf();
		$notification->method('setUser')->willReturnSelf();
		$notification->method('setDateTime')->willReturnSelf();
		$notification->method('setObject')->willReturnSelf();
		$notification->method('setSubject')->willReturnSelf();
		$notification->method('setLink')->willReturnSelf();
		$notification->method('setIcon')->willReturnSelf();

		$this->notifManager->method('createNotification')->willReturn($notification);
		$this->notifManager->expects($this->once())->method('notify');

		$this->svc->notifyNoteEdited(1, 'alice', 42, 'My Note');
	}

	public function testIsNotificationEnabledDelegatesToPrefs(): void {
		$this->prefs->method('getNotificationPref')
			->with('bob', 1, 'notify_photo')
			->willReturn(false);

		$this->assertFalse($this->svc->isNotificationEnabled('bob', 1, 'notify_photo'));
	}
}
