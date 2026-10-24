<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Db\Category;
use OCA\Pantry\Db\CategoryMapper;
use OCA\Pantry\Db\Checklist;
use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Db\ChecklistItemMapper;
use OCA\Pantry\Db\FieldDefinition;
use OCA\Pantry\Db\FieldDefinitionMapper;
use OCA\Pantry\Db\FieldOption;
use OCA\Pantry\Db\FieldOptionMapper;
use OCA\Pantry\Db\FieldValueMapper;
use OCA\Pantry\Db\ItemLabelMapper;
use OCA\Pantry\Db\ItemPriceMapper;
use OCA\Pantry\Db\ItemStoreMapper;
use OCA\Pantry\Db\Label;
use OCA\Pantry\Db\LabelMapper;
use OCA\Pantry\Service\ChecklistService;
use OCA\Pantry\Service\ItemDefaults;
use OCA\Pantry\Service\ItemDefaultsService;
use OCA\Pantry\Service\PrefsService;
use OCA\Pantry\Service\ScopedTaxonomyService;
use OCA\Pantry\Service\TemplateCopyService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\L10N\IFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TemplateCopyServiceTest extends TestCase {
	private const HOUSE = 1;
	private const TEMPLATE = 50;
	private const TARGET = 10;

	/** @var ChecklistService&MockObject */
	private ChecklistService $lists;
	/** @var ChecklistItemMapper&MockObject */
	private ChecklistItemMapper $itemMapper;
	/** @var CategoryMapper&MockObject */
	private CategoryMapper $categoryMapper;
	/** @var LabelMapper&MockObject */
	private LabelMapper $labelMapper;
	/** @var FieldDefinitionMapper&MockObject */
	private FieldDefinitionMapper $fieldDefMapper;
	/** @var FieldOptionMapper&MockObject */
	private FieldOptionMapper $fieldOptionMapper;
	/** @var ItemStoreMapper&MockObject */
	private ItemStoreMapper $itemStoreMapper;
	/** @var ItemLabelMapper&MockObject */
	private ItemLabelMapper $itemLabelMapper;
	/** @var ItemPriceMapper&MockObject */
	private ItemPriceMapper $itemPriceMapper;
	/** @var FieldValueMapper&MockObject */
	private FieldValueMapper $fieldValueMapper;
	/** @var ItemDefaultsService&MockObject */
	private ItemDefaultsService $itemDefaults;
	private TemplateCopyService $svc;
	private PrefsService $prefs;
	/** Bob's stored fill preference for the house; empty is unset. */
	private string $memberFill = '';

	private Checklist $template;
	/** @var array<string, mixed>|null What addItem was handed. */
	private ?array $added = null;
	private int $nextId = 900;

	protected function setUp(): void {
		$this->lists = $this->createMock(ChecklistService::class);
		$this->itemMapper = $this->createMock(ChecklistItemMapper::class);
		$this->categoryMapper = $this->createMock(CategoryMapper::class);
		$this->labelMapper = $this->createMock(LabelMapper::class);
		$this->fieldDefMapper = $this->createMock(FieldDefinitionMapper::class);
		$this->fieldOptionMapper = $this->createMock(FieldOptionMapper::class);
		$this->itemStoreMapper = $this->createMock(ItemStoreMapper::class);
		$this->itemLabelMapper = $this->createMock(ItemLabelMapper::class);
		$this->itemPriceMapper = $this->createMock(ItemPriceMapper::class);
		$this->fieldValueMapper = $this->createMock(FieldValueMapper::class);
		$this->itemDefaults = $this->createMock(ItemDefaultsService::class);

		$this->template = $this->makeList(self::TEMPLATE, Checklist::KIND_TEMPLATE);
		$this->lists->method('getList')->willReturnCallback(
			fn (int $id): Checklist => $id === self::TEMPLATE ? $this->template : $this->makeList($id, Checklist::KIND_CHECKLIST),
		);
		$this->lists->method('addItem')->willReturnCallback(function (int $listId, array $data, ?string $by): ChecklistItem {
			$this->added = $data;
			$item = new ChecklistItem();
			$item->setId(777);
			$item->setListId($listId);
			$item->setName($data['name']);
			return $item;
		});
		$this->itemDefaults->method('forList')->willReturn(ItemDefaults::complete([]));

		$insert = function ($entity) {
			$entity->setId($this->nextId++);
			return $entity;
		};
		foreach ([$this->categoryMapper, $this->labelMapper, $this->fieldDefMapper, $this->fieldOptionMapper] as $mapper) {
			$mapper->method('insert')->willReturnCallback($insert);
		}

		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturnCallback(
			fn (string $uid, string $app, string $key, string $default): string => $uid === 'bob' && $key === 'fill_from_item_defaults_' . self::HOUSE && $this->memberFill !== ''
				? $this->memberFill
				: $default,
		);
		$this->prefs = new PrefsService($config, $this->createMock(IL10N::class), $this->createMock(IFactory::class));

		$this->rebuild();
	}

	private function makeList(int $id, string $kind): Checklist {
		$list = new Checklist();
		$list->setId($id);
		$list->setHouseId(self::HOUSE);
		$list->setKind($kind);
		$list->setName('List ' . $id);
		return $list;
	}

	private function templateItem(string $name = 'Oat milk'): ChecklistItem {
		$item = new ChecklistItem();
		$item->setId(42);
		$item->setListId(self::TEMPLATE);
		$item->setName($name);
		return $item;
	}

	private function targetItem(string $name, bool $done = false, ?int $archivedAt = null): ChecklistItem {
		$item = new ChecklistItem();
		$item->setId(5);
		$item->setListId(self::TARGET);
		$item->setName($name);
		$item->setDone($done);
		$item->setArchivedAt($archivedAt);
		return $item;
	}

	private function category(int $id, string $name, ?int $listId): Category {
		$c = new Category();
		$c->setId($id);
		$c->setHouseId(self::HOUSE);
		$c->setListId($listId);
		$c->setName($name);
		$c->setIcon('tag');
		$c->setColor('#22c55e');
		return $c;
	}

	private function label(int $id, string $name, ?int $listId): Label {
		$l = new Label();
		$l->setId($id);
		$l->setHouseId(self::HOUSE);
		$l->setListId($listId);
		$l->setName($name);
		$l->setIcon('tag');
		$l->setColor('#22c55e');
		return $l;
	}

	private function field(int $id, string $name, ?int $listId, string $type = FieldDefinition::TYPE_SELECT): FieldDefinition {
		$f = new FieldDefinition();
		$f->setId($id);
		$f->setHouseId(self::HOUSE);
		$f->setListId($listId);
		$f->setName($name);
		$f->setType($type);
		return $f;
	}

	private function option(int $id, int $fieldId, string $label): FieldOption {
		$o = new FieldOption();
		$o->setId($id);
		$o->setFieldId($fieldId);
		$o->setLabel($label);
		return $o;
	}

	/**
	 * @return array{item: ChecklistItem, reused: bool}
	 */
	private function draw(ChecklistItem $source, string $targetKind = Checklist::KIND_CHECKLIST, ?callable $duplicateImage = null, ?Checklist $target = null): array {
		return $this->svc->draw(
			$source,
			$target ?? $this->makeList(self::TARGET, $targetKind),
			'bob',
			$duplicateImage ?? static fn (int $f, string $o): ?int => null,
		);
	}

	public function testOnlyTemplateItemsCanBeDrawn(): void {
		$this->template->setKind(Checklist::KIND_CHECKLIST);

		$this->expectException(\InvalidArgumentException::class);
		$this->draw($this->templateItem());
	}

	public function testNothingIsDrawnIntoATemplate(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->draw($this->templateItem(), Checklist::KIND_TEMPLATE);
	}

	public function testASameNameItemOnAChecklistIsUncheckedAndUnarchived(): void {
		$existing = $this->targetItem(' OAT  Milk', done: true, archivedAt: 100);
		$this->targetHas([$existing]);
		$this->lists->expects($this->once())->method('uncheckItems')->with([5]);
		$this->lists->expects($this->once())->method('unarchiveItem')->with(5);
		$this->lists->method('getItem')->with(5)->willReturn($existing);
		$this->lists->expects($this->never())->method('addItem');

		$result = $this->draw($this->templateItem('Oat milk'));

		$this->assertTrue($result['reused']);
		$this->assertSame($existing, $result['item']);
	}

	public function testASameNameItemOnAnInventoryIsOnlyUnarchived(): void {
		$existing = $this->targetItem('Oat milk', done: true, archivedAt: 100);
		$this->targetHas([], [$existing]);
		$this->lists->method('getItem')->willReturn($existing);
		$this->lists->expects($this->never())->method('uncheckItems');
		$this->lists->expects($this->once())->method('unarchiveItem')->with(5);

		$result = $this->draw($this->templateItem(), Checklist::KIND_INVENTORY);

		$this->assertTrue($result['reused']);
	}

	public function testTheCopyCarriesEveryFieldButRecurrence(): void {
		$source = $this->templateItem();
		$source->setDescription('Barista');
		$source->setQuantity('2 L');
		$source->setBarcode('4001724819103');
		$source->setRrule('FREQ=WEEKLY');
		$source->setDeleteOnDone(true);
		$this->targetHas([]);
		$this->itemStoreMapper->method('findStoreIdsForItem')->willReturn([3]);
		$prices = [['storeId' => 3, 'priceType' => 'set', 'priceMin' => 1.5, 'priceMax' => null, 'priceCurrency' => 'EUR']];
		$this->itemPriceMapper->method('findForItem')->willReturn($prices);
		$this->itemLabelMapper->method('findLabelIdsForItem')->willReturn([8]);
		$this->labelMapper->method('findByHouse')->willReturn([$this->label(8, 'Vegan', null)]);

		$result = $this->draw($source);

		$this->assertFalse($result['reused']);
		$this->assertSame('Oat milk', $this->added['name']);
		$this->assertSame('Barista', $this->added['description']);
		$this->assertSame('2 L', $this->added['quantity']);
		$this->assertSame('4001724819103', $this->added['barcode']);
		$this->assertSame([3], $this->added['storeIds']);
		$this->assertSame($prices, $this->added['prices']);
		$this->assertSame([8], $this->added['labelIds'], 'a house-wide label is shared');
		$this->assertArrayNotHasKey('rrule', $this->added);
		$this->assertArrayNotHasKey('deleteOnDone', $this->added);
	}

	public function testEmptyFieldsFillFromTheTargetItemDefaults(): void {
		$this->targetHas([]);
		$this->itemStoreMapper->method('findStoreIdsForItem')->willReturn([]);
		$this->itemLabelMapper->method('findLabelIdsForItem')->willReturn([]);
		$this->itemDefaults = $this->createMock(ItemDefaultsService::class);
		$this->itemDefaults->method('forList')->willReturn(ItemDefaults::complete([
			'category' => ['mode' => 'fixed', 'value' => 12],
			'stores' => ['mode' => 'remember', 'value' => [4]],
			'labels' => ['mode' => 'none', 'value' => [9]],
			'quantity' => ['mode' => 'fixed', 'value' => '1'],
			'recurrence' => ['mode' => 'fixed', 'value' => ['kind' => 'recurring', 'rrule' => 'FREQ=DAILY', 'repeatFromCompletion' => true]],
		]));
		$this->rebuild();

		$this->draw($this->templateItem());

		$this->assertSame(12, $this->added['categoryId']);
		$this->assertSame([4], $this->added['storeIds']);
		$this->assertSame([], $this->added['labelIds'], 'a key set to none fills nothing');
		$this->assertSame('1', $this->added['quantity']);
		$this->assertSame('FREQ=DAILY', $this->added['rrule'], 'a checklist starts with its recurrence default');
		$this->assertTrue($this->added['repeatFromCompletion']);
	}

	public function testAnInventoryTakesNoRecurrenceDefault(): void {
		$this->targetHas([]);
		$this->itemDefaults = $this->createMock(ItemDefaultsService::class);
		$this->itemDefaults->method('forList')->willReturn(ItemDefaults::complete([
			'recurrence' => ['mode' => 'fixed', 'value' => ['kind' => 'once', 'rrule' => null, 'repeatFromCompletion' => false]],
		]));
		$this->rebuild();

		$this->draw($this->templateItem(), Checklist::KIND_INVENTORY);

		$this->assertArrayNotHasKey('deleteOnDone', $this->added);
	}

	public function testTheTemplateItemsOwnValuesWinOverDefaults(): void {
		$source = $this->templateItem();
		$source->setQuantity('6');
		$this->targetHas([]);
		$this->itemDefaults = $this->createMock(ItemDefaultsService::class);
		$this->itemDefaults->method('forList')->willReturn(ItemDefaults::complete([
			'quantity' => ['mode' => 'fixed', 'value' => '1'],
		]));
		$this->rebuild();

		$this->draw($source);

		$this->assertSame('6', $this->added['quantity']);
	}

	public function testATemplateCategoryMatchesTheTargetsOwnByName(): void {
		$source = $this->templateItem();
		$source->setCategoryId(30);
		$this->targetHas([]);
		$this->categoryMapper->method('findByHouse')->willReturn([
			$this->category(30, 'Camping', self::TEMPLATE),
			$this->category(31, 'camping', null),
			$this->category(32, ' CAMPING ', self::TARGET),
		]);
		$this->categoryMapper->expects($this->never())->method('insert');

		$this->draw($source);

		$this->assertSame(32, $this->added['categoryId'], "the target's own category wins over a house-wide one");
	}

	public function testATemplateCategoryWithNoMatchIsCopiedOntoTheTarget(): void {
		$source = $this->templateItem();
		$source->setCategoryId(30);
		$this->targetHas([]);
		$this->categoryMapper->method('findByHouse')->willReturn([
			$this->category(30, 'Camping', self::TEMPLATE),
			$this->category(33, 'Camping', 99),
		]);
		$this->categoryMapper->expects($this->once())->method('insert');

		$this->draw($source);

		$this->assertSame(900, $this->added['categoryId'], 'a category scoped to another list does not match');
	}

	public function testTemplateLabelsMatchOrCopyOncePerName(): void {
		$this->targetHas([]);
		$this->itemLabelMapper->method('findLabelIdsForItem')->willReturn([60, 61]);
		$this->labelMapper->method('findByHouse')->willReturn([
			$this->label(60, 'Organic', self::TEMPLATE),
			$this->label(61, 'Gluten-free', self::TEMPLATE),
			$this->label(62, 'organic', null),
		]);
		$this->labelMapper->expects($this->once())->method('insert');

		$this->draw($this->templateItem());

		$this->assertSame([62, 900], $this->added['labelIds']);
	}

	public function testATemplateFieldMatchesByNameAndTypeWithItsOptionByLabel(): void {
		$this->targetHas([]);
		$this->fieldValueMapper->method('findForItem')->willReturn([
			['fieldId' => 70, 'valueOptionId' => 700],
		]);
		$this->fieldDefMapper->method('findByHouse')->willReturn([
			$this->field(70, 'Aisle', self::TEMPLATE),
			$this->field(71, 'Aisle', null, FieldDefinition::TYPE_TEXT),
			$this->field(72, 'aisle', null),
		]);
		$this->fieldOptionMapper->method('findByField')->willReturnCallback(fn (int $id): array => match ($id) {
			70 => [$this->option(700, 70, 'Dairy')],
			72 => [$this->option(720, 72, 'Bakery'), $this->option(721, 72, ' dairy')],
			default => [],
		});
		$this->fieldDefMapper->expects($this->never())->method('insert');

		$this->draw($this->templateItem());

		$this->assertSame([['fieldId' => 72, 'valueOptionId' => 721]], $this->added['customFields']);
	}

	public function testATemplateFieldWithNoMatchIsCopiedWithItsOptions(): void {
		$this->targetHas([]);
		$this->fieldValueMapper->method('findForItem')->willReturn([
			['fieldId' => 70, 'valueOptionId' => 701],
		]);
		$this->fieldDefMapper->method('findByHouse')->willReturn([$this->field(70, 'Aisle', self::TEMPLATE)]);
		$this->fieldOptionMapper->method('findByField')->willReturn([
			$this->option(700, 70, 'Dairy'),
			$this->option(701, 70, 'Bakery'),
		]);

		$this->draw($this->templateItem());

		// The field copy is id 900, its options 901 and 902.
		$this->assertSame([['fieldId' => 900, 'valueOptionId' => 902]], $this->added['customFields']);
	}

	public function testFieldsTheTemplateLeavesUnsetStartFromTheirDefaults(): void {
		$this->targetHas([]);
		$text = $this->field(80, 'Note', null, FieldDefinition::TYPE_TEXT);
		$text->setDefaultText('Fragile');
		$elsewhere = $this->field(81, 'Shelf', 99, FieldDefinition::TYPE_TEXT);
		$elsewhere->setDefaultText('Top');
		$this->fieldDefMapper->method('findByHouse')->willReturn([$text, $elsewhere]);

		$this->draw($this->templateItem());

		$this->assertCount(1, $this->added['customFields']);
		$this->assertSame(80, $this->added['customFields'][0]['fieldId']);
		$this->assertSame('Fragile', $this->added['customFields'][0]['valueText']);
	}

	/**
	 * @return array<string, array{bool, string, bool}>
	 */
	public static function fillSwitchCases(): array {
		return [
			'list on, follow list' => [true, '', true],
			'list off, follow list' => [false, 'list', false],
			'list on, member never' => [true, 'never', false],
			'list off, member always' => [false, 'always', true],
			'list on, member always' => [true, 'always', true],
			'list off, member never' => [false, 'never', false],
		];
	}

	#[DataProvider('fillSwitchCases')]
	public function testTheMemberPreferenceWinsOverTheListFillSwitch(bool $listFills, string $memberFill, bool $fills): void {
		$this->targetHas([]);
		$this->itemStoreMapper->method('findStoreIdsForItem')->willReturn([]);
		$this->itemLabelMapper->method('findLabelIdsForItem')->willReturn([]);
		$text = $this->field(80, 'Note', null, FieldDefinition::TYPE_TEXT);
		$text->setDefaultText('Fragile');
		$this->fieldDefMapper->method('findByHouse')->willReturn([$text]);
		$this->itemDefaults = $this->createMock(ItemDefaultsService::class);
		$this->itemDefaults->method('forList')->willReturn(ItemDefaults::complete([
			'category' => ['mode' => 'fixed', 'value' => 12],
			'stores' => ['mode' => 'fixed', 'value' => [4]],
			'quantity' => ['mode' => 'fixed', 'value' => '1'],
			'recurrence' => ['mode' => 'fixed', 'value' => ['kind' => 'once', 'rrule' => null, 'repeatFromCompletion' => false]],
		]));
		$this->memberFill = $memberFill;
		$this->rebuild();
		$target = $this->makeList(self::TARGET, Checklist::KIND_CHECKLIST);
		$target->setFillFromDefaults($listFills);

		$this->draw($this->templateItem(), target: $target);

		if ($fills) {
			$this->assertSame(12, $this->added['categoryId']);
			$this->assertSame([4], $this->added['storeIds']);
			$this->assertSame('1', $this->added['quantity']);
			$this->assertTrue($this->added['deleteOnDone']);
			$this->assertCount(1, $this->added['customFields']);
		} else {
			$this->assertNull($this->added['categoryId']);
			$this->assertSame([], $this->added['storeIds']);
			$this->assertNull($this->added['quantity']);
			$this->assertArrayNotHasKey('deleteOnDone', $this->added);
			$this->assertSame([], $this->added['customFields'], 'field definition defaults stay off too');
		}
	}

	public function testAListWithoutTheSwitchSetFills(): void {
		$list = new Checklist();

		$this->assertTrue($list->fillsFromDefaults());
		$list->setFillFromDefaults(null);
		$this->assertTrue($list->fillsFromDefaults());
	}

	public function testTheImageIsDuplicatedForTheCopy(): void {
		$source = $this->templateItem();
		$source->setImageFileId(111);
		$source->setImageUploadedBy('alice');
		$this->targetHas([]);

		$this->draw($source, duplicateImage: static fn (int $f, string $o): ?int => $f === 111 && $o === 'alice' ? 222 : null);

		$this->assertSame(222, $this->added['imageFileId']);
		$this->assertSame('bob', $this->added['imageUploadedBy']);
	}

	/**
	 * @param ChecklistItem[] $active
	 * @param ChecklistItem[] $archived
	 */
	private function targetHas(array $active, array $archived = []): void {
		$this->itemMapper->method('findByList')->willReturn($active);
		$this->itemMapper->method('findArchivedByList')->willReturn($archived);
	}

	private function rebuild(): void {
		$this->svc = new TemplateCopyService(
			$this->lists,
			$this->itemMapper,
			$this->categoryMapper,
			$this->labelMapper,
			$this->fieldDefMapper,
			$this->fieldOptionMapper,
			$this->itemStoreMapper,
			$this->itemLabelMapper,
			$this->itemPriceMapper,
			$this->fieldValueMapper,
			$this->itemDefaults,
			new ScopedTaxonomyService($this->categoryMapper, $this->labelMapper, $this->fieldDefMapper, $this->fieldOptionMapper),
			$this->prefs,
		);
	}
}
