<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Controller;

use OCA\Pantry\Controller\ShoppingSessionController;
use OCA\Pantry\Db\ItemStoreMapper;
use OCA\Pantry\Db\ShoppingSession;
use OCA\Pantry\Service\ChecklistService;
use OCA\Pantry\Service\HouseAuthService;
use OCA\Pantry\Service\PermissionService;
use OCA\Pantry\Service\ShoppingSessionService;
use OCA\Pantry\Service\StockLinkService;
use OCA\Pantry\Service\StoreService;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Closing a trip with the items' put-away picks.
 */
class ShoppingSessionControllerTest extends TestCase {
	/** @var ShoppingSessionService&MockObject */
	private ShoppingSessionService $sessions;
	/** @var PermissionService&MockObject */
	private PermissionService $permissions;
	private ShoppingSessionController $controller;
	/** @var array<int, list<array{listId: int, amount: float|null}>>|null What the service was asked to put away. */
	private ?array $closedWith = null;

	protected function setUp(): void {
		$session = new ShoppingSession();
		$session->setId(5);
		$session->setHouseId(1);
		$session->setUserId('alice');

		$this->sessions = $this->createMock(ShoppingSessionService::class);
		$this->sessions->method('get')->willReturn($session);
		$this->sessions->method('isMember')->willReturn(true);
		$this->sessions->method('composeDto')->willReturn(['id' => 5]);
		$this->sessions->method('close')->willReturnCallback(function (ShoppingSession $s, array $putAway, ?string $uid): ShoppingSession {
			$this->closedWith = $putAway;
			return $s;
		});

		$this->permissions = $this->createMock(PermissionService::class);
		$this->permissions->method('can')->willReturn(true);
		$this->permissions->method('canAccessList')->willReturn(true);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$this->controller = new ShoppingSessionController(
			'pantry',
			$this->createMock(IRequest::class),
			$this->sessions,
			$this->createMock(ChecklistService::class),
			$this->createMock(StoreService::class),
			$this->permissions,
			$this->createMock(HouseAuthService::class),
			$this->createMock(ItemStoreMapper::class),
			$userSession,
			$this->createMock(LoggerInterface::class),
			$this->createMock(StockLinkService::class),
		);
	}

	public function testClosingWithoutPutAwayPassesNoPicks(): void {
		$this->controller->close(1, 5);

		$this->assertSame([], $this->closedWith);
	}

	public function testPutAwayPicksAreMergedPerInventoryAndZeroesDropped(): void {
		$this->controller->close(1, 5, [
			['itemId' => 10, 'storages' => [
				['listId' => 20, 'amount' => 4],
				['listId' => 21, 'amount' => '2.5'],
				['listId' => 20, 'amount' => 2],
				['listId' => 22, 'amount' => 0],
			]],
			['itemId' => 11, 'storages' => [['listId' => 20]]],
			['itemId' => 12, 'storages' => []],
		]);

		$this->assertSame([
			10 => [['listId' => 20, 'amount' => 6.0], ['listId' => 21, 'amount' => 2.5]],
			11 => [['listId' => 20, 'amount' => null]],
			12 => [],
		], $this->closedWith);
	}

	public function testPuttingAwayNeedsTheRightToTickItems(): void {
		$permissions = $this->createMock(PermissionService::class);
		$permissions->method('can')->willReturnCallback(static fn (int $h, string $u, string $cap): bool => $cap !== 'canCheckItems');
		$this->replacePermissions($permissions);

		$this->expectException(OCSForbiddenException::class);
		$this->controller->close(1, 5, [['itemId' => 10, 'storages' => [['listId' => 20, 'amount' => 1]]]]);
	}

	public function testPuttingAwayNeedsAccessToEveryInventoryPicked(): void {
		$permissions = $this->createMock(PermissionService::class);
		$permissions->method('can')->willReturn(true);
		$permissions->method('canAccessList')->willReturnCallback(static fn (int $h, string $u, int $listId): bool => $listId !== 21);
		$this->replacePermissions($permissions);

		$this->expectException(OCSForbiddenException::class);
		$this->controller->close(1, 5, [['itemId' => 10, 'storages' => [['listId' => 20, 'amount' => 1], ['listId' => 21, 'amount' => 1]]]]);
	}

	public function testANegativeAmountIsRefused(): void {
		$this->expectException(OCSBadRequestException::class);
		$this->controller->close(1, 5, [['itemId' => 10, 'storages' => [['listId' => 20, 'amount' => -1]]]]);
	}

	public function testAnEntryWithoutAnItemIsRefused(): void {
		$this->expectException(OCSBadRequestException::class);
		$this->controller->close(1, 5, [['storages' => []]]);
	}

	private function replacePermissions(PermissionService $permissions): void {
		$ref = new \ReflectionProperty($this->controller, 'permissions');
		$ref->setValue($this->controller, $permissions);
	}
}
