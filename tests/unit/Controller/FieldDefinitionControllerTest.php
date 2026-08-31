<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Controller;

use OCA\Pantry\Controller\FieldDefinitionController;
use OCA\Pantry\Service\FieldDefinitionService;
use OCA\Pantry\Service\HouseAuthService;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FieldDefinitionControllerTest extends TestCase {
	/** @var FieldDefinitionService&MockObject */
	private FieldDefinitionService $fields;
	/** @var HouseAuthService&MockObject */
	private HouseAuthService $auth;
	/** @var IRequest&MockObject */
	private IRequest $request;
	private FieldDefinitionController $controller;

	protected function setUp(): void {
		$this->fields = $this->createMock(FieldDefinitionService::class);
		$this->auth = $this->createMock(HouseAuthService::class);
		$this->request = $this->createMock(IRequest::class);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);

		$this->controller = new FieldDefinitionController(
			'pantry',
			$this->request,
			$this->fields,
			$this->auth,
			$session,
		);
	}

	public function testIndexReturnsDefinitions(): void {
		$defs = [['id' => 1, 'name' => 'Aisle', 'type' => 'text']];
		$this->auth->expects($this->once())->method('requireMember')->with(3, 'alice');
		$this->fields->method('listForHouse')->with(3)->willReturn($defs);

		$this->assertSame($defs, $this->controller->index(3)->getData());
	}

	public function testCreatePassesAssembledData(): void {
		$this->fields->expects($this->once())
			->method('create')
			->with(3, $this->callback(static fn (array $d): bool => $d['name'] === 'Aisle'
				&& $d['type'] === 'select'
				&& $d['options'] === [['label' => 'A']]))
			->willReturn(['id' => 9, 'name' => 'Aisle', 'type' => 'select']);

		$response = $this->controller->create(
			3, 'Aisle', 'select', null, null, false, null, null, false,
			null, null, false, 0, null, false, [['label' => 'A']],
		);

		$this->assertSame(9, $response->getData()['id']);
	}

	public function testUpdateBuildsPatchFromPresentParamsOnly(): void {
		// Only name + hint present; other config keys stay out of the patch.
		$this->request->method('getParams')->willReturn([
			'houseId' => 3, 'fieldId' => 9, 'name' => 'Shelf', 'hint' => 'e.g. B12',
		]);
		$this->auth->expects($this->once())->method('requireMember')->with(3, 'alice');
		$this->fields->expects($this->once())->method('assertInHouse')->with(9, 3);
		$this->fields->expects($this->once())
			->method('update')
			->with(9, ['name' => 'Shelf', 'hint' => 'e.g. B12'])
			->willReturn(['id' => 9, 'name' => 'Shelf']);

		$response = $this->controller->update(3, 9, 'Shelf');
		$this->assertSame('Shelf', $response->getData()['name']);
	}

	public function testDestroyAssertsThenDeletes(): void {
		$this->auth->expects($this->once())->method('requireMember')->with(3, 'alice');
		$this->fields->expects($this->once())->method('assertInHouse')->with(9, 3);
		$this->fields->expects($this->once())->method('delete')->with(9);

		$this->assertSame(['success' => true], $this->controller->destroy(3, 9)->getData());
	}

	public function testDeleteOptionDelegates(): void {
		$this->auth->expects($this->once())->method('requireMember')->with(3, 'alice');
		$this->fields->expects($this->once())->method('assertInHouse')->with(9, 3);
		$this->fields->expects($this->once())
			->method('deleteOption')
			->with(9, 12, 'remap', 13)
			->willReturn(['id' => 9, 'name' => 'Size', 'type' => 'select']);

		$response = $this->controller->deleteOption(3, 9, 12, 'remap', 13);
		$this->assertSame('Size', $response->getData()['name']);
	}

	public function testReorderDelegates(): void {
		$items = [['id' => 1, 'sortOrder' => 0]];
		$this->auth->expects($this->once())->method('requireMember')->with(3, 'alice');
		$this->fields->expects($this->once())->method('reorder')->with(3, $items);

		$this->assertSame(['success' => true], $this->controller->reorder(3, $items)->getData());
	}
}
