<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Activity;

use OCA\Pantry\Activity\ActivityPublisher;
use OCA\Pantry\Activity\Provider;
use OCP\Activity\IEvent;
use OCP\Activity\IManager as IActivityManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProviderTest extends TestCase {
	/** @var IFactory&MockObject */
	private IFactory $l10nFactory;
	/** @var IURLGenerator&MockObject */
	private IURLGenerator $urlGenerator;
	/** @var IUserManager&MockObject */
	private IUserManager $userManager;
	/** @var IActivityManager&MockObject */
	private IActivityManager $activityManager;
	private Provider $provider;

	protected function setUp(): void {
		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->activityManager = $this->createMock(IActivityManager::class);

		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(fn (string $text) => $text);
		$l->method('n')->willReturnCallback(fn (string $s, string $p, int $n) => $n === 1 ? $s : $p);
		$this->l10nFactory->method('get')->willReturn($l);

		$this->urlGenerator->method('imagePath')->willReturn('/apps/pantry/img/app-dark.svg');
		$this->urlGenerator->method('getAbsoluteURL')->willReturnArgument(0);

		$this->userManager->method('getDisplayName')->willReturnCallback(
			fn (string $uid): ?string => $uid === 'alice' ? 'Alice' : ($uid === 'bob' ? 'Bob' : null),
		);

		$this->provider = new Provider(
			$this->l10nFactory,
			$this->urlGenerator,
			$this->userManager,
			$this->activityManager,
		);
	}

	/**
	 * Capturing IEvent mock that records setRichSubject / setParsedSubject calls.
	 *
	 * @return array{0: IEvent&MockObject, 1: \ArrayObject}
	 */
	private function makeEvent(string $app, string $subject, array $params): array {
		$captured = new \ArrayObject(['rich' => '', 'richParams' => [], 'parsed' => '']);
		$event = $this->createMock(IEvent::class);
		$event->method('getApp')->willReturn($app);
		$event->method('getSubject')->willReturn($subject);
		$event->method('getSubjectParameters')->willReturn($params);
		$event->method('setIcon')->willReturnSelf();
		$event->method('setRichSubject')->willReturnCallback(function (string $s, array $p) use ($event, $captured) {
			$captured['rich'] = $s;
			$captured['richParams'] = $p;
			return $event;
		});
		$event->method('setParsedSubject')->willReturnCallback(function (string $s) use ($event, $captured) {
			$captured['parsed'] = $s;
			return $event;
		});
		return [$event, $captured];
	}

	public function testThrowsForWrongApp(): void {
		[$event] = $this->makeEvent('other', 'list_created', []);
		$this->expectException(\InvalidArgumentException::class);
		$this->provider->parse('en', $event);
	}

	public function testThrowsForUnknownSubject(): void {
		[$event] = $this->makeEvent('pantry', 'mystery', []);
		$this->expectException(\InvalidArgumentException::class);
		$this->provider->parse('en', $event);
	}

	public function testListCreatedRendersOtherAuthorVariant(): void {
		[$event, $captured] = $this->makeEvent('pantry', ActivityPublisher::SUBJECT_LIST_CREATED, [
			'author' => 'alice',
			'houseId' => 1,
			'houseName' => 'My House',
			'listId' => 7,
			'listName' => 'Groceries',
		]);
		$this->activityManager->method('getCurrentUserId')->willReturn('bob');

		$this->provider->parse('en', $event);

		$this->assertSame('{author} created list {list} in {house}', $captured['rich']);
		$this->assertArrayHasKey('author', $captured['richParams']);
		$this->assertSame('Alice', $captured['richParams']['author']['name']);
		$this->assertSame('Groceries', $captured['richParams']['list']['name']);
		$this->assertSame('My House', $captured['richParams']['house']['name']);
	}

	public function testListCreatedRendersSelfVariant(): void {
		[$event, $captured] = $this->makeEvent('pantry', ActivityPublisher::SUBJECT_LIST_CREATED, [
			'author' => 'alice',
			'houseId' => 1,
			'houseName' => 'My House',
			'listId' => 7,
			'listName' => 'Groceries',
		]);
		$this->activityManager->method('getCurrentUserId')->willReturn('alice');

		$this->provider->parse('en', $event);

		$this->assertSame('You created list {list} in {house}', $captured['rich']);
		$this->assertArrayNotHasKey('author', $captured['richParams']);
	}

	public function testItemCopiedIncludesBothLists(): void {
		[$event, $captured] = $this->makeEvent('pantry', ActivityPublisher::SUBJECT_ITEM_COPIED, [
			'author' => 'alice',
			'houseId' => 1,
			'houseName' => 'My House',
			'itemName' => 'Milk',
			'fromListId' => 7,
			'fromListName' => 'Groceries',
			'toListId' => 8,
			'toListName' => 'Pharmacy',
		]);
		$this->activityManager->method('getCurrentUserId')->willReturn('bob');

		$this->provider->parse('en', $event);

		$this->assertStringContainsString('copied {item} from {from} to {to}', $captured['rich']);
		$this->assertSame('Groceries', $captured['richParams']['from']['name']);
		$this->assertSame('Pharmacy', $captured['richParams']['to']['name']);
		$this->assertSame('Milk', $captured['richParams']['item']['name']);
	}

	public function testItemCopiedRendersSelfVariant(): void {
		[$event, $captured] = $this->makeEvent('pantry', ActivityPublisher::SUBJECT_ITEM_COPIED, [
			'author' => 'alice',
			'houseId' => 1,
			'houseName' => 'My House',
			'itemName' => 'Milk',
			'fromListId' => 7,
			'fromListName' => 'Groceries',
			'toListId' => 8,
			'toListName' => 'Pharmacy',
		]);
		$this->activityManager->method('getCurrentUserId')->willReturn('alice');

		$this->provider->parse('en', $event);

		$this->assertStringStartsWith('You copied', $captured['rich']);
		$this->assertArrayNotHasKey('author', $captured['richParams']);
	}

	public function testItemMovedIncludesBothLists(): void {
		[$event, $captured] = $this->makeEvent('pantry', ActivityPublisher::SUBJECT_ITEM_MOVED, [
			'author' => 'alice',
			'houseId' => 1,
			'houseName' => 'My House',
			'itemName' => 'Milk',
			'fromListId' => 7,
			'fromListName' => 'Groceries',
			'toListId' => 8,
			'toListName' => 'Pharmacy',
		]);
		$this->activityManager->method('getCurrentUserId')->willReturn('bob');

		$this->provider->parse('en', $event);

		$this->assertStringContainsString('moved {item} from {from} to {to}', $captured['rich']);
		$this->assertSame('Groceries', $captured['richParams']['from']['name']);
		$this->assertSame('Pharmacy', $captured['richParams']['to']['name']);
		$this->assertSame('Milk', $captured['richParams']['item']['name']);
	}

	public function testItemsRecurredRendersNoAuthorWithFewItems(): void {
		[$event, $captured] = $this->makeEvent('pantry', ActivityPublisher::SUBJECT_ITEMS_RECURRED, [
			'author' => '',
			'houseId' => 1,
			'houseName' => 'My House',
			'listId' => 7,
			'listName' => 'Groceries',
			'itemNames' => ['Milk', 'Eggs'],
			'itemCount' => 2,
		]);
		$this->activityManager->method('getCurrentUserId')->willReturn('alice');

		$this->provider->parse('en', $event);

		$this->assertStringNotContainsString('{author}', $captured['rich']);
		$this->assertStringContainsString('{items}', $captured['rich']);
		$this->assertSame('Milk, Eggs', $captured['richParams']['items']['name']);
	}

	public function testItemsRecurredUsesPluralForManyItems(): void {
		[$event, $captured] = $this->makeEvent('pantry', ActivityPublisher::SUBJECT_ITEMS_RECURRED, [
			'author' => '',
			'houseId' => 1,
			'houseName' => 'My House',
			'listId' => 7,
			'listName' => 'Groceries',
			'itemNames' => ['Milk', 'Eggs', 'Bread', 'Cheese'],
			'itemCount' => 4,
		]);
		$this->activityManager->method('getCurrentUserId')->willReturn('alice');

		$this->provider->parse('en', $event);

		$this->assertSame('%n items', $captured['richParams']['items']['name']);
	}

	public function testPhotoUploadedRendersFolderVariant(): void {
		[$event, $captured] = $this->makeEvent('pantry', ActivityPublisher::SUBJECT_PHOTO_UPLOADED, [
			'author' => 'alice',
			'houseId' => 1,
			'houseName' => 'My House',
			'photoId' => 5,
			'caption' => null,
			'folderId' => 3,
			'folderName' => 'Vacation',
		]);
		$this->activityManager->method('getCurrentUserId')->willReturn('bob');

		$this->provider->parse('en', $event);

		$this->assertStringContainsString('photo to {folder}', $captured['rich']);
		$this->assertSame('Vacation', $captured['richParams']['folder']['name']);
	}

	public function testPhotoUploadedRendersRootVariantWhenNoFolder(): void {
		[$event, $captured] = $this->makeEvent('pantry', ActivityPublisher::SUBJECT_PHOTO_UPLOADED, [
			'author' => 'alice',
			'houseId' => 1,
			'houseName' => 'My House',
			'photoId' => 5,
			'caption' => null,
			'folderId' => null,
			'folderName' => null,
		]);
		$this->activityManager->method('getCurrentUserId')->willReturn('bob');

		$this->provider->parse('en', $event);

		$this->assertStringNotContainsString('{folder}', $captured['rich']);
		$this->assertArrayNotHasKey('folder', $captured['richParams']);
	}

	public function testNoteCreatedRendersWithTitle(): void {
		[$event, $captured] = $this->makeEvent('pantry', ActivityPublisher::SUBJECT_NOTE_CREATED, [
			'author' => 'alice',
			'houseId' => 1,
			'houseName' => 'My House',
			'noteId' => 11,
			'noteTitle' => 'Reminder',
		]);
		$this->activityManager->method('getCurrentUserId')->willReturn('bob');

		$this->provider->parse('en', $event);

		$this->assertSame('Reminder', $captured['richParams']['note']['name']);
	}

	public function testParsedSubjectStripsPlaceholders(): void {
		[$event, $captured] = $this->makeEvent('pantry', ActivityPublisher::SUBJECT_NOTE_CREATED, [
			'author' => 'alice',
			'houseId' => 1,
			'houseName' => 'My House',
			'noteId' => 11,
			'noteTitle' => 'Reminder',
		]);
		$this->activityManager->method('getCurrentUserId')->willReturn('bob');

		$this->provider->parse('en', $event);

		$this->assertStringNotContainsString('{', $captured['parsed']);
		$this->assertStringContainsString('Alice', $captured['parsed']);
		$this->assertStringContainsString('Reminder', $captured['parsed']);
		$this->assertStringContainsString('My House', $captured['parsed']);
	}
}
