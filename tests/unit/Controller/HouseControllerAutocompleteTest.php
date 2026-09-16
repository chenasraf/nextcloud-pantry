<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Controller;

use OCA\Pantry\Controller\HouseController;
use OCA\Pantry\Db\HouseMemberRoleMapper;
use OCA\Pantry\Service\HouseAuthService;
use OCA\Pantry\Service\HouseService;
use OCA\Pantry\Service\PermissionService;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class HouseControllerAutocompleteTest extends TestCase {
	/** @var IUserManager&MockObject */
	private IUserManager $userManager;
	private HouseController $controller;

	protected function setUp(): void {
		$this->userManager = $this->createMock(IUserManager::class);

		$current = $this->user('alice', 'Alice');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($current);

		$this->controller = new HouseController(
			'pantry',
			$this->createMock(IRequest::class),
			$this->createMock(HouseService::class),
			$this->createMock(HouseAuthService::class),
			$this->createMock(PermissionService::class),
			$this->createMock(HouseMemberRoleMapper::class),
			$session,
			$this->userManager,
		);
	}

	/**
	 * @return IUser&MockObject
	 */
	private function user(string $uid, string $displayName): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$user->method('getDisplayName')->willReturn($displayName);
		return $user;
	}

	public function testReturnsDisplayNameMatches(): void {
		$this->userManager->method('searchDisplayName')->willReturn([
			$this->user('bob', 'Bob Smith'),
			$this->user('carol', 'Carol Smith'),
		]);
		$this->userManager->method('get')->willReturn(null);

		$this->assertSame([
			['id' => 'bob', 'label' => 'Bob Smith'],
			['id' => 'carol', 'label' => 'Carol Smith'],
		], $this->controller->autocompleteUsers('smith')->getData());
	}

	public function testExactAccountIdLeadsResultsWhenDisplayNameDiffers(): void {
		$this->userManager->method('searchDisplayName')->willReturn([
			$this->user('carol', 'Carol Smith'),
		]);
		$this->userManager->method('get')->with('dave')->willReturn(
			$this->user('dave', 'Renamed Entirely'),
		);

		$this->assertSame([
			['id' => 'dave', 'label' => 'Renamed Entirely'],
			['id' => 'carol', 'label' => 'Carol Smith'],
		], $this->controller->autocompleteUsers('dave')->getData());
	}

	public function testExactAccountIdIsNotRepeatedWhenAlsoMatchedByName(): void {
		$this->userManager->method('searchDisplayName')->willReturn([
			$this->user('bob', 'bob'),
		]);
		$this->userManager->method('get')->with('bob')->willReturn($this->user('bob', 'bob'));

		$this->assertSame(
			[['id' => 'bob', 'label' => 'bob']],
			$this->controller->autocompleteUsers('bob')->getData(),
		);
	}

	public function testExcludesCurrentAccount(): void {
		$this->userManager->method('searchDisplayName')->willReturn([
			$this->user('alice', 'Alice'),
			$this->user('bob', 'Bob'),
		]);
		$this->userManager->method('get')->willReturn(null);

		$this->assertSame(
			[['id' => 'bob', 'label' => 'Bob']],
			$this->controller->autocompleteUsers('a')->getData(),
		);
	}

	public function testHonoursLimit(): void {
		$this->userManager->method('searchDisplayName')->willReturn([
			$this->user('bob', 'Bob'),
			$this->user('carol', 'Carol'),
			$this->user('dave', 'Dave'),
		]);
		$this->userManager->method('get')->willReturn(null);

		$this->assertSame([
			['id' => 'bob', 'label' => 'Bob'],
			['id' => 'carol', 'label' => 'Carol'],
		], $this->controller->autocompleteUsers('a', 2)->getData());
	}

	public function testBlankSearchDoesNotLookUpAnAccountId(): void {
		$this->userManager->method('searchDisplayName')->willReturn([]);
		$this->userManager->expects($this->never())->method('get');

		$this->assertSame([], $this->controller->autocompleteUsers('')->getData());
	}
}
