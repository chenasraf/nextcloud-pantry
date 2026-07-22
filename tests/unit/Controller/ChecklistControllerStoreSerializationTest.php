<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Controller;

use OCA\Pantry\Activity\ActivityPublisher;
use OCA\Pantry\Controller\ChecklistController;
use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Db\ItemStoreMapper;
use OCA\Pantry\Service\CategoryService;
use OCA\Pantry\Service\ChecklistService;
use OCA\Pantry\Service\HouseAuthService;
use OCA\Pantry\Service\HouseService;
use OCA\Pantry\Service\ImageService;
use OCA\Pantry\Service\NotificationService;
use OCA\Pantry\Service\PermissionService;
use OCA\Pantry\Service\PrefsService;
use OCA\Pantry\Service\ShareService;
use OCA\Pantry\Service\StoreService;
use OCP\IRequest;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Regression coverage for issue #188: a failure loading item-store links (e.g.
 * the item-stores table is missing because its migration did not apply) must
 * not sink the whole item list. Items should still serialize, just without any
 * attached stores.
 */
class ChecklistControllerStoreSerializationTest extends TestCase {
	/** @var ItemStoreMapper&MockObject */
	private ItemStoreMapper $itemStores;
	/** @var LoggerInterface&MockObject */
	private LoggerInterface $logger;
	private ChecklistController $controller;

	protected function setUp(): void {
		$this->itemStores = $this->createMock(ItemStoreMapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->controller = new ChecklistController(
			'pantry',
			$this->createMock(IRequest::class),
			$this->createMock(ChecklistService::class),
			$this->createMock(CategoryService::class),
			$this->createMock(StoreService::class),
			$this->itemStores,
			$this->createMock(HouseAuthService::class),
			$this->createMock(HouseService::class),
			$this->createMock(ImageService::class),
			$this->createMock(NotificationService::class),
			$this->createMock(ActivityPublisher::class),
			$this->createMock(PrefsService::class),
			$this->createMock(PermissionService::class),
			$this->createMock(ShareService::class),
			$this->createMock(IUserSession::class),
			$this->logger,
		);
	}

	private function makeItem(int $id): ChecklistItem {
		$item = new ChecklistItem();
		$item->setId($id);
		$item->setListId(1);
		$item->setName('Item ' . $id);
		return $item;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function serializeItems(array $items): array {
		$m = new \ReflectionMethod($this->controller, 'serializeItems');
		$m->setAccessible(true);
		return $m->invoke($this->controller, $items);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function serializeItem(ChecklistItem $item): array {
		$m = new \ReflectionMethod($this->controller, 'serializeItem');
		$m->setAccessible(true);
		return $m->invoke($this->controller, $item);
	}

	public function testSerializeItemsEmbedsStoreIdsOnSuccess(): void {
		$items = [$this->makeItem(1), $this->makeItem(2)];
		$this->itemStores->method('findStoreIdsForItems')->willReturn([1 => [10, 11]]);

		$out = $this->serializeItems($items);

		$this->assertCount(2, $out);
		$this->assertSame([10, 11], $out[0]['storeIds']);
		// Item with no attached stores gets an empty list, not a missing key.
		$this->assertSame([], $out[1]['storeIds']);
	}

	public function testSerializeItemsFallsBackToEmptyStoresWhenLookupThrows(): void {
		$items = [$this->makeItem(1), $this->makeItem(2)];
		$this->itemStores->method('findStoreIdsForItems')
			->willThrowException(new \RuntimeException('relation "oc_pantry_item_stores" does not exist'));
		$this->logger->expects($this->once())->method('error');

		// The store failure must not propagate — the item list still serializes.
		$out = $this->serializeItems($items);

		$this->assertCount(2, $out);
		$this->assertSame([], $out[0]['storeIds']);
		$this->assertSame([], $out[1]['storeIds']);
		$this->assertSame('Item 1', $out[0]['name']);
	}

	public function testSerializeItemFallsBackToEmptyStoresWhenLookupThrows(): void {
		$this->itemStores->method('findStoreIdsForItem')
			->willThrowException(new \RuntimeException('relation "oc_pantry_item_stores" does not exist'));
		$this->logger->expects($this->once())->method('error');

		$out = $this->serializeItem($this->makeItem(7));

		$this->assertSame([], $out['storeIds']);
		$this->assertSame('Item 7', $out['name']);
	}
}
