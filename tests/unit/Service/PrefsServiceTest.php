<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\AppInfo\Application;
use OCA\Pantry\Service\PrefsService;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PrefsServiceTest extends TestCase {
	/** @var IConfig&MockObject */
	private IConfig $config;
	private PrefsService $svc;

	protected function setUp(): void {
		$this->config = $this->createMock(IConfig::class);
		$this->svc = new PrefsService($this->config);
	}

	// ----- Notification preferences -----

	public function testGetNotificationPrefDefaultsToTrue(): void {
		$this->config->method('getUserValue')
			->with('alice', Application::APP_ID, 'notify_photo_1', '1')
			->willReturn('1');

		$this->assertTrue($this->svc->getNotificationPref('alice', 1, 'notify_photo'));
	}

	public function testGetNotificationPrefReturnsFalseWhenDisabled(): void {
		$this->config->method('getUserValue')
			->with('alice', Application::APP_ID, 'notify_photo_1', '1')
			->willReturn('0');

		$this->assertFalse($this->svc->getNotificationPref('alice', 1, 'notify_photo'));
	}

	public function testSetNotificationPrefStoresValue(): void {
		$this->config->expects($this->once())
			->method('setUserValue')
			->with('alice', Application::APP_ID, 'notify_photo_1', '0');

		$this->svc->setNotificationPref('alice', 1, 'notify_photo', false);
	}

	public function testSetNotificationPrefStoresEnabled(): void {
		$this->config->expects($this->once())
			->method('setUserValue')
			->with('bob', Application::APP_ID, 'notify_note_create_5', '1');

		$this->svc->setNotificationPref('bob', 5, 'notify_note_create', true);
	}

	public function testGetNotificationPrefsReturnsAllThree(): void {
		$this->config->method('getUserValue')->willReturnCallback(
			function (string $uid, string $app, string $key, string $default): string {
				if ($key === 'notify_photo_1') {
					return '0';
				}
				if ($key === 'notify_note_create_1') {
					return '1';
				}
				if ($key === 'notify_note_edit_1') {
					return '0';
				}
				return $default;
			}
		);

		$result = $this->svc->getNotificationPrefs('alice', 1);
		$this->assertSame([
			'notifyPhoto' => false,
			'notifyNoteCreate' => true,
			'notifyNoteEdit' => false,
		], $result);
	}

	public function testNotificationPrefIsScopedPerHouse(): void {
		$this->config->method('getUserValue')->willReturnCallback(
			function (string $uid, string $app, string $key, string $default): string {
				if ($key === 'notify_photo_1') {
					return '0';
				}
				if ($key === 'notify_photo_2') {
					return '1';
				}
				return $default;
			}
		);

		$this->assertFalse($this->svc->getNotificationPref('alice', 1, 'notify_photo'));
		$this->assertTrue($this->svc->getNotificationPref('alice', 2, 'notify_photo'));
	}

	// ----- Image folder -----

	public function testGetImageFolderReturnsDefault(): void {
		$this->config->method('getUserValue')
			->willReturn('/Pantry');

		$this->assertSame('/Pantry', $this->svc->getImageFolder('alice', 1));
	}

	public function testSetImageFolderNormalizesPath(): void {
		$this->config->expects($this->once())
			->method('setUserValue')
			->with('alice', Application::APP_ID, 'image_folder_1', '/Photos');

		$result = $this->svc->setImageFolder('alice', 1, 'Photos/');
		$this->assertSame('/Photos', $result);
	}

	// ----- Last house -----

	public function testGetLastHouseIdReturnsNullWhenEmpty(): void {
		$this->config->method('getUserValue')->willReturn('');
		$this->assertNull($this->svc->getLastHouseId('alice'));
	}

	public function testGetLastHouseIdReturnsStoredValue(): void {
		$this->config->method('getUserValue')->willReturn('42');
		$this->assertSame(42, $this->svc->getLastHouseId('alice'));
	}

	public function testSetLastHouseIdDeletesWhenNull(): void {
		$this->config->expects($this->once())
			->method('deleteUserValue')
			->with('alice', Application::APP_ID, 'last_house_id');

		$this->svc->setLastHouseId('alice', null);
	}

	public function testSetLastHouseIdStoresValue(): void {
		$this->config->expects($this->once())
			->method('setUserValue')
			->with('alice', Application::APP_ID, 'last_house_id', '5');

		$this->svc->setLastHouseId('alice', 5);
	}
}
