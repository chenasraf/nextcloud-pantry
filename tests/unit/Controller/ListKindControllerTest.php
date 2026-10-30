<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Controller;

use OCA\Pantry\Activity\ActivityPublisher;
use OCA\Pantry\Controller\ChecklistController;
use OCA\Pantry\Controller\InventoryController;
use OCA\Pantry\Controller\ListKindOCSController;
use OCA\Pantry\Controller\TemplateController;
use OCA\Pantry\Db\Checklist;
use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Db\FieldValueMapper;
use OCA\Pantry\Db\House;
use OCA\Pantry\Db\ItemLabelMapper;
use OCA\Pantry\Db\ItemPriceMapper;
use OCA\Pantry\Db\ItemStoreMapper;
use OCA\Pantry\Exception\NotFoundException;
use OCA\Pantry\Service\CategoryService;
use OCA\Pantry\Service\ChecklistService;
use OCA\Pantry\Service\FieldDefinitionService;
use OCA\Pantry\Service\HouseAuthService;
use OCA\Pantry\Service\HouseService;
use OCA\Pantry\Service\ImageService;
use OCA\Pantry\Service\ItemDefaults;
use OCA\Pantry\Service\ItemDefaultsService;
use OCA\Pantry\Service\LabelService;
use OCA\Pantry\Service\NotificationService;
use OCA\Pantry\Service\PermissionService;
use OCA\Pantry\Service\PrefsService;
use OCA\Pantry\Service\RestockService;
use OCA\Pantry\Service\RestockSyncService;
use OCA\Pantry\Service\ShareService;
use OCA\Pantry\Service\StockLinkService;
use OCA\Pantry\Service\StockRules;
use OCA\Pantry\Service\StoreService;
use OCA\Pantry\Service\TemplateAttachmentService;
use OCA\Pantry\Service\TemplateCopyService;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ListKindControllerTest extends TestCase {
	/** @var ChecklistService&MockObject */
	private ChecklistService $lists;
	/** @var PermissionService&MockObject */
	private PermissionService $permissions;
	/** @var TemplateAttachmentService&MockObject */
	private TemplateAttachmentService $attachments;
	/** @var TemplateCopyService&MockObject */
	private TemplateCopyService $templateCopy;
	/** @var ShareService&MockObject */
	private ShareService $shares;
	/** @var ActivityPublisher&MockObject */
	private ActivityPublisher $activity;
	/** @var StockLinkService&MockObject */
	private StockLinkService $stockLinks;
	/** @var RestockSyncService&MockObject */
	private RestockSyncService $restockSync;
	/** @var RestockService&MockObject */
	private RestockService $restock;

	protected function setUp(): void {
		$this->activity = $this->createMock(ActivityPublisher::class);
		$this->stockLinks = $this->createMock(StockLinkService::class);
		$this->restockSync = $this->createMock(RestockSyncService::class);
		$this->restock = $this->createMock(RestockService::class);
		$this->lists = $this->createMock(ChecklistService::class);
		$this->attachments = $this->createMock(TemplateAttachmentService::class);
		$this->templateCopy = $this->createMock(TemplateCopyService::class);
		$this->permissions = $this->createMock(PermissionService::class);
		$this->permissions->method('isAdmin')->willReturn(true);
		$this->shares = $this->createMock(ShareService::class);
		$this->shares->method('userShareMap')->willReturn([]);
	}

	/**
	 * @template T of ListKindOCSController
	 * @param class-string<T> $class
	 * @param array<string, mixed> $itemDefaultsForList
	 * @return T
	 */
	private function makeController(string $class, array $itemDefaultsForList = []): ListKindOCSController {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$itemDefaults = $this->createMock(ItemDefaultsService::class);
		$itemDefaults->method('forList')->willReturn($itemDefaultsForList);
		$house = new House();
		$house->setName('Home');
		$houses = $this->createMock(HouseService::class);
		$houses->method('get')->willReturn($house);
		return new $class(
			'pantry',
			$this->createMock(IRequest::class),
			$this->lists,
			$this->createMock(CategoryService::class),
			$this->createMock(LabelService::class),
			$this->createMock(StoreService::class),
			$this->createMock(ItemStoreMapper::class),
			$this->createMock(ItemLabelMapper::class),
			$this->createMock(ItemPriceMapper::class),
			$this->createMock(FieldValueMapper::class),
			$this->createMock(FieldDefinitionService::class),
			$this->createMock(HouseAuthService::class),
			$houses,
			$this->createMock(ImageService::class),
			$this->createMock(NotificationService::class),
			$this->activity,
			$this->createMock(PrefsService::class),
			$this->permissions,
			$this->shares,
			$itemDefaults,
			$session,
			$this->createMock(LoggerInterface::class),
			$this->attachments,
			$this->templateCopy,
			$this->stockLinks,
			$this->restockSync,
			$this->restock,
		);
	}

	private function makeList(string $kind): Checklist {
		$list = new Checklist();
		$list->setId(7);
		$list->setHouseId(1);
		$list->setKind($kind);
		$list->setName('Weekly staples');
		return $list;
	}

	public function testTemplateResponseLeavesOutChecklistOnlyFields(): void {
		$this->lists->method('getList')->with(7, true, Checklist::KIND_TEMPLATE)
			->willReturn($this->makeList(Checklist::KIND_TEMPLATE));

		$data = $this->makeController(TemplateController::class)->showTemplate(1, 7)->getData();

		$this->assertSame('template', $data['kind']);
		foreach (['lastCompletedAt', 'deleteOnDoneDefault', 'defaultRecurrenceMode', 'defaultRrule', 'fillFromDefaults'] as $key) {
			$this->assertArrayNotHasKey($key, $data);
		}
	}

	public function testTemplateItemResponseLeavesOutTheDoneState(): void {
		$item = new ChecklistItem();
		$item->setId(3);
		$item->setListId(7);
		$item->setName('Bread');
		$item->setRrule('FREQ=WEEKLY');
		$this->lists->method('getList')->willReturn($this->makeList(Checklist::KIND_TEMPLATE));
		$this->lists->method('listItems')->willReturn([$item]);

		$data = $this->makeController(TemplateController::class)->indexTemplateItems(1, 7)->getData();

		$this->assertCount(1, $data);
		$this->assertSame('FREQ=WEEKLY', $data[0]['rrule']);
		foreach (['done', 'doneAt', 'doneBy', 'nextDueAt'] as $key) {
			$this->assertArrayNotHasKey($key, $data[0]);
		}
	}

	public function testInventoryResponseLeavesOutRecurrence(): void {
		$this->lists->method('getList')->with(7, true, Checklist::KIND_INVENTORY)
			->willReturn($this->makeList(Checklist::KIND_INVENTORY));

		$data = $this->makeController(InventoryController::class, ['recurrence' => [], 'stores' => []])
			->showInventory(1, 7)->getData();

		$this->assertSame('inventory', $data['kind']);
		foreach (['lastCompletedAt', 'deleteOnDoneDefault', 'defaultRecurrenceMode', 'defaultRrule'] as $key) {
			$this->assertArrayNotHasKey($key, $data);
		}
		$this->assertSame(['stores' => []], $data['itemDefaults']);
		$this->assertTrue($data['fillFromDefaults']);
	}

	public function testAnEditorSwitchesOffFillingFromItemDefaults(): void {
		$list = $this->makeList(Checklist::KIND_INVENTORY);
		$this->lists->method('getList')->willReturn($list);
		$this->shares->method('canEditFromMap')->willReturn(true);
		$this->lists->expects($this->once())->method('setItemDefaults')
			->with($list, $this->anything(), false)
			->willReturnCallback(static function (Checklist $l, array $d, ?bool $fill): Checklist {
				$l->setFillFromDefaults($fill);
				return $l;
			});

		$data = $this->makeController(InventoryController::class)
			->updateInventoryItemDefaults(1, 7, fillFromDefaults: false)->getData();

		$this->assertFalse($data['fillFromDefaults']);
	}

	public function testOnlyAnEditorChangesTheFillSwitch(): void {
		$this->lists->method('getList')->willReturn($this->makeList(Checklist::KIND_CHECKLIST));
		$this->shares->method('canEditFromMap')->willReturn(false);
		$this->lists->expects($this->never())->method('setItemDefaults');

		$this->expectException(\OCP\AppFramework\OCS\OCSForbiddenException::class);
		$this->makeController(ChecklistController::class)->updateItemDefaults(1, 7, fillFromDefaults: false);
	}

	public function testInventoryItemResponseLeavesOutTheDoneStateAndRecurrence(): void {
		$item = new ChecklistItem();
		$item->setId(3);
		$item->setListId(7);
		$item->setName('Flour');
		$this->lists->method('getList')->willReturn($this->makeList(Checklist::KIND_INVENTORY));
		$this->lists->method('listItems')->willReturn([$item]);

		$data = $this->makeController(InventoryController::class)->indexInventoryItems(1, 7)->getData();

		$this->assertCount(1, $data);
		foreach (['done', 'doneAt', 'doneBy', 'nextDueAt', 'rrule', 'repeatFromCompletion', 'deleteOnDone'] as $key) {
			$this->assertArrayNotHasKey($key, $data[0]);
		}
	}

	public function testInventoryItemsCarryTheirStockStatus(): void {
		$item = new ChecklistItem();
		$item->setId(3);
		$item->setListId(7);
		$item->setName('Eggs');
		$item->setQuantity('4');
		$item->setTarget('12 eggs');
		$this->lists->method('getList')->willReturn($this->makeList(Checklist::KIND_INVENTORY));
		$this->lists->method('listItems')->willReturn([$item]);

		$data = $this->makeController(InventoryController::class)->indexInventoryItems(1, 7)->getData();

		$this->assertSame('count', $data[0]['stockMode']);
		$this->assertSame('full', $data[0]['stockLevel']);
		$this->assertSame('12 eggs', $data[0]['target']);
		$this->assertNull($data[0]['reorderPoint']);
		$this->assertSame('low', $data[0]['stockStatus']);
		$this->assertSame('8 eggs', $data[0]['shortfall']);
		$this->assertSame('none', $data[0]['expiryStatus']);
	}

	public function testInventoryItemsReportExpiryAgainstTheirInventorysWarning(): void {
		$item = new ChecklistItem();
		$item->setId(3);
		$item->setListId(7);
		$item->setName('Milk');
		$item->setQuantity('1');
		$item->setTarget('1');
		$item->setExpiresAt(StockRules::addDays(StockRules::today(), 3));
		$item->setShelfLifeDays(7);
		$inventory = $this->makeList(Checklist::KIND_INVENTORY);
		$inventory->setExpiryLeadDays(3);
		$this->lists->method('getList')->willReturn($inventory);
		$this->lists->method('listItems')->willReturn([$item]);

		$data = $this->makeController(InventoryController::class)->indexInventoryItems(1, 7)->getData();

		$this->assertSame('expiring', $data[0]['expiryStatus']);
		$this->assertSame(7, $data[0]['shelfLifeDays']);
	}

	public function testChecklistItemsHaveNoStockAndTemplateItemsNoStockLevel(): void {
		$item = new ChecklistItem();
		$item->setId(3);
		$item->setListId(7);
		$item->setName('Eggs');
		$item->setStockMode('count');
		$item->setTarget('12');
		$this->lists->method('listItems')->willReturn([$item]);

		$this->lists->method('getList')->willReturnCallback(fn (int $id, bool $deleted, string $kind): Checklist => $this->makeList($kind));
		$lists = $this->makeController(ChecklistController::class)->indexItems(1, 7)->getData();
		$templates = $this->makeController(TemplateController::class)->indexTemplateItems(1, 7)->getData();

		foreach (['stockMode', 'stockLevel', 'target', 'reorderPoint', 'stockStatus', 'shortfall', 'expiresAt', 'shelfLifeDays', 'expiryStatus'] as $key) {
			$this->assertArrayNotHasKey($key, $lists[0]);
		}
		$this->assertSame('count', $templates[0]['stockMode']);
		$this->assertSame('12', $templates[0]['target']);
		$this->assertArrayHasKey('reorderPoint', $templates[0]);
		foreach (['stockLevel', 'stockStatus', 'shortfall', 'expiresAt', 'expiryStatus'] as $key) {
			$this->assertArrayNotHasKey($key, $templates[0]);
		}
		$this->assertArrayHasKey('shelfLifeDays', $templates[0]);
	}

	public function testInventoriesCountTheItemsThatNeedRestock(): void {
		$first = $this->makeList(Checklist::KIND_INVENTORY);
		$first->setId(7);
		$second = $this->makeList(Checklist::KIND_INVENTORY);
		$second->setId(8);
		$this->lists->method('listForHouse')->willReturn([$first, $second]);
		$second->setExpiryLeadDays(0);
		$this->lists->method('countNeedsRestock')->with([$first, $second])->willReturn([7 => 2]);
		$this->permissions->method('canAccessList')->willReturn(true);

		$data = $this->makeController(InventoryController::class, ItemDefaults::complete([]))->indexInventories(1)->getData();

		$this->assertSame(2, $data[0]['needsRestock']);
		$this->assertSame(0, $data[1]['needsRestock']);
		$this->assertSame(2, $data[0]['expiryLeadDays']);
		$this->assertSame(0, $data[1]['expiryLeadDays']);
		$this->assertArrayHasKey('stockMode', $data[0]['itemDefaults']);
	}

	public function testChecklistsAndTemplatesHaveNoStockDefaults(): void {
		$list = $this->makeList(Checklist::KIND_TEMPLATE);
		$list->setId(7);
		$this->lists->method('listForHouse')->willReturn([$list]);
		$this->permissions->method('canAccessList')->willReturn(true);

		$data = $this->makeController(TemplateController::class, ItemDefaults::complete([]))->indexTemplates(1)->getData();

		$this->assertArrayNotHasKey('stockMode', $data[0]['itemDefaults']);
		$this->assertArrayNotHasKey('needsRestock', $data[0]);
	}

	public function testInventoryItemUpdatesPassOnlyTheStockFieldsSent(): void {
		$item = new ChecklistItem();
		$item->setId(3);
		$item->setListId(7);
		$item->setName('Eggs');
		$this->lists->method('getList')->willReturn($this->makeList(Checklist::KIND_INVENTORY));
		$this->lists->method('getItem')->willReturn($item);
		$this->lists->expects($this->once())->method('updateItem')
			->with(3, ['stockMode' => 'level', 'target' => ''])
			->willReturn($item);

		$this->makeController(InventoryController::class)
			->updateInventoryItem(1, 7, 3, stockMode: 'level', target: '');
	}

	public function testInventoryItemsAreAddedWithoutRecurrence(): void {
		$item = new ChecklistItem();
		$item->setId(3);
		$item->setListId(7);
		$item->setName('Flour');
		$this->lists->method('getList')->willReturn($this->makeList(Checklist::KIND_INVENTORY));
		$this->lists->expects($this->once())->method('addItem')
			->with(7, $this->callback(static fn (array $data): bool => $data['rrule'] === null
				&& $data['repeatFromCompletion'] === false
				&& $data['deleteOnDone'] === false))
			->willReturn($item);

		$this->makeController(InventoryController::class)->addInventoryItem(1, 7, 'Flour');
	}

	public function testChecklistResourceDoesNotServeAnInventory(): void {
		$this->lists->method('getList')->with(7, true, Checklist::KIND_CHECKLIST)
			->willThrowException(new NotFoundException('List not found'));

		$this->expectException(OCSNotFoundException::class);
		$this->makeController(ChecklistController::class)->showList(1, 7);
	}

	public function testChecklistResourceDoesNotServeATemplate(): void {
		$this->lists->method('getList')->with(7, true, Checklist::KIND_CHECKLIST)
			->willThrowException(new NotFoundException('List not found'));

		$this->expectException(OCSNotFoundException::class);
		$this->makeController(ChecklistController::class)->showList(1, 7);
	}

	public function testChecklistResourceStillReturnsItsDoneState(): void {
		$this->lists->method('getList')->willReturn($this->makeList(Checklist::KIND_CHECKLIST));

		$data = $this->makeController(ChecklistController::class)->showList(1, 7)->getData();

		$this->assertSame('checklist', $data['kind']);
		$this->assertArrayHasKey('lastCompletedAt', $data);
	}

	public function testDuplicateAsInventoryCreatesAnInventoryFromAChecklist(): void {
		$this->lists->method('getList')->with(7)->willReturn($this->makeList(Checklist::KIND_CHECKLIST));
		$copy = $this->makeList(Checklist::KIND_INVENTORY);
		$copy->setId(8);
		$this->lists->expects($this->once())->method('duplicateList')
			->with(7, 'Pantry shelf', true, 'alice', $this->anything(), Checklist::KIND_INVENTORY)
			->willReturn($copy);

		$data = $this->makeController(InventoryController::class)->duplicateAsInventory(1, 7, 'Pantry shelf')->getData();

		$this->assertSame('inventory', $data['kind']);
		$this->assertArrayNotHasKey('defaultRrule', $data);
	}

	public function testDuplicateAsChecklistPassesResetDone(): void {
		$this->lists->method('getList')->willReturn($this->makeList(Checklist::KIND_TEMPLATE));
		$this->lists->expects($this->once())->method('duplicateList')
			->with(7, 'This week', false, 'alice', $this->anything(), Checklist::KIND_CHECKLIST)
			->willReturn($this->makeList(Checklist::KIND_CHECKLIST));

		$this->makeController(ChecklistController::class)->duplicateAsChecklist(1, 7, 'This week', false);
	}

	public function testDuplicateAsTemplateRefusesAListOfAnotherHouse(): void {
		$list = $this->makeList(Checklist::KIND_CHECKLIST);
		$list->setHouseId(2);
		$this->lists->method('getList')->willReturn($list);
		$this->lists->expects($this->never())->method('duplicateList');

		$this->expectException(OCSNotFoundException::class);
		$this->makeController(TemplateController::class)->duplicateAsTemplate(1, 7, 'Staples');
	}

	public function testDuplicateWithinAKindKeepsTheSourceResourceKind(): void {
		$this->lists->method('getList')->willReturn($this->makeList(Checklist::KIND_TEMPLATE));
		$this->lists->expects($this->once())->method('duplicateList')
			->with(7, 'Copy', true, 'alice', $this->anything(), Checklist::KIND_TEMPLATE)
			->willReturn($this->makeList(Checklist::KIND_TEMPLATE));

		$this->makeController(TemplateController::class)->duplicateTemplate(1, 7, 'Copy');
	}

	/**
	 * Items 3 (on checklist 7) and 4 (on template 9), with list 9 the template.
	 */
	private function mixedSelection(): void {
		$onChecklist = new ChecklistItem();
		$onChecklist->setId(3);
		$onChecklist->setListId(7);
		$onChecklist->setName('Milk');
		$onTemplate = new ChecklistItem();
		$onTemplate->setId(4);
		$onTemplate->setListId(9);
		$onTemplate->setName('Bread');
		$template = $this->makeList(Checklist::KIND_TEMPLATE);
		$template->setId(9);
		$this->lists->method('getItem')->willReturnCallback(
			static fn (int $id): ChecklistItem => $id === 3 ? $onChecklist : $onTemplate,
		);
		$this->lists->method('getList')->willReturnCallback(
			fn (int $id): Checklist => $id === 9 ? $template : $this->makeList(Checklist::KIND_CHECKLIST),
		);
	}

	public function testBatchMoveTakesItemsOfEveryKind(): void {
		$this->mixedSelection();
		$this->lists->expects($this->once())->method('moveItems')->with([3], 9)->willReturn([]);

		$data = $this->makeController(ChecklistController::class)->batchMoveItems(1, [3, 4], 9)->getData();

		$this->assertSame([], $data['skipped'], 'the item already on the template is a no-op, not a skip');
	}

	private function copied(int $id, string $name): ChecklistItem {
		$item = new ChecklistItem();
		$item->setId($id);
		$item->setListId(7);
		$item->setName($name);
		return $item;
	}

	public function testBatchCopyTakesItemsOfEveryKind(): void {
		$this->mixedSelection();
		$this->lists->expects($this->once())->method('copyItem')->with(3, 7)->willReturn($this->copied(10, 'Milk'));
		$this->templateCopy->expects($this->once())->method('draw')
			->willReturn(['item' => $this->copied(11, 'Bread'), 'reused' => false]);

		$data = $this->makeController(ChecklistController::class)->batchCopyItems(1, [3, 4], 7)->getData();

		$this->assertSame([], $data['skipped']);
		$this->assertSame([10, 11], array_column($data['items'], 'id'));
		$this->assertSame([], $data['reused']);
	}

	public function testBatchCopyDrawsTemplateItemsAsDrawingEachOneWould(): void {
		$target = $this->makeList(Checklist::KIND_INVENTORY);
		$this->lists->method('getItem')->willReturnCallback(function (int $id): ChecklistItem {
			$item = new ChecklistItem();
			$item->setId($id);
			$item->setListId(9);
			$item->setName($id === 4 ? 'Bread' : 'Eggs');
			return $item;
		});
		$template = $this->makeList(Checklist::KIND_TEMPLATE);
		$template->setId(9);
		$this->lists->method('getList')->willReturnCallback(static fn (int $id): Checklist => $id === 9 ? $template : $target);
		$this->lists->expects($this->never())->method('copyItem');

		$drawnWith = [];
		$this->templateCopy->expects($this->exactly(2))->method('draw')->willReturnCallback(
			function (ChecklistItem $source, Checklist $list, ?string $uid) use (&$drawnWith): array {
				$drawnWith[] = [$source->getId(), $list, $uid];
				return $source->getId() === 4
					? ['item' => $this->copied(20, 'Bread'), 'reused' => false]
					: ['item' => $this->copied(21, 'Eggs'), 'reused' => true];
			},
		);
		$this->activity->expects($this->once())->method('publishItemsCopied')
			->with(1, 'Home', 'alice', 9, 'Weekly staples', 7, 'Weekly staples', ['Bread']);

		$data = $this->makeController(ChecklistController::class)->batchCopyItems(1, [4, 5], 7)->getData();

		$this->assertSame([[4, $target, 'alice'], [5, $target, 'alice']], $drawnWith);
		$this->assertSame([20, 21], array_column($data['items'], 'id'));
		$this->assertSame([21], $data['reused']);
	}

	public function testATemplateNamingAnItemTwiceListsItsCopyOnce(): void {
		$this->mixedSelection();
		$bread = $this->copied(20, 'Bread');
		$this->templateCopy->method('draw')->willReturnOnConsecutiveCalls(
			['item' => $bread, 'reused' => false],
			['item' => $bread, 'reused' => true],
		);

		$data = $this->makeController(ChecklistController::class)->batchCopyItems(1, [4, 4], 7)->getData();

		$this->assertSame([20], array_column($data['items'], 'id'));
		$this->assertSame([], $data['reused'], 'the copy this batch made is not reused');
	}

	public function testBatchCopyIntoATemplateCopiesWithoutDrawing(): void {
		$this->mixedSelection();
		$this->lists->expects($this->exactly(2))->method('copyItem')
			->willReturnCallback(fn (int $id): ChecklistItem => $this->copied($id + 10, 'Copy'));
		$this->templateCopy->expects($this->never())->method('draw');

		$data = $this->makeController(ChecklistController::class)->batchCopyItems(1, [3, 4], 9)->getData();

		$this->assertSame([13, 14], array_column($data['items'], 'id'));
	}

	public function testSuggestionsReturnTemplateRowsInTheTemplateItemShape(): void {
		$this->lists->method('getList')->willReturn($this->makeList(Checklist::KIND_CHECKLIST));
		$bread = new ChecklistItem();
		$bread->setId(30);
		$bread->setListId(9);
		$bread->setName('Bread');
		$bread->setRrule('FREQ=WEEKLY');
		$this->attachments->method('suggestions')->willReturn([
			['item' => $bread, 'templates' => [['id' => 9, 'name' => 'Weekly']]],
		]);
		$this->lists->expects($this->never())->method('listArchivedItems');

		$data = $this->makeController(ChecklistController::class)->listSuggestions(1, 7)->getData();

		$this->assertSame([], $data['archived'], 'archived items only when asked for');
		$this->assertCount(1, $data['templates']);
		$this->assertSame('Bread', $data['templates'][0]['item']['name']);
		$this->assertSame('FREQ=WEEKLY', $data['templates'][0]['item']['rrule']);
		$this->assertArrayNotHasKey('done', $data['templates'][0]['item']);
		$this->assertSame([['id' => 9, 'name' => 'Weekly']], $data['templates'][0]['templates']);
	}

	public function testInventorySuggestionsIncludeArchivedItemsWhenAskedFor(): void {
		$this->lists->method('getList')->willReturn($this->makeList(Checklist::KIND_INVENTORY));
		$flour = new ChecklistItem();
		$flour->setId(3);
		$flour->setListId(7);
		$flour->setName('Flour');
		$this->lists->expects($this->once())->method('listArchivedItems')->with(7)->willReturn([$flour]);
		$this->attachments->method('suggestions')->willReturn([]);

		$data = $this->makeController(InventoryController::class)->inventorySuggestions(1, 7, true)->getData();

		$this->assertSame('Flour', $data['archived'][0]['name']);
		$this->assertArrayNotHasKey('rrule', $data['archived'][0], 'archived items take the inventory shape');
	}

	public function testDuplicateCarriesTemplateAttachments(): void {
		$source = $this->makeList(Checklist::KIND_CHECKLIST);
		$copy = $this->makeList(Checklist::KIND_CHECKLIST);
		$copy->setId(8);
		$this->lists->method('getList')->willReturn($source);
		$this->lists->method('duplicateList')->willReturn($copy);
		$this->attachments->expects($this->once())->method('copyAttachments')->with($source, $copy);

		$this->makeController(ChecklistController::class)->duplicateList(1, 7, 'Copy');
	}

	private function drawSetup(): ChecklistItem {
		$source = new ChecklistItem();
		$source->setId(30);
		$source->setListId(9);
		$source->setName('Bread');
		$template = $this->makeList(Checklist::KIND_TEMPLATE);
		$template->setId(9);
		$this->lists->method('getItem')->with(30)->willReturn($source);
		$this->lists->method('getList')->willReturnCallback(
			fn (int $id): Checklist => $id === 9 ? $template : $this->makeList(Checklist::KIND_CHECKLIST),
		);
		$drawn = new ChecklistItem();
		$drawn->setId(31);
		$drawn->setListId(7);
		$drawn->setName('Bread');
		return $drawn;
	}

	public function testDrawReturnsTheCopyInTheListShape(): void {
		$drawn = $this->drawSetup();
		$this->templateCopy->expects($this->once())->method('draw')->willReturn(['item' => $drawn, 'reused' => false]);

		$data = $this->makeController(ChecklistController::class)->drawItem(1, 7, 30)->getData();

		$this->assertFalse($data['reused']);
		$this->assertSame(31, $data['item']['id']);
		$this->assertArrayHasKey('done', $data['item']);
	}

	public function testDrawRefusesATemplateTheCallerCannotAccess(): void {
		$this->drawSetup();
		$this->permissions = $this->createMock(PermissionService::class);
		$this->permissions->method('isAdmin')->willReturn(false);
		$this->permissions->method('canAccessList')->willReturnCallback(static fn (int $h, string $u, int $id): bool => $id !== 9);
		$this->templateCopy->expects($this->never())->method('draw');

		$this->expectException(\OCP\AppFramework\OCS\OCSForbiddenException::class);
		$this->makeController(InventoryController::class)->drawInventoryItem(1, 7, 30);
	}

	public function testBatchUncheckSkipsItemsOfOtherKinds(): void {
		$this->mixedSelection();
		$this->lists->expects($this->once())->method('uncheckItems')->with([3])->willReturn([]);

		$data = $this->makeController(ChecklistController::class)->batchUncheckItems(1, [3, 4])->getData();

		$this->assertSame([4], $data['skipped']);
	}

	// ----- Stock links -----

	/**
	 * Inventory 7 holds item 3; checklist 10 holds item 50.
	 */
	private function stageLink(bool $archivedChecklist = false): ChecklistItem {
		$item = new ChecklistItem();
		$item->setId(3);
		$item->setListId(7);
		$item->setName('Eggs');
		$row = new ChecklistItem();
		$row->setId(50);
		$row->setListId(10);
		$row->setName('Eggs');
		$this->lists->method('getItem')->willReturnCallback(static fn (int $id): ChecklistItem => $id === 50 ? $row : $item);
		$this->lists->method('getList')->willReturnCallback(function (int $id) use ($archivedChecklist): Checklist {
			$list = $this->makeList($id === 10 ? Checklist::KIND_CHECKLIST : Checklist::KIND_INVENTORY);
			$list->setId($id);
			if ($id === 10 && $archivedChecklist) {
				$list->setArchivedAt(1);
			}
			return $list;
		});
		return $item;
	}

	public function testLinkingByHandNeedsEditRightsOnTheChecklist(): void {
		$this->stageLink();
		$this->shares->method('canEditFromMap')->willReturn(false);
		$this->stockLinks->expects($this->never())->method('link');

		$this->expectException(\OCP\AppFramework\OCS\OCSForbiddenException::class);
		$this->makeController(InventoryController::class)->linkInventoryItem(1, 7, 3, 50);
	}

	public function testAnEditorLinksByHand(): void {
		$item = $this->stageLink();
		$this->shares->method('canEditFromMap')->willReturn(true);
		$this->stockLinks->expects($this->once())->method('link')
			->with($item, $this->callback(static fn (ChecklistItem $r): bool => $r->getId() === 50))
			->willReturnArgument(0);

		$this->makeController(InventoryController::class)->linkInventoryItem(1, 7, 3, 50);
	}

	public function testLinkingRefusesAnItemOnAnArchivedChecklist(): void {
		$this->stageLink(archivedChecklist: true);
		$this->shares->method('canEditFromMap')->willReturn(true);
		$this->stockLinks->expects($this->never())->method('link');

		$this->expectException(\OCP\AppFramework\OCS\OCSBadRequestException::class);
		$this->makeController(InventoryController::class)->linkInventoryItem(1, 7, 3, 50);
	}

	public function testUnlinkingAnItem(): void {
		$item = $this->stageLink();
		$this->stockLinks->expects($this->once())->method('unlink')->with($item)->willReturnArgument(0);

		$this->makeController(InventoryController::class)->unlinkInventoryItem(1, 7, 3);
	}

	public function testInventoryItemsCarryTheirLink(): void {
		$item = new ChecklistItem();
		$item->setId(3);
		$item->setListId(7);
		$item->setName('Eggs');
		$link = ['itemId' => 50, 'name' => 'Eggs', 'quantity' => null, 'listId' => 10, 'listName' => 'Shopping', 'listColor' => null, 'state' => 'open'];
		$this->lists->method('getList')->willReturn($this->makeList(Checklist::KIND_INVENTORY));
		$this->lists->method('listItems')->willReturn([$item]);
		$this->stockLinks->method('describe')->willReturn([3 => ['autoLink' => true, 'stockLink' => $link, 'linkCandidates' => []]]);

		$data = $this->makeController(InventoryController::class)->indexInventoryItems(1, 7)->getData();

		$this->assertTrue($data[0]['autoLink']);
		$this->assertSame($link, $data[0]['stockLink']);
		$this->assertSame([], $data[0]['linkCandidates']);
	}

	public function testInventoryItemUpdatesPassTheAutoLinkSwitch(): void {
		$item = $this->stageLink();
		$this->lists->expects($this->once())->method('updateItem')
			->with(3, ['autoLink' => true])
			->willReturn($item);

		$this->makeController(InventoryController::class)->updateInventoryItem(1, 7, 3, autoLink: true);
	}

	public function testChecklistItemsListWhatTheyRestock(): void {
		$row = new ChecklistItem();
		$row->setId(50);
		$row->setListId(7);
		$row->setName('Eggs');
		$restocks = [['itemId' => 3, 'name' => 'Eggs', 'listId' => 20, 'listName' => 'Kitchen']];
		$this->lists->method('getList')->willReturn($this->makeList(Checklist::KIND_CHECKLIST));
		$this->lists->method('listItems')->willReturn([$row]);
		$this->stockLinks->method('restocksFor')->willReturn([50 => $restocks]);

		$data = $this->makeController(ChecklistController::class)->indexItems(1, 7)->getData();

		$this->assertSame($restocks, $data[0]['restocks']);
		$this->assertArrayNotHasKey('stockLink', $data[0]);
	}

	// ----- Buying restocks -----

	private const RESTOCKED = ['itemId' => 3, 'listId' => 20, 'itemName' => 'Eggs', 'listName' => 'Kitchen', 'added' => '8', 'previousLevel' => null, 'replaced' => false, 'previousOnHand' => null, 'previousExpiresAt' => null, 'expiresAt' => null];

	private function stageRow(bool $done): ChecklistItem {
		$row = new ChecklistItem();
		$row->setId(50);
		$row->setListId(7);
		$row->setName('Eggs');
		$row->setDone($done);
		$this->lists->method('getList')->willReturn($this->makeList(Checklist::KIND_CHECKLIST));
		$this->lists->method('getItem')->willReturn($row);
		return $row;
	}

	public function testATickThatRestocksTellsTheMemberAndTheActivity(): void {
		$row = $this->stageRow(false);
		$ticked = clone $row;
		$ticked->setDone(true);
		$ticked->setRestock([self::RESTOCKED]);
		$this->restock->expects($this->once())->method('toggle')->with(50, 'alice')
			->willReturn(['item' => $ticked, 'restocked' => [self::RESTOCKED], 'undone' => [], 'events' => []]);
		$this->activity->expects($this->once())->method('publishItemDone')
			->with(1, 'Home', 'alice', 50, 'Eggs', 7, 'Weekly staples', [self::RESTOCKED]);

		$data = $this->makeController(ChecklistController::class)->toggleItem(1, 7, 50)->getData();

		$this->assertSame([self::RESTOCKED], $data['restocked']);
		$this->assertSame([self::RESTOCKED], $data['restock']);
		$this->assertArrayNotHasKey('restockUndone', $data);
	}

	public function testATickThatCameStraightBackStillCountsAsATick(): void {
		$row = $this->stageRow(false);
		$revived = clone $row;
		$event = ['event' => 'revived', 'itemId' => 4, 'rowId' => 50, 'rowName' => 'Eggs', 'quantity' => '4', 'listId' => 7, 'listName' => 'Weekly staples'];
		$this->restock->method('toggle')
			->willReturn(['item' => $revived, 'restocked' => [self::RESTOCKED], 'undone' => [], 'events' => [$event]]);
		$this->lists->method('getItem')->willReturn($revived);
		$this->activity->expects($this->once())->method('publishItemDone');
		$this->activity->expects($this->never())->method('publishItemReopened');

		$data = $this->makeController(ChecklistController::class)->toggleItem(1, 7, 50)->getData();

		$this->assertFalse($data['done']);
		$this->assertSame([$event], $data['restockSync']);
	}

	public function testAMembersUncheckSaysWhatItTookBack(): void {
		$row = $this->stageRow(true);
		$unchecked = clone $row;
		$unchecked->setDone(false);
		$this->restock->method('toggle')
			->willReturn(['item' => $unchecked, 'restocked' => [], 'undone' => [self::RESTOCKED], 'events' => []]);
		$this->activity->expects($this->once())->method('publishItemReopened');

		$data = $this->makeController(ChecklistController::class)->toggleItem(1, 7, 50)->getData();

		$this->assertSame([self::RESTOCKED], $data['restockUndone']);
		$this->assertNull($data['restock']);
	}

	public function testInventoryItemsHaveNoRestockRecord(): void {
		$item = $this->stageLink();
		$this->lists->method('updateItem')->willReturn($item);

		$data = $this->makeController(InventoryController::class)->updateInventoryItem(1, 7, 3, name: 'Eggs')->getData();

		$this->assertArrayNotHasKey('restock', $data);
	}

	public function testInventoriesReportOnlyAUsableRestockChecklist(): void {
		$inventory = $this->makeList(Checklist::KIND_INVENTORY);
		$inventory->setRestockChecklistId(10);
		$this->lists->method('getList')->willReturn($inventory);
		$this->stockLinks->method('restockChecklist')->willReturn(null);

		$data = $this->makeController(InventoryController::class)->showInventory(1, 7)->getData();

		$this->assertNull($data['restockChecklistId']);
	}

	// ----- Restock sync -----

	/** @return array<string, mixed> */
	private function syncEvent(string $event): array {
		return ['event' => $event, 'itemId' => 3, 'rowId' => 50, 'rowName' => 'Eggs', 'quantity' => '8 eggs', 'listId' => 10, 'listName' => 'Shopping'];
	}

	public function testAStockWriteSyncsAgainstTheStockItHadBefore(): void {
		$item = $this->stageLink();
		$item->setStockMode('count');
		$item->setQuantity('12 eggs');
		$item->setTarget('12 eggs');
		$updated = clone $item;
		$updated->setQuantity('4 eggs');
		$this->lists->method('updateItem')->willReturn($updated);
		$this->restockSync->expects($this->once())->method('afterWrite')
			->with($updated, ['status' => 'ok', 'shortfall' => null, 'expiry' => 'none', 'low' => false, 'needs' => false], false, 'alice')
			->willReturn([$this->syncEvent('added')]);

		$data = $this->makeController(InventoryController::class)->updateInventoryItem(1, 7, 3, quantity: '4 eggs')->getData();

		$this->assertSame([$this->syncEvent('added')], $data['restockSync']);
	}

	public function testAWriteTheSyncLeftAloneSaysNothing(): void {
		$item = $this->stageLink();
		$this->lists->method('updateItem')->willReturn($item);

		$data = $this->makeController(InventoryController::class)->updateInventoryItem(1, 7, 3, name: 'Eggs')->getData();

		$this->assertArrayNotHasKey('restockSync', $data);
	}

	public function testANewItemSyncsWithNothingBefore(): void {
		$item = $this->stageLink();
		$this->lists->method('addItem')->willReturn($item);
		$this->restockSync->expects($this->once())->method('afterWrite')->with($item, null, false, 'alice');

		$this->makeController(InventoryController::class)->addInventoryItem(1, 7, 'Eggs');
	}

	public function testLinkingByHandSyncsTheNewLink(): void {
		$this->stageLink();
		$this->shares->method('canEditFromMap')->willReturn(true);
		$this->stockLinks->method('link')->willReturnCallback(static function (ChecklistItem $item, ChecklistItem $row): ChecklistItem {
			$item->setLinkedItemId((int)$row->getId());
			return $item;
		});
		$this->restockSync->expects($this->once())->method('afterWrite')
			->with($this->anything(), $this->anything(), true, 'alice')
			->willReturn([$this->syncEvent('revived')]);

		$data = $this->makeController(InventoryController::class)->linkInventoryItem(1, 7, 3, 50)->getData();

		$this->assertSame([$this->syncEvent('revived')], $data['restockSync']);
	}

	public function testSettingTheRestockChecklistBackFillsTheInventory(): void {
		$this->lists->method('getList')->willReturn($this->makeList(Checklist::KIND_INVENTORY));
		$updated = $this->makeList(Checklist::KIND_INVENTORY);
		$updated->setRestockChecklistId(10);
		$this->lists->method('updateList')->willReturn($updated);
		$this->restockSync->expects($this->once())->method('backfill')->with($updated);

		$this->makeController(InventoryController::class)->updateInventory(1, 7, restockChecklistId: 10);
	}

	public function testOtherInventorySettingsBackFillNothing(): void {
		$inventory = $this->makeList(Checklist::KIND_INVENTORY);
		$inventory->setRestockChecklistId(10);
		$this->lists->method('getList')->willReturn($inventory);
		$this->lists->method('updateList')->willReturn(clone $inventory);
		$this->restockSync->expects($this->never())->method('backfill');

		$this->makeController(InventoryController::class)->updateInventory(1, 7, name: 'Pantry');
	}

	public function testLettingStockSetAPinnedQuantityResyncsTheRow(): void {
		$row = new ChecklistItem();
		$row->setId(50);
		$row->setListId(7);
		$row->setName('Eggs');
		$row->setQuantityPinned(true);
		$unpinned = clone $row;
		$unpinned->setQuantityPinned(false);
		$this->lists->method('getItem')->willReturn($row);
		$this->lists->method('getList')->willReturn($this->makeList(Checklist::KIND_CHECKLIST));
		$this->lists->expects($this->once())->method('updateItem')->with(50, ['quantityPinned' => false])->willReturn($unpinned);
		$this->restockSync->expects($this->once())->method('resyncRow')->with($unpinned)->willReturn([$this->syncEvent('changed')]);

		$data = $this->makeController(ChecklistController::class)->updateItem(1, 7, 50, quantityPinned: false)->getData();

		$this->assertSame([$this->syncEvent('changed')], $data['restockSync']);
		$this->assertTrue($data['quantityPinned']);
	}

	public function testEditingAnUnpinnedRowResyncsNothing(): void {
		$row = new ChecklistItem();
		$row->setId(50);
		$row->setListId(7);
		$row->setName('Eggs');
		$this->lists->method('getItem')->willReturn($row);
		$this->lists->method('getList')->willReturn($this->makeList(Checklist::KIND_CHECKLIST));
		$this->lists->method('updateItem')->willReturn($row);
		$this->restockSync->expects($this->never())->method('resyncRow');

		$this->makeController(ChecklistController::class)->updateItem(1, 7, 50, quantity: '3');
	}

	public function testRestoringAChecklistBackFillsTheInventoriesRestockingIntoIt(): void {
		$checklist = $this->makeList(Checklist::KIND_CHECKLIST);
		$this->lists->method('getList')->willReturn($checklist);
		$this->lists->method('restoreList')->willReturn($checklist);
		$this->lists->method('unarchiveList')->willReturn($checklist);
		$this->restockSync->expects($this->exactly(2))->method('backfillInto')->with($checklist);

		$controller = $this->makeController(ChecklistController::class);
		$controller->restoreList(1, 7);
		$controller->unarchiveList(1, 7);
	}
}
