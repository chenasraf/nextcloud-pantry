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
use OCA\Pantry\Service\ItemDefaultsService;
use OCA\Pantry\Service\LabelService;
use OCA\Pantry\Service\NotificationService;
use OCA\Pantry\Service\PermissionService;
use OCA\Pantry\Service\PrefsService;
use OCA\Pantry\Service\ShareService;
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

	protected function setUp(): void {
		$this->activity = $this->createMock(ActivityPublisher::class);
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
}
