<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\AppInfo\Application;
use OCA\Pantry\Service\PrefsService;
use OCP\IConfig;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PrefsServiceTest extends TestCase {
	/** @var IConfig&MockObject */
	private IConfig $config;
	/** @var IL10N&MockObject */
	private IL10N $l;
	private PrefsService $svc;

	protected function setUp(): void {
		$this->config = $this->createMock(IConfig::class);
		$this->l = $this->createMock(IL10N::class);
		$this->l->method('l')->willReturn('1'); // Monday fallback
		$this->svc = new PrefsService($this->config, $this->l);
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

	public function testGetNotificationPrefsReturnsAll(): void {
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
				if ($key === 'notify_item_add_1') {
					return '1';
				}
				if ($key === 'notify_item_recur_1') {
					return '0';
				}
				if ($key === 'notify_item_done_1') {
					return '1';
				}
				return $default;
			}
		);

		$result = $this->svc->getNotificationPrefs('alice', 1);
		$this->assertSame([
			'notifyPhoto' => false,
			'notifyNoteCreate' => true,
			'notifyNoteEdit' => false,
			'notifyItemAdd' => true,
			'notifyItemRecur' => false,
			'notifyItemDone' => true,
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

	// ----- Category spacing -----

	public function testGetCategorySpacingDefaultsToDisabled(): void {
		$this->config->method('getUserValue')
			->with('alice', Application::APP_ID, 'category_spacing', 'disabled')
			->willReturn('disabled');

		$this->assertSame('disabled', $this->svc->getCategorySpacing('alice'));
	}

	public function testGetCategorySpacingReturnsStoredValue(): void {
		$this->config->method('getUserValue')
			->with('alice', Application::APP_ID, 'category_spacing', 'disabled')
			->willReturn('divider');

		$this->assertSame('divider', $this->svc->getCategorySpacing('alice'));
	}

	public function testGetCategorySpacingFallsBackForUnknownValue(): void {
		$this->config->method('getUserValue')
			->with('alice', Application::APP_ID, 'category_spacing', 'disabled')
			->willReturn('bogus');

		$this->assertSame('disabled', $this->svc->getCategorySpacing('alice'));
	}

	public function testSetCategorySpacingStoresValidValue(): void {
		$this->config->expects($this->once())
			->method('setUserValue')
			->with('alice', Application::APP_ID, 'category_spacing', 'spacing');

		$this->assertSame('spacing', $this->svc->setCategorySpacing('alice', 'spacing'));
	}

	public function testSetCategorySpacingFallsBackForUnknownValue(): void {
		$this->config->expects($this->once())
			->method('setUserValue')
			->with('alice', Application::APP_ID, 'category_spacing', 'disabled');

		$this->assertSame('disabled', $this->svc->setCategorySpacing('alice', 'bogus'));
	}

	// ----- Show added-by -----

	public function testGetShowAddedByDefaultsToFalse(): void {
		$this->config->method('getUserValue')
			->with('alice', Application::APP_ID, 'show_added_by_1', '0')
			->willReturn('0');

		$this->assertFalse($this->svc->getShowAddedBy('alice', 1));
	}

	public function testGetShowAddedByReturnsTrueWhenEnabled(): void {
		$this->config->method('getUserValue')
			->with('alice', Application::APP_ID, 'show_added_by_1', '0')
			->willReturn('1');

		$this->assertTrue($this->svc->getShowAddedBy('alice', 1));
	}

	public function testShowAddedByIsScopedPerHouse(): void {
		$this->config->method('getUserValue')->willReturnCallback(
			function (string $uid, string $app, string $key, string $default): string {
				if ($key === 'show_added_by_1') {
					return '1';
				}
				if ($key === 'show_added_by_2') {
					return '0';
				}
				return $default;
			}
		);

		$this->assertTrue($this->svc->getShowAddedBy('alice', 1));
		$this->assertFalse($this->svc->getShowAddedBy('alice', 2));
	}

	public function testSetShowAddedByStoresOne(): void {
		$this->config->expects($this->once())
			->method('setUserValue')
			->with('alice', Application::APP_ID, 'show_added_by_1', '1');

		$this->assertTrue($this->svc->setShowAddedBy('alice', 1, true));
	}

	public function testSetShowAddedByStoresZero(): void {
		$this->config->expects($this->once())
			->method('setUserValue')
			->with('alice', Application::APP_ID, 'show_added_by_3', '0');

		$this->assertFalse($this->svc->setShowAddedBy('alice', 3, false));
	}

	public function testGetAllHousePrefsIncludesShowAddedBy(): void {
		$this->config->method('getUserValue')->willReturnCallback(
			function (string $uid, string $app, string $key, string $default): string {
				if ($key === 'show_added_by_7') {
					return '1';
				}
				return $default;
			}
		);

		$prefs = $this->svc->getAllHousePrefs('alice', 7);
		$this->assertArrayHasKey('showAddedBy', $prefs);
		$this->assertTrue($prefs['showAddedBy']);
	}

	public function testSetHousePrefsAppliesShowAddedBy(): void {
		$this->config->expects($this->once())
			->method('setUserValue')
			->with('alice', Application::APP_ID, 'show_added_by_4', '1');

		$this->svc->setHousePrefs('alice', 4, ['showAddedBy' => true]);
	}

	public function testSetHousePrefsIgnoresShowAddedByWhenNotBool(): void {
		// Patch values that aren't booleans should be skipped rather than coerced.
		$this->config->expects($this->never())
			->method('setUserValue');

		$this->svc->setHousePrefs('alice', 4, ['showAddedBy' => 'yes']);
	}

	// ----- Category sort -----

	public function testGetCategorySortDefaultsToNameAsc(): void {
		$this->config->method('getUserValue')
			->with('alice', Application::APP_ID, 'category_sort_1', 'name_asc')
			->willReturn('name_asc');

		$this->assertSame('name_asc', $this->svc->getCategorySort('alice', 1));
	}

	public function testGetCategorySortReturnsStoredValue(): void {
		$this->config->method('getUserValue')
			->with('alice', Application::APP_ID, 'category_sort_2', 'name_asc')
			->willReturn('custom');

		$this->assertSame('custom', $this->svc->getCategorySort('alice', 2));
	}

	public function testSetCategorySortStoresValidValue(): void {
		$this->config->expects($this->once())
			->method('setUserValue')
			->with('alice', Application::APP_ID, 'category_sort_3', 'custom');

		$this->assertSame('custom', $this->svc->setCategorySort('alice', 3, 'custom'));
	}

	public function testSetCategorySortFallsBackForUnknownValue(): void {
		$this->config->expects($this->once())
			->method('setUserValue')
			->with('alice', Application::APP_ID, 'category_sort_4', 'name_asc');

		$this->assertSame('name_asc', $this->svc->setCategorySort('alice', 4, 'bogus'));
	}

	public function testCategorySortIsScopedPerHouse(): void {
		$this->config->method('getUserValue')->willReturnCallback(
			function (string $uid, string $app, string $key, string $default): string {
				if ($key === 'category_sort_1') {
					return 'custom';
				}
				if ($key === 'category_sort_2') {
					return 'name_desc';
				}
				return $default;
			}
		);

		$this->assertSame('custom', $this->svc->getCategorySort('alice', 1));
		$this->assertSame('name_desc', $this->svc->getCategorySort('alice', 2));
	}

	public function testGetAllHousePrefsIncludesCategorySort(): void {
		$this->config->method('getUserValue')->willReturnCallback(
			function (string $uid, string $app, string $key, string $default): string {
				if ($key === 'category_sort_7') {
					return 'custom';
				}
				return $default;
			}
		);

		$prefs = $this->svc->getAllHousePrefs('alice', 7);
		$this->assertArrayHasKey('categorySort', $prefs);
		$this->assertSame('custom', $prefs['categorySort']);
	}

	public function testSetHousePrefsAppliesCategorySort(): void {
		$this->config->expects($this->once())
			->method('setUserValue')
			->with('alice', Application::APP_ID, 'category_sort_4', 'name_desc');

		$this->svc->setHousePrefs('alice', 4, ['categorySort' => 'name_desc']);
	}
}
