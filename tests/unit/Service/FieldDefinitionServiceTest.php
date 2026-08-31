<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Db\Checklist;
use OCA\Pantry\Db\ChecklistMapper;
use OCA\Pantry\Db\FieldDefinition;
use OCA\Pantry\Db\FieldDefinitionMapper;
use OCA\Pantry\Db\FieldOption;
use OCA\Pantry\Db\FieldOptionMapper;
use OCA\Pantry\Db\FieldValueMapper;
use OCA\Pantry\Exception\NotFoundException;
use OCA\Pantry\Service\CustomFieldReminderService;
use OCA\Pantry\Service\FieldDefinitionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FieldDefinitionServiceTest extends TestCase {
	/** @var FieldDefinitionMapper&MockObject */
	private FieldDefinitionMapper $mapper;
	/** @var FieldOptionMapper&MockObject */
	private FieldOptionMapper $optionMapper;
	/** @var FieldValueMapper&MockObject */
	private FieldValueMapper $valueMapper;
	/** @var ChecklistMapper&MockObject */
	private ChecklistMapper $listMapper;
	/** @var CustomFieldReminderService&MockObject */
	private CustomFieldReminderService $reminders;
	/** @var IDBConnection&MockObject */
	private IDBConnection $db;
	private FieldDefinitionService $svc;

	protected function setUp(): void {
		$this->mapper = $this->createMock(FieldDefinitionMapper::class);
		$this->reminders = $this->createMock(CustomFieldReminderService::class);
		$this->optionMapper = $this->createMock(FieldOptionMapper::class);
		$this->valueMapper = $this->createMock(FieldValueMapper::class);
		$this->listMapper = $this->createMock(ChecklistMapper::class);
		$this->db = $this->createMock(IDBConnection::class);
		$this->svc = new FieldDefinitionService(
			$this->mapper,
			$this->optionMapper,
			$this->valueMapper,
			$this->listMapper,
			$this->reminders,
			$this->db,
		);
	}

	private static function withId(object $entity, int $id): object {
		$ref = new \ReflectionProperty($entity, 'id');
		$ref->setValue($entity, $id);
		return $entity;
	}

	private function makeList(int $id, int $houseId): Checklist {
		$l = new Checklist();
		$l->setHouseId($houseId);
		return self::withId($l, $id);
	}

	private function makeOption(int $id, string $label, int $sortOrder): FieldOption {
		$o = new FieldOption();
		$o->setLabel($label);
		$o->setSortOrder($sortOrder);
		return self::withId($o, $id);
	}

	private function makeDef(array $overrides = []): FieldDefinition {
		$d = new FieldDefinition();
		$d->setHouseId($overrides['houseId'] ?? 1);
		$d->setName($overrides['name'] ?? 'Aisle');
		$d->setType($overrides['type'] ?? FieldDefinition::TYPE_TEXT);
		$d->setSortOrder($overrides['sortOrder'] ?? 0);
		$d->setCreatedAt(1000);
		$d->setUpdatedAt(1000);
		if (isset($overrides['listId'])) {
			$d->setListId($overrides['listId']);
		}
		if (isset($overrides['id'])) {
			self::withId($d, $overrides['id']);
		}
		return $d;
	}

	public function testCreateTextAppendsAfterHighestSortOrder(): void {
		$this->mapper->method('findByHouseListAndName')->willReturn(null);
		$this->mapper->method('findMaxSortOrder')->with(1)->willReturn(4);
		$this->mapper->method('insert')->willReturnCallback(fn (FieldDefinition $d) => self::withId($d, 7));
		$this->optionMapper->method('findByField')->willReturn([]);

		$result = $this->svc->create(1, ['name' => 'Aisle', 'type' => 'text', 'multiline' => true]);

		$this->assertSame(5, $result['sortOrder']);
		$this->assertSame('text', $result['type']);
		$this->assertTrue($result['multiline']);
		$this->assertSame([], $result['options']);
	}

	public function testCreateRejectsUnknownType(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->create(1, ['name' => 'X', 'type' => 'rating']);
	}

	public function testCreateRejectsEmptyName(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->create(1, ['name' => '   ', 'type' => 'text']);
	}

	public function testCreateRejectsDuplicateInScope(): void {
		$this->mapper->method('findByHouseListAndName')->with(1, null, 'Aisle')
			->willReturn($this->makeDef(['id' => 3]));

		$this->expectException(\InvalidArgumentException::class);
		$this->svc->create(1, ['name' => 'Aisle', 'type' => 'text']);
	}

	public function testCreateScopedFieldValidatesList(): void {
		$this->listMapper->method('findById')->with(9, true)->willReturn($this->makeList(9, 1));
		$this->mapper->method('findByHouseListAndName')->willReturn(null);
		$this->mapper->method('findMaxSortOrder')->willReturn(-1);
		$this->mapper->method('insert')->willReturnCallback(fn (FieldDefinition $d) => self::withId($d, 7));
		$this->optionMapper->method('findByField')->willReturn([]);

		$result = $this->svc->create(1, ['name' => 'Aisle', 'type' => 'text', 'listId' => 9]);

		$this->assertSame(9, $result['listId']);
	}

	public function testCreateRejectsListFromAnotherHouse(): void {
		$this->listMapper->method('findById')->with(9, true)->willReturn($this->makeList(9, 99));

		$this->expectException(\InvalidArgumentException::class);
		$this->svc->create(1, ['name' => 'Aisle', 'type' => 'text', 'listId' => 9]);
	}

	public function testCreateSelectInsertsOptions(): void {
		$this->mapper->method('findByHouseListAndName')->willReturn(null);
		$this->mapper->method('findMaxSortOrder')->willReturn(-1);
		$this->mapper->method('insert')->willReturnCallback(fn (FieldDefinition $d) => self::withId($d, 7));
		$this->optionMapper->expects($this->exactly(2))->method('insert');
		$this->optionMapper->method('findByField')->with(7)->willReturn([
			$this->makeOption(1, 'Small', 0),
			$this->makeOption(2, 'Large', 1),
		]);

		$result = $this->svc->create(1, [
			'name' => 'Size',
			'type' => 'select',
			'options' => [
				['label' => 'Small'],
				['label' => 'Large'],
				['label' => '   '], // blank dropped
			],
		]);

		$this->assertSame('select', $result['type']);
	}

	public function testUpdateRenameChecksUniqueness(): void {
		$def = $this->makeDef(['id' => 5, 'houseId' => 1, 'name' => 'Aisle']);
		$this->mapper->method('findById')->with(5)->willReturn($def);
		$this->mapper->method('findByHouseListAndName')->with(1, null, 'Shelf')
			->willReturn($this->makeDef(['id' => 8]));

		$this->expectException(\InvalidArgumentException::class);
		$this->svc->update(5, ['name' => 'Shelf']);
	}

	public function testUpdateSelectOptionsDiff(): void {
		$def = $this->makeDef(['id' => 5, 'houseId' => 1, 'type' => 'select']);
		$this->mapper->method('findById')->with(5)->willReturn($def);
		$this->mapper->method('findByHouseListAndName')->willReturn(null);
		$this->mapper->method('update')->willReturnArgument(0);

		$keep = $this->makeOption(11, 'Old', 0);
		$drop = $this->makeOption(12, 'Gone', 1);
		// First call (diff) sees both existing; serialize() re-reads afterwards.
		$this->optionMapper->method('findByField')->willReturn([$keep, $drop], []);

		$this->optionMapper->expects($this->once())->method('update'); // the kept/renamed one
		$this->optionMapper->expects($this->once())->method('insert'); // the brand-new one
		$this->optionMapper->expects($this->once())->method('delete')->with($drop);

		$this->svc->update(5, ['options' => [
			['id' => 11, 'label' => 'Renamed', 'sortOrder' => 0],
			['label' => 'New', 'sortOrder' => 1],
		]]);
	}

	public function testDiffDeleteRejectsInUseOption(): void {
		$def = $this->makeDef(['id' => 5, 'houseId' => 1, 'type' => 'select']);
		$this->mapper->method('findById')->with(5)->willReturn($def);
		$this->mapper->method('findByHouseListAndName')->willReturn(null);

		$drop = $this->makeOption(12, 'Gone', 1);
		$this->optionMapper->method('findByField')->willReturn([$drop]);
		$this->valueMapper->method('countByOptions')->with([12])->willReturn([12 => 3]);
		$this->optionMapper->expects($this->never())->method('delete');

		$this->expectException(\InvalidArgumentException::class);
		$this->svc->update(5, ['options' => []]);
	}

	public function testDeleteUnusedOptionRemovesOutright(): void {
		$def = $this->makeDef(['id' => 5, 'houseId' => 1, 'type' => 'select']);
		$this->mapper->method('findById')->with(5)->willReturn($def);
		$opt = $this->makeOption(12, 'Gone', 1);
		$this->optionMapper->method('findByField')->willReturn([$opt], []);
		$this->valueMapper->method('countByOptions')->willReturn([]);

		$this->optionMapper->expects($this->once())->method('delete')->with($opt);
		$this->valueMapper->expects($this->never())->method('remapOption');
		$this->valueMapper->expects($this->never())->method('clearOption');
		$this->db->expects($this->once())->method('commit');

		$this->svc->deleteOption(5, 12, null, null);
	}

	public function testDeleteInUseOptionRequiresAction(): void {
		$def = $this->makeDef(['id' => 5, 'houseId' => 1, 'type' => 'select']);
		$this->mapper->method('findById')->with(5)->willReturn($def);
		$this->optionMapper->method('findByField')->willReturn([$this->makeOption(12, 'Gone', 1)]);
		$this->valueMapper->method('countByOptions')->with([12])->willReturn([12 => 2]);

		$this->expectException(\InvalidArgumentException::class);
		$this->svc->deleteOption(5, 12, null, null);
	}

	public function testDeleteInUseOptionRemap(): void {
		$def = $this->makeDef(['id' => 5, 'houseId' => 1, 'type' => 'select']);
		$def->setDefaultOptionId(12);
		$this->mapper->method('findById')->with(5)->willReturn($def);
		$drop = $this->makeOption(12, 'Gone', 0);
		$keep = $this->makeOption(13, 'Kept', 1);
		$this->optionMapper->method('findByField')->willReturn([$drop, $keep], [$keep]);
		$this->valueMapper->method('countByOptions')->willReturn([12 => 4]);

		$this->valueMapper->expects($this->once())->method('remapOption')->with(12, 13);
		$this->valueMapper->expects($this->never())->method('clearOption');
		$this->optionMapper->expects($this->once())->method('delete')->with($drop);
		// The default pointed at the deleted option → follows the remap target.
		$this->mapper->expects($this->once())->method('update')->with($this->callback(
			static fn (FieldDefinition $d): bool => $d->getDefaultOptionId() === 13,
		));
		$this->db->expects($this->once())->method('commit');

		$this->svc->deleteOption(5, 12, 'remap', 13);
	}

	public function testDeleteInUseOptionRemapRejectsForeignTarget(): void {
		$def = $this->makeDef(['id' => 5, 'houseId' => 1, 'type' => 'select']);
		$this->mapper->method('findById')->with(5)->willReturn($def);
		$this->optionMapper->method('findByField')->willReturn([$this->makeOption(12, 'Gone', 0)]);
		$this->valueMapper->method('countByOptions')->willReturn([12 => 1]);
		$this->db->expects($this->never())->method('beginTransaction');

		$this->expectException(\InvalidArgumentException::class);
		$this->svc->deleteOption(5, 12, 'remap', 999);
	}

	public function testDeleteInUseOptionClear(): void {
		$def = $this->makeDef(['id' => 5, 'houseId' => 1, 'type' => 'select']);
		$def->setDefaultOptionId(12);
		$this->mapper->method('findById')->with(5)->willReturn($def);
		$drop = $this->makeOption(12, 'Gone', 0);
		$this->optionMapper->method('findByField')->willReturn([$drop], []);
		$this->valueMapper->method('countByOptions')->willReturn([12 => 4]);

		$this->valueMapper->expects($this->once())->method('clearOption')->with(12);
		$this->valueMapper->expects($this->never())->method('remapOption');
		$this->optionMapper->expects($this->once())->method('delete')->with($drop);
		// The default pointed at the deleted option → cleared.
		$this->mapper->expects($this->once())->method('update')->with($this->callback(
			static fn (FieldDefinition $d): bool => $d->getDefaultOptionId() === null,
		));
		$this->db->expects($this->once())->method('commit');

		$this->svc->deleteOption(5, 12, 'clear', null);
	}

	public function testDeleteOptionRejectsUnknownOption(): void {
		$def = $this->makeDef(['id' => 5, 'houseId' => 1, 'type' => 'select']);
		$this->mapper->method('findById')->with(5)->willReturn($def);
		$this->optionMapper->method('findByField')->willReturn([$this->makeOption(12, 'A', 0)]);

		$this->expectException(NotFoundException::class);
		$this->svc->deleteOption(5, 99, null, null);
	}

	public function testDeleteSoftDeletes(): void {
		$def = $this->makeDef(['id' => 5, 'houseId' => 1]);
		$this->mapper->method('findById')->with(5)->willReturn($def);
		$this->mapper->expects($this->once())->method('update')->with($this->callback(
			static fn (FieldDefinition $d): bool => $d->getDeletedAt() !== null,
		));
		$this->mapper->expects($this->never())->method('delete');

		$this->svc->delete(5);
	}

	public function testReorderUpdatesEachInHouseOnly(): void {
		$d1 = $this->makeDef(['id' => 1, 'houseId' => 1]);
		$foreign = $this->makeDef(['id' => 3, 'houseId' => 99]);
		$this->mapper->method('findById')->willReturnCallback(fn (int $id) => match ($id) {
			1 => $d1,
			3 => $foreign,
			default => throw new DoesNotExistException(''),
		});
		$this->mapper->expects($this->once())->method('update');

		$this->svc->reorder(1, [
			['id' => 1, 'sortOrder' => 2],
			['id' => 3, 'sortOrder' => 0], // foreign, ignored
		]);

		$this->assertSame(2, $d1->getSortOrder());
	}

	public function testAssertInHouseRejectsForeign(): void {
		$this->mapper->method('findById')->with(5)->willReturn($this->makeDef(['id' => 5, 'houseId' => 99]));
		$this->expectException(NotFoundException::class);
		$this->svc->assertInHouse(5, 1);
	}

	public function testSanitizeCoercesEachTypeToItsColumn(): void {
		$this->mapper->method('findByHouse')->with(1)->willReturn([
			$this->makeDef(['id' => 1, 'houseId' => 1, 'type' => 'text']),
			$this->makeDef(['id' => 2, 'houseId' => 1, 'type' => 'number']),
			$this->makeDef(['id' => 3, 'houseId' => 1, 'type' => 'checkbox']),
			$this->makeDef(['id' => 4, 'houseId' => 1, 'type' => 'select']),
			$this->makeDef(['id' => 5, 'houseId' => 1, 'type' => 'date']),
		]);

		$out = $this->svc->sanitizeValuesForItem(1, 7, [
			['fieldId' => 1, 'valueText' => 'Aisle 4'],
			['fieldId' => 2, 'valueNumber' => '3.5'],
			['fieldId' => 3, 'valueBool' => true],
			['fieldId' => 4, 'valueOptionId' => 42],
			['fieldId' => 5, 'valueDate' => 1788000000, 'offsetDays' => 3, 'notifyOverride' => true, 'notifyEnabled' => true, 'notifyLeadDays' => 1],
		]);

		$this->assertSame('Aisle 4', $out[0]['valueText']);
		$this->assertSame(3.5, $out[1]['valueNumber']);
		$this->assertTrue($out[2]['valueBool']);
		$this->assertSame(42, $out[3]['valueOptionId']);
		$this->assertSame(1788000000, $out[4]['valueDate']);
		$this->assertSame(3, $out[4]['offsetDays']);
		$this->assertTrue($out[4]['notifyEnabled']);
		// A number value does not leak into unrelated typed columns.
		$this->assertNull($out[1]['valueText']);
	}

	public function testSanitizeRejectsUnknownField(): void {
		$this->mapper->method('findByHouse')->willReturn([]);
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->sanitizeValuesForItem(1, 7, [['fieldId' => 99, 'valueText' => 'x']]);
	}

	public function testSanitizeRejectsFieldScopedToAnotherList(): void {
		$this->mapper->method('findByHouse')->willReturn([
			$this->makeDef(['id' => 1, 'houseId' => 1, 'type' => 'text', 'listId' => 8]),
		]);
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->sanitizeValuesForItem(1, 7, [['fieldId' => 1, 'valueText' => 'x']]);
	}

	public function testSanitizeAllowsHouseWideFieldOnAnyList(): void {
		$this->mapper->method('findByHouse')->willReturn([
			$this->makeDef(['id' => 1, 'houseId' => 1, 'type' => 'text']), // null list = house-wide
		]);
		$out = $this->svc->sanitizeValuesForItem(1, 7, [['fieldId' => 1, 'valueText' => 'ok']]);
		$this->assertCount(1, $out);
		$this->assertSame('ok', $out[0]['valueText']);
	}

	public function testSanitizeDedupsByFieldIdLastWins(): void {
		$this->mapper->method('findByHouse')->willReturn([
			$this->makeDef(['id' => 1, 'houseId' => 1, 'type' => 'text']),
		]);
		$out = $this->svc->sanitizeValuesForItem(1, 7, [
			['fieldId' => 1, 'valueText' => 'first'],
			['fieldId' => 1, 'valueText' => 'second'],
		]);
		$this->assertCount(1, $out);
		$this->assertSame('second', $out[0]['valueText']);
	}
}
