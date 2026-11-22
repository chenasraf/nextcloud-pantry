<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Controller;

use OCA\Pantry\Controller\ImageController;
use OCA\Pantry\Db\ChecklistItemMapper;
use OCA\Pantry\Db\PhotoMapper;
use OCA\Pantry\Service\HouseAuthService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\Files\IRootFolder;
use OCP\IPreview;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ImageControllerTest extends TestCase {
	/** @var ChecklistItemMapper&MockObject */
	private ChecklistItemMapper $itemMapper;
	/** @var IRootFolder&MockObject */
	private IRootFolder $rootFolder;
	private ImageController $controller;

	protected function setUp(): void {
		$this->itemMapper = $this->createMock(ChecklistItemMapper::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('bob');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);

		$this->controller = new ImageController(
			'pantry',
			$this->createMock(IRequest::class),
			$this->createMock(HouseAuthService::class),
			$this->createMock(PhotoMapper::class),
			$this->itemMapper,
			$this->rootFolder,
			$this->createMock(IPreview::class),
			$session,
		);
	}

	public function testItemPreviewRefusesFileThatIsNoItemImageInTheHouse(): void {
		$this->itemMapper->expects($this->once())
			->method('houseHasItemImage')
			->with(1, 42, 'alice')
			->willReturn(false);
		$this->rootFolder->expects($this->never())->method('getUserFolder');

		$resp = $this->controller->itemImagePreview(1, 42, 'alice');

		$this->assertInstanceOf(DataResponse::class, $resp);
		$this->assertSame(Http::STATUS_NOT_FOUND, $resp->getStatus());
	}
}
