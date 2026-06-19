<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Activity;

use OCA\Pantry\Activity\ActivityPublisher;
use OCA\Pantry\Db\HouseMember;
use OCA\Pantry\Db\HouseMemberMapper;
use OCP\Activity\IEvent;
use OCP\Activity\IManager as IActivityManager;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ActivityPublisherTest extends TestCase {
	/** @var IActivityManager&MockObject */
	private IActivityManager $activityManager;
	/** @var HouseMemberMapper&MockObject */
	private HouseMemberMapper $memberMapper;
	/** @var IURLGenerator&MockObject */
	private IURLGenerator $urlGenerator;
	private ActivityPublisher $publisher;

	protected function setUp(): void {
		$this->activityManager = $this->createMock(IActivityManager::class);
		$this->memberMapper = $this->createMock(HouseMemberMapper::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->urlGenerator->method('linkToRouteAbsolute')->willReturn('https://example.com/apps/pantry/');
		$this->urlGenerator->method('imagePath')->willReturn('/apps/pantry/img/app-dark.svg');
		$this->urlGenerator->method('getAbsoluteURL')->willReturnArgument(0);

		$this->publisher = new ActivityPublisher(
			$this->activityManager,
			$this->memberMapper,
			$this->urlGenerator,
			$this->createMock(LoggerInterface::class),
		);
	}

	private function makeMember(string $uid): HouseMember {
		$m = new HouseMember();
		$m->setUserId($uid);
		$m->setHouseId(1);
		$m->setRole('member');
		$m->setJoinedAt(1000);
		return $m;
	}

	/**
	 * Returns an IEvent mock whose every setter returns $this so the
	 * publisher's fluent chain works.
	 *
	 * @return IEvent&MockObject
	 */
	private function makeEvent(): IEvent {
		$e = $this->createMock(IEvent::class);
		foreach ([
			'setApp', 'setType', 'setAuthor', 'setAffectedUser', 'setTimestamp',
			'setSubject', 'setObject', 'setLink', 'setIcon', 'setGenerateNotification',
		] as $method) {
			$e->method($method)->willReturnSelf();
		}
		return $e;
	}

	public function testPublishesToEveryHouseMemberIncludingAuthor(): void {
		$this->memberMapper->method('findByHouse')->willReturn([
			$this->makeMember('alice'),
			$this->makeMember('bob'),
			$this->makeMember('charlie'),
		]);
		$this->activityManager->method('generateEvent')->willReturnCallback(fn () => $this->makeEvent());

		$this->activityManager->expects($this->exactly(3))->method('publish');

		$this->publisher->publishListCreated(1, 'My House', 'alice', 42, 'Groceries');
	}

	public function testNoMembersPublishesNothing(): void {
		$this->memberMapper->method('findByHouse')->willReturn([]);
		$this->activityManager->expects($this->never())->method('publish');

		$this->publisher->publishListCreated(1, 'My House', 'alice', 42, 'Groceries');
	}

	public function testAuthorIsSetOnEvent(): void {
		$this->memberMapper->method('findByHouse')->willReturn([$this->makeMember('alice')]);
		$event = $this->makeEvent();
		$event->expects($this->once())->method('setAuthor')->with('alice')->willReturnSelf();
		$event->expects($this->once())->method('setAffectedUser')->with('alice')->willReturnSelf();
		$this->activityManager->method('generateEvent')->willReturn($event);

		$this->publisher->publishNoteCreated(1, 'My House', 'alice', 10, 'Hello');
	}

	public function testItemsRecurredHasNoAuthor(): void {
		$this->memberMapper->method('findByHouse')->willReturn([$this->makeMember('alice')]);
		$event = $this->makeEvent();
		$event->expects($this->once())->method('setAuthor')->with('')->willReturnSelf();
		$this->activityManager->method('generateEvent')->willReturn($event);

		$this->publisher->publishItemsRecurred(1, 'My House', 7, 'Groceries', ['Milk', 'Eggs']);
	}

	public function testItemsRecurredSkipsWhenEmpty(): void {
		$this->memberMapper->expects($this->never())->method('findByHouse');
		$this->activityManager->expects($this->never())->method('generateEvent');

		$this->publisher->publishItemsRecurred(1, 'My House', 7, 'Groceries', []);
	}

	public function testErrorInOneMemberDoesNotBreakOthers(): void {
		$this->memberMapper->method('findByHouse')->willReturn([
			$this->makeMember('alice'),
			$this->makeMember('bob'),
		]);

		$call = 0;
		$this->activityManager->method('generateEvent')->willReturnCallback(function () use (&$call) {
			$call++;
			if ($call === 1) {
				throw new \RuntimeException('boom');
			}
			return $this->makeEvent();
		});

		// First member throws on generateEvent → caught; second still publishes.
		$this->activityManager->expects($this->once())->method('publish');

		$this->publisher->publishListCreated(1, 'My House', 'system', 42, 'Groceries');
	}

	public function testItemCopiedSendsBothListsInParameters(): void {
		$this->memberMapper->method('findByHouse')->willReturn([$this->makeMember('alice')]);

		$captured = null;
		$event = $this->makeEvent();
		$event->method('setSubject')->willReturnCallback(function (string $subject, array $params) use ($event, &$captured) {
			$captured = ['subject' => $subject, 'params' => $params];
			return $event;
		});
		$this->activityManager->method('generateEvent')->willReturn($event);

		$this->publisher->publishItemCopied(1, 'My House', 'alice', 99, 'Milk', 7, 'Groceries', 8, 'Pharmacy');

		$this->assertSame('item_copied', $captured['subject']);
		$this->assertSame('Groceries', $captured['params']['fromListName']);
		$this->assertSame('Pharmacy', $captured['params']['toListName']);
		$this->assertSame(7, $captured['params']['fromListId']);
		$this->assertSame(8, $captured['params']['toListId']);
		$this->assertSame('Milk', $captured['params']['itemName']);
	}

	public function testItemAddedSuppressesActivityBellNotification(): void {
		// Item adds are covered by a curated, aggregated Pantry bell
		// notification, so the activity → notification bridge must be off
		// for this subject to avoid duplicate pushes.
		$this->memberMapper->method('findByHouse')->willReturn([$this->makeMember('alice')]);
		$event = $this->makeEvent();
		$event->expects($this->once())->method('setGenerateNotification')->with(false)->willReturnSelf();
		$this->activityManager->method('generateEvent')->willReturn($event);

		$this->publisher->publishItemAdded(1, 'My House', 'alice', 99, 'Milk', 7, 'Groceries');
	}

	public function testItemUpdatedKeepsActivityBellNotification(): void {
		$this->memberMapper->method('findByHouse')->willReturn([$this->makeMember('alice')]);
		$event = $this->makeEvent();
		$event->expects($this->once())->method('setGenerateNotification')->with(true)->willReturnSelf();
		$this->activityManager->method('generateEvent')->willReturn($event);

		$this->publisher->publishItemUpdated(1, 'My House', 'alice', 99, 'Milk', 7, 'Groceries');
	}

	public function testItemMovedSendsBothListsInParameters(): void {
		$this->memberMapper->method('findByHouse')->willReturn([$this->makeMember('alice')]);

		$captured = null;
		$event = $this->makeEvent();
		$event->method('setSubject')->willReturnCallback(function (string $subject, array $params) use ($event, &$captured) {
			$captured = ['subject' => $subject, 'params' => $params];
			return $event;
		});
		$this->activityManager->method('generateEvent')->willReturn($event);

		$this->publisher->publishItemMoved(1, 'My House', 'alice', 99, 'Milk', 7, 'Groceries', 8, 'Pharmacy');

		$this->assertSame('item_moved', $captured['subject']);
		$this->assertSame('Groceries', $captured['params']['fromListName']);
		$this->assertSame('Pharmacy', $captured['params']['toListName']);
		$this->assertSame(7, $captured['params']['fromListId']);
		$this->assertSame(8, $captured['params']['toListId']);
	}
}
