<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Db\Category;
use OCA\Pantry\Db\CategoryMapper;
use OCA\Pantry\Db\Checklist;
use OCA\Pantry\Db\FieldDefinition;
use OCA\Pantry\Db\FieldDefinitionMapper;
use OCA\Pantry\Db\FieldOptionMapper;
use OCA\Pantry\Db\Label;
use OCA\Pantry\Db\LabelMapper;
use OCA\Pantry\Db\Store;
use OCA\Pantry\Db\StoreMapper;
use OCA\Pantry\Exception\ForbiddenException;
use OCA\Pantry\Service\ItemDefaults;
use OCA\Pantry\Service\ItemDefaultsService;
use OCA\Pantry\Service\RecurrenceService;
use PHPUnit\Framework\TestCase;

class ItemDefaultsServiceTest extends TestCase {
	private const HOUSE = 1;
	private const LIST = 5;

	private ItemDefaultsService $svc;

	protected function setUp(): void {
		$storeMapper = $this->createMock(StoreMapper::class);
		$storeMapper->method('findByHouse')->willReturn([$this->store(2), $this->store(3)]);

		$categoryMapper = $this->createMock(CategoryMapper::class);
		$categoryMapper->method('findByHouse')->willReturn([
			$this->category(10, null),
			$this->category(11, self::LIST),
			$this->category(12, 99),
		]);

		$labelMapper = $this->createMock(LabelMapper::class);
		$labelMapper->method('findByHouse')->willReturn([
			$this->label(20, null),
			$this->label(21, 99),
		]);

		$fieldDefMapper = $this->createMock(FieldDefinitionMapper::class);
		$fieldDefMapper->method('findByHouse')->willReturn([
			$this->field(30, FieldDefinition::TYPE_SELECT),
			$this->field(31, FieldDefinition::TYPE_DATE, dateMode: FieldDefinition::DATE_RELATIVE),
			$this->field(32, FieldDefinition::TYPE_TEXT, listId: 99),
			$this->field(33, FieldDefinition::TYPE_DATE, dateMode: FieldDefinition::DATE_ABSOLUTE),
		]);

		$optionMapper = $this->createMock(FieldOptionMapper::class);
		$optionMapper->method('findForFields')->willReturn([
			30 => [['id' => 40, 'label' => 'Fridge', 'sortOrder' => 0]],
		]);

		$this->svc = new ItemDefaultsService(
			new RecurrenceService(),
			$storeMapper,
			$categoryMapper,
			$labelMapper,
			$fieldDefMapper,
			$optionMapper,
		);
	}

	public function testAnEditorCanPinStoresCategoryAndQuantity(): void {
		$merged = $this->svc->merge($this->list(), [
			'stores' => ['mode' => 'fixed', 'value' => [2, '3', 2]],
			'category' => ['mode' => 'remember', 'value' => 11],
			'quantity' => ['mode' => 'fixed', 'value' => ' 1 kg '],
		], true);

		$this->assertSame(['mode' => 'fixed', 'value' => [2, 3]], $merged['stores']);
		$this->assertSame(['mode' => 'remember', 'value' => 11], $merged['category']);
		$this->assertSame(['mode' => 'fixed', 'value' => '1 kg'], $merged['quantity']);
	}

	public function testMergingLeavesKeysOutsideThePatchAlone(): void {
		$list = $this->list(['labels' => ['mode' => 'fixed', 'value' => [20]]]);

		$merged = $this->svc->merge($list, ['stores' => ['mode' => 'fixed', 'value' => [2]]], true);

		$this->assertSame(['mode' => 'fixed', 'value' => [20]], $merged['labels']);
	}

	public function testModeNoneClearsAKey(): void {
		$list = $this->list(['stores' => ['mode' => 'fixed', 'value' => [2]]]);

		$merged = $this->svc->merge($list, ['stores' => ['mode' => 'none']], true);

		$this->assertArrayNotHasKey('stores', $merged);
	}

