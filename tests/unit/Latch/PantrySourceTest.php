<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Latch;

use Latch\Integration\Nextcloud\LatchBootstrap;
use OCA\Pantry\Db\Checklist;
use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Db\ChecklistItemMapper;
use OCA\Pantry\Db\ChecklistMapper;
use OCA\Pantry\Latch\HookPoints;
use OCA\Pantry\Latch\PantrySource;
use OCA\Pantry\Latch\Payload\CategorySuggestionContext;
use OCA\Pantry\Latch\Payload\ChecklistItemEventPayload;
use OCA\Pantry\Latch\Payload\ChecklistItemPayload;
use OCA\Pantry\Latch\Payload\ListItemsCollectContext;
use OCA\Pantry\Service\ChecklistService;
use OCA\Pantry\Service\RecurrenceService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end Latch behavior driven through ChecklistService:
 *
 *  - filter chains transform payloads in priority order
 *  - actions fire after persistence and carry the saved entity
 *  - collectors merge contributions into the response
 */
class PantrySourceTest extends TestCase {
	private ChecklistMapper $listMapper;
	private ChecklistItemMapper $itemMapper;
	private PantrySource $hooks;
	private ChecklistService $svc;

	protected function setUp(): void {
		LatchBootstrap::reset();
		$this->listMapper = $this->createMock(ChecklistMapper::class);
		$this->itemMapper = $this->createMock(ChecklistItemMapper::class);
		$this->hooks = new PantrySource();
		$this->svc = new ChecklistService(
			$this->listMapper,
			$this->itemMapper,
			new RecurrenceService(),
			$this->hooks,
		);
	}

	protected function tearDown(): void {
		LatchBootstrap::reset();
	}

	private function makeList(int $id = 1, int $houseId = 42): Checklist {
		$list = new Checklist();
		$list->setId($id);
		$list->setHouseId($houseId);
		$list->setName('Pantry');
		return $list;
	}

	public function testItemBeforeCreateFilterChainIsApplied(): void {
		// Two handlers at different priorities mutate the draft name.
		$registry = LatchBootstrap::registry();
		$first = $registry->registerHandler('first');
		$first->hook(HookPoints::SOURCE, HookPoints::FILTER_ITEM_BEFORE_CREATE)
			->priority(10)
			->handle(fn (ChecklistItemPayload $p) => $p->withField('name', '[' . ($p->data['name'] ?? '') . ']'));

		$second = $registry->registerHandler('second');
		$second->hook(HookPoints::SOURCE, HookPoints::FILTER_ITEM_BEFORE_CREATE)
			->priority(20)
			->handle(fn (ChecklistItemPayload $p) => $p->withField('name', ($p->data['name'] ?? '') . '!'));

		$this->listMapper->method('findById')->willReturn($this->makeList());
		$this->itemMapper->method('insert')->willReturnCallback(fn (ChecklistItem $i) => $i);

		$saved = $this->svc->addItem(1, ['name' => 'milk']);

		// priority 10 runs first, then 20: "[milk]" -> "[milk]!"
		$this->assertSame('[milk]!', $saved->getName());
	}

	public function testItemCreatedActionFiresWithSavedEntity(): void {
		$received = null;
		$handler = LatchBootstrap::registry()->registerHandler('observer');
		$handler->hook(HookPoints::SOURCE, HookPoints::ACTION_ITEM_CREATED)
			->handle(function (ChecklistItemEventPayload $p) use (&$received): void {
				$received = $p;
			});

		$this->listMapper->method('findById')->willReturn($this->makeList());
		$this->itemMapper->method('insert')->willReturnCallback(function (ChecklistItem $i) {
			$i->setId(99);
			return $i;
		});

		$this->svc->addItem(1, ['name' => 'eggs'], 'alice');

		$this->assertNotNull($received);
		$this->assertSame('eggs', $received->item->getName());
		$this->assertSame(99, (int)$received->item->getId());
		$this->assertSame('alice', $received->actorUid);
	}

	public function testContributedItemsAreMergedIntoListResponse(): void {
		$handler = LatchBootstrap::registry()->registerHandler('cookbook');
		$handler->hook(HookPoints::SOURCE, HookPoints::COLLECT_LIST_CONTRIBUTED_ITEMS)
			->handle(fn (ListItemsCollectContext $ctx) => [[
				'name' => 'Flour (recipe)',
				'quantity' => '500g',
				'contributedBy' => 'cookbook',
			]]);

		$this->itemMapper->method('findDueRecurring')->willReturn([]);

		$stored = new ChecklistItem();
		$stored->setId(7);
		$stored->setListId(1);
		$stored->setName('Sugar');
		$this->itemMapper->method('findByList')->willReturn([$stored]);

		$items = $this->svc->listItems(1);
		$serialized = $this->svc->serializeListItems(1, 42, $items, 'alice');

		$this->assertCount(2, $serialized);
		$this->assertSame('Sugar', $serialized[0]['name']);
		$this->assertNull($serialized[0]['contributedBy']);
		$this->assertSame('Flour (recipe)', $serialized[1]['name']);
		$this->assertSame('cookbook', $serialized[1]['contributedBy']);
	}

	public function testCategorySuggestionFillsMissingCategoryId(): void {
		$handler = LatchBootstrap::registry()->registerHandler('classifier');
		$handler->hook(HookPoints::SOURCE, HookPoints::COLLECT_CATEGORY_SUGGESTIONS)
			->handle(fn (CategorySuggestionContext $ctx) => $ctx->itemName === 'milk' ? [17] : []);

		$this->listMapper->method('findById')->willReturn($this->makeList());
		$this->itemMapper->method('insert')->willReturnCallback(fn (ChecklistItem $i) => $i);

		$saved = $this->svc->addItem(1, ['name' => 'milk']);
		$this->assertSame(17, $saved->getCategoryId());
	}

	public function testRenderNameFilterAffectsSerialization(): void {
		$handler = LatchBootstrap::registry()->registerHandler('decorator');
		$handler->hook(HookPoints::SOURCE, HookPoints::FILTER_ITEM_RENDER_NAME)
			->handle(fn ($p) => $p->withName('★ ' . $p->name));

		$item = new ChecklistItem();
		$item->setId(1);
		$item->setListId(1);
		$item->setName('Milk');

		$out = $this->svc->serializeItem($item, 'alice');
		$this->assertSame('★ Milk', $out['name']);
		$this->assertSame([], $out['extraActions']);
		$this->assertSame([], $out['badges']);
	}

	public function testListNotFoundStillRaisesAfterFilter(): void {
		$this->listMapper->method('findById')->willThrowException(new DoesNotExistException('no'));
		$this->expectException(\OCA\Pantry\Exception\NotFoundException::class);
		$this->svc->addItem(999, ['name' => 'milk']);
	}
}
