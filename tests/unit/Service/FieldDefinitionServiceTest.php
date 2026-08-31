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
use OCA\Pantry\Exception\NotFoundException;
use OCA\Pantry\Service\FieldDefinitionService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FieldDefinitionServiceTest extends TestCase {
	/** @var FieldDefinitionMapper&MockObject */
	private FieldDefinitionMapper $mapper;
	/** @var FieldOptionMapper&MockObject */
	private FieldOptionMapper $optionMapper;
	/** @var ChecklistMapper&MockObject */
	private ChecklistMapper $listMapper;
	private FieldDefinitionService $svc;

	protected function setUp(): void {
		$this->mapper = $this->createMock(FieldDefinitionMapper::class);
		$this->optionMapper = $this->createMock(FieldOptionMapper::class);
		$this->listMapper = $this->createMock(ChecklistMapper::class);
		$this->svc = new FieldDefinitionService($this->mapper, $this->optionMapper, $this->listMapper);
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
}