	public function testRememberStartsEmptyUntilSomethingIsAdded(): void {
		$merged = $this->svc->merge($this->list(), ['labels' => ['mode' => 'remember']], true);

		$this->assertSame(['mode' => 'remember'], $merged['labels']);
	}

	public function testQuantityCannotBeRemembered(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->merge($this->list(), ['quantity' => ['mode' => 'remember', 'value' => '2']], true);
	}

	public function testAnUnknownKeyIsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->merge($this->list(), ['price' => ['mode' => 'fixed', 'value' => 3]], true);
	}

	public function testAStoreFromAnotherHouseIsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->merge($this->list(), ['stores' => ['mode' => 'fixed', 'value' => [8]]], true);
	}

	public function testACategoryScopedToAnotherListIsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->merge($this->list(), ['category' => ['mode' => 'fixed', 'value' => 12]], true);
	}

	public function testALabelScopedToAnotherListIsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->merge($this->list(), ['labels' => ['mode' => 'fixed', 'value' => [21]]], true);
	}

	public function testARecurringDefaultWithoutARuleRepeatsWeekly(): void {
		$merged = $this->svc->merge($this->list(), [
			'recurrence' => ['mode' => 'fixed', 'value' => ['kind' => 'recurring']],
		], true);

		$this->assertSame(
			['kind' => 'recurring', 'rrule' => ItemDefaults::DEFAULT_RRULE, 'repeatFromCompletion' => false],
			$merged['recurrence']['value'],
		);
	}

	public function testAnUnparseableRuleIsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->merge($this->list(), [
			'recurrence' => ['mode' => 'fixed', 'value' => ['kind' => 'recurring', 'rrule' => 'every other tuesday']],
		], true);
	}

	public function testWithoutEditRightsARememberedValueCanBeWrittenBack(): void {
		$list = $this->list(['stores' => ['mode' => 'remember', 'value' => [2]]]);

		$merged = $this->svc->merge($list, ['stores' => ['value' => [3]]], false);

		$this->assertSame(['mode' => 'remember', 'value' => [3]], $merged['stores']);
	}

	public function testWithoutEditRightsAPinnedValueCannotChange(): void {
		$list = $this->list(['stores' => ['mode' => 'fixed', 'value' => [2]]]);

		$this->expectException(ForbiddenException::class);
		$this->svc->merge($list, ['stores' => ['mode' => 'remember', 'value' => [3]]], false);
	}

	public function testWithoutEditRightsTheModeCannotChange(): void {
		$list = $this->list(['stores' => ['mode' => 'remember', 'value' => [2]]]);

		$this->expectException(ForbiddenException::class);
		$this->svc->merge($list, ['stores' => ['mode' => 'fixed', 'value' => [3]]], false);
	}

	public function testWithoutEditRightsAnUnsetKeyCannotBeSet(): void {
		$this->expectException(ForbiddenException::class);
		$this->svc->merge($this->list(), ['category' => ['mode' => 'remember', 'value' => 10]], false);
	}

	public function testCustomFieldDefaultsMergeByFieldAndKeepOnlyTheirValueColumn(): void {
		$list = $this->list(['fields' => [
			['fieldId' => 31, 'mode' => 'fixed', 'value' => ['offsetDays' => 3]],
		]]);

		$merged = $this->svc->merge($list, ['fields' => [
			['fieldId' => 30, 'mode' => 'remember', 'value' => ['valueOptionId' => 40, 'valueText' => 'ignored']],
			['fieldId' => 33, 'mode' => 'fixed', 'value' => ['valueDate' => 1_700_000_000, 'notifyEnabled' => true]],
		]], true);

		$this->assertSame([
			['fieldId' => 31, 'mode' => 'fixed', 'value' => ['offsetDays' => 3]],
			['fieldId' => 30, 'mode' => 'remember', 'value' => ['valueOptionId' => 40]],
			['fieldId' => 33, 'mode' => 'fixed', 'value' => ['valueDate' => 1_700_000_000]],
		], $merged['fields']);
	}

	public function testACustomFieldBackOnNoneInheritsItsOwnDefault(): void {
		$list = $this->list(['fields' => [
			['fieldId' => 31, 'mode' => 'fixed', 'value' => ['offsetDays' => 3]],
		]]);

		$merged = $this->svc->merge($list, ['fields' => [['fieldId' => 31, 'mode' => 'none']]], true);

		$this->assertArrayNotHasKey('fields', $merged);
	}

	public function testACustomFieldScopedToAnotherListIsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->merge($this->list(), ['fields' => [
			['fieldId' => 32, 'mode' => 'fixed', 'value' => ['valueText' => 'x']],
		]], true);
	}

	public function testAnOptionFromAnotherFieldIsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->merge($this->list(), ['fields' => [
			['fieldId' => 30, 'mode' => 'fixed', 'value' => ['valueOptionId' => 41]],
		]], true);
	}

	public function testWithoutEditRightsARememberedFieldCanBeWrittenBack(): void {
		$list = $this->list(['fields' => [['fieldId' => 30, 'mode' => 'remember']]]);

		$merged = $this->svc->merge($list, ['fields' => [
			['fieldId' => 30, 'value' => ['valueOptionId' => 40]],
		]], false);

		$this->assertSame([['fieldId' => 30, 'mode' => 'remember', 'value' => ['valueOptionId' => 40]]], $merged['fields']);
	}

	public function testReadingFillsEveryKey(): void {
		$defaults = $this->svc->forList($this->list());

		$this->assertSame(['mode' => 'none'], $defaults['stores']);
		$this->assertSame(['mode' => 'none'], $defaults['quantity']);
		$this->assertSame([], $defaults['fields']);
	}

	public function testReadingDropsReferencesThatNoLongerResolve(): void {
		$defaults = $this->svc->forList($this->list([
			'stores' => ['mode' => 'fixed', 'value' => [2, 8]],
			'labels' => ['mode' => 'remember', 'value' => [20, 21, 22]],
			'category' => ['mode' => 'fixed', 'value' => 12],
			'fields' => [
				['fieldId' => 30, 'mode' => 'fixed', 'value' => ['valueOptionId' => 41]],
				['fieldId' => 30, 'mode' => 'remember', 'value' => ['valueOptionId' => 41]],
				['fieldId' => 99, 'mode' => 'fixed', 'value' => ['valueText' => 'gone']],
				['fieldId' => 31, 'mode' => 'fixed', 'value' => ['offsetDays' => 3]],
			],
		]));

		$this->assertSame([2], $defaults['stores']['value']);
		$this->assertSame([20], $defaults['labels']['value']);
		$this->assertNull($defaults['category']['value']);
		$this->assertSame([
			['fieldId' => 30, 'mode' => 'remember', 'value' => null],
			['fieldId' => 31, 'mode' => 'fixed', 'value' => ['offsetDays' => 3]],
		], $defaults['fields']);
	}

	/**
	 * @param array<string, mixed> $defaults
	 */
	private function list(array $defaults = []): Checklist {
		$list = new Checklist();
		$list->setId(self::LIST);
		$list->setHouseId(self::HOUSE);
		$list->setItemDefaults(ItemDefaults::encode($defaults));
		return $list;
	}

	private function store(int $id): Store {
		$store = new Store();
		$store->setId($id);
		$store->setHouseId(self::HOUSE);
		return $store;
	}

	private function category(int $id, ?int $listId): Category {
		$category = new Category();
		$category->setId($id);
		$category->setHouseId(self::HOUSE);
		$category->setListId($listId);
		return $category;
	}

	private function label(int $id, ?int $listId): Label {
		$label = new Label();
		$label->setId($id);
		$label->setHouseId(self::HOUSE);
		$label->setListId($listId);
		return $label;
	}

	private function field(int $id, string $type, ?int $listId = null, ?string $dateMode = null): FieldDefinition {
		$def = new FieldDefinition();
		$def->setId($id);
		$def->setHouseId(self::HOUSE);
		$def->setType($type);
		$def->setListId($listId);
		$def->setDateMode($dateMode);
		return $def;
	}
}
