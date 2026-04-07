<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Notification;

use OCA\Pantry\Notification\Notifier;
use OCP\IL10N;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class NotifierTest extends TestCase {
	/** @var IFactory&MockObject */
	private IFactory $l10nFactory;
	private Notifier $notifier;

	protected function setUp(): void {
		$this->l10nFactory = $this->createMock(IFactory::class);
		$l = $this->createMock(IL10N::class);
		// Pass-through translation
		$l->method('t')->willReturnCallback(fn (string $text) => $text);
		$l->method('n')->willReturnCallback(fn (string $s, string $p, int $n) => $n === 1 ? $s : $p);
		$this->l10nFactory->method('get')->willReturn($l);

		$this->notifier = new Notifier($this->l10nFactory);
	}

	private function makeNotification(string $app, string $subject, array $params = []): INotification {
		$n = $this->createMock(INotification::class);
		$n->method('getApp')->willReturn($app);
		$n->method('getSubject')->willReturn($subject);
		$n->method('getSubjectParameters')->willReturn($params);
		$n->method('setRichSubject')->willReturnSelf();
		$n->method('setParsedSubject')->willReturnSelf();

		// Capture richSubject for assertions
		$richSubject = '';
		$richParams = [];
		$n->method('setRichSubject')->willReturnCallback(function (string $s, array $p) use ($n, &$richSubject, &$richParams) {
			$richSubject = $s;
			$richParams = $p;
			return $n;
		});
		$n->method('getRichSubject')->willReturnCallback(function () use (&$richSubject) {
			return $richSubject;
		});
		$n->method('getRichSubjectParameters')->willReturnCallback(function () use (&$richParams) {
			return $richParams;
		});

		return $n;
	}

	public function testGetID(): void {
		$this->assertSame('pantry', $this->notifier->getID());
	}

	public function testGetName(): void {
		$this->assertSame('Pantry', $this->notifier->getName());
	}

	public function testThrowsForWrongApp(): void {
		$n = $this->makeNotification('other_app', 'something');
		$this->expectException(\InvalidArgumentException::class);
		$this->notifier->prepare($n, 'en');
	}

	public function testThrowsForUnknownSubject(): void {
		$n = $this->makeNotification('pantry', 'unknown_subject');
		$this->expectException(\InvalidArgumentException::class);
		$this->notifier->prepare($n, 'en');
	}

	public function testPhotoUploadedSetsRichSubject(): void {
		$n = $this->makeNotification('pantry', 'photo_uploaded', [
			'userId' => 'alice',
			'userDisplayName' => 'Alice',
			'houseId' => 1,
			'houseName' => 'My House',
		]);

		$n->expects($this->once())->method('setRichSubject');
		$n->expects($this->once())->method('setParsedSubject');

		$result = $this->notifier->prepare($n, 'en');
		$this->assertSame($n, $result);
	}

	public function testNoteCreatedSetsRichSubject(): void {
		$n = $this->makeNotification('pantry', 'note_created', [
			'userId' => 'bob',
			'userDisplayName' => 'Bob',
			'houseId' => 1,
			'houseName' => 'My House',
			'noteId' => 42,
			'noteTitle' => 'Shopping List',
		]);

		$n->expects($this->once())->method('setRichSubject');
		$n->expects($this->once())->method('setParsedSubject');

		$result = $this->notifier->prepare($n, 'en');
		$this->assertSame($n, $result);
	}

	public function testNoteEditedSetsRichSubject(): void {
		$n = $this->makeNotification('pantry', 'note_edited', [
			'userId' => 'bob',
			'userDisplayName' => 'Bob',
			'houseId' => 1,
			'houseName' => 'My House',
			'noteId' => 42,
			'noteTitle' => 'Shopping List',
		]);

		$n->expects($this->once())->method('setRichSubject');
		$n->expects($this->once())->method('setParsedSubject');

		$result = $this->notifier->prepare($n, 'en');
		$this->assertSame($n, $result);
	}

	public function testItemAddedSetsRichSubject(): void {
		$n = $this->makeNotification('pantry', 'item_added', [
			'userId' => 'alice',
			'userDisplayName' => 'Alice',
			'houseId' => 1,
			'houseName' => 'My House',
			'itemName' => 'Milk',
			'listName' => 'Groceries',
		]);

		$n->expects($this->once())->method('setRichSubject');
		$n->expects($this->once())->method('setParsedSubject');

		$result = $this->notifier->prepare($n, 'en');
		$this->assertSame($n, $result);
	}

	public function testItemDoneSetsRichSubject(): void {
		$n = $this->makeNotification('pantry', 'item_done', [
			'userId' => 'alice',
			'userDisplayName' => 'Alice',
			'houseId' => 1,
			'houseName' => 'My House',
			'itemName' => 'Milk',
			'listName' => 'Groceries',
		]);

		$n->expects($this->once())->method('setRichSubject');
		$n->expects($this->once())->method('setParsedSubject');

		$result = $this->notifier->prepare($n, 'en');
		$this->assertSame($n, $result);
	}

	public function testItemReminderSetsRichSubject(): void {
		$n = $this->makeNotification('pantry', 'item_reminder', [
			'houseId' => 1,
			'houseName' => 'My House',
			'itemNames' => ['Milk'],
			'itemCount' => 1,
			'listName' => 'Groceries',
		]);

		$n->expects($this->once())->method('setRichSubject');
		$n->expects($this->once())->method('setParsedSubject');

		$result = $this->notifier->prepare($n, 'en');
		$this->assertSame($n, $result);
	}

	public function testItemRecurredSetsRichSubject(): void {
		$n = $this->makeNotification('pantry', 'item_recurred', [
			'houseId' => 1,
			'houseName' => 'My House',
			'itemNames' => ['Milk', 'Eggs'],
			'itemCount' => 2,
			'listName' => 'Groceries',
		]);

		$n->expects($this->once())->method('setRichSubject');
		$n->expects($this->once())->method('setParsedSubject');

		$result = $this->notifier->prepare($n, 'en');
		$this->assertSame($n, $result);
	}

	public function testParsedSubjectReplacesPlaceholders(): void {
		$parsedSubject = '';
		$n = $this->makeNotification('pantry', 'photo_uploaded', [
			'userId' => 'alice',
			'userDisplayName' => 'Alice',
			'houseId' => 1,
			'houseName' => 'My House',
		]);
		$n->method('setParsedSubject')->willReturnCallback(function (string $s) use ($n, &$parsedSubject) {
			$parsedSubject = $s;
			return $n;
		});

		$this->notifier->prepare($n, 'en');

		$this->assertStringContainsString('Alice', $parsedSubject);
		$this->assertStringContainsString('My House', $parsedSubject);
		$this->assertStringNotContainsString('{user}', $parsedSubject);
		$this->assertStringNotContainsString('{house}', $parsedSubject);
	}
}
