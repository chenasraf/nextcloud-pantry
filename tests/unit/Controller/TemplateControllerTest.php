<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Controller;

use OCA\Pantry\Activity\ActivityPublisher;
use OCA\Pantry\Controller\ChecklistController;
use OCA\Pantry\Controller\ListKindOCSController;
use OCA\Pantry\Controller\TemplateController;
use OCA\Pantry\Db\Checklist;
use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Db\FieldValueMapper;
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
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TemplateControllerTest extends TestCase {
	/** @var ChecklistService&MockObject */
	private ChecklistService $lists;

	protected function setUp(): void {
		$this->lists = $this->createMock(ChecklistService::class);
	}

	/**
	 * @template T of ListKindOCSController
	 * @param class-string<T> $class
	 * @return T
	 */
	private function makeController(string $class): ListKindOCSController {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$itemDefaults = $this->createMock(ItemDefaultsService::class);
		$itemDefaults->method('forList')->willReturn([]);
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
			$this->createMock(HouseService::class),
			$this->createMock(ImageService::class),
			$this->createMock(NotificationService::class),
			$this->createMock(ActivityPublisher::class),
			$this->createMock(PrefsService::class),
			$this->createMock(PermissionService::class),
			$this->createMock(ShareService::class),
			$itemDefaults,
			$session,
			$this->createMock(LoggerInterface::class),
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
		foreach (['lastCompletedAt', 'deleteOnDoneDefault', 'defaultRecurrenceMode', 'defaultRrule'] as $key) {
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
}
