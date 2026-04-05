<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Db\HouseMember;
use OCA\Pantry\Db\HouseMemberMapper;
use OCA\Pantry\Exception\ForbiddenException;
use OCA\Pantry\Service\HouseAuthService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class HouseAuthServiceTest extends TestCase {
	/** @var HouseMemberMapper&MockObject */
	private HouseMemberMapper $mapper;
	private HouseAuthService $svc;

	protected function setUp(): void {
		$this->mapper = $this->createMock(HouseMemberMapper::class);
		$this->svc = new HouseAuthService($this->mapper);
	}

	private function makeMember(string $role): HouseMember {
		$m = new HouseMember();
		$m->setHouseId(1);
		$m->setUserId('alice');
		$m->setRole($role);
		$m->setJoinedAt(0);
		return $m;
	}

	public function testRequireMemberAllowsAnyRole(): void {
		$this->mapper->method('findForUserAndHouse')->willReturn($this->makeMember(HouseMember::ROLE_MEMBER));
		$this->svc->requireMember(1, 'alice');
		$this->assertTrue(true);
	}

	public function testRequireMemberForbidsNonMember(): void {
		$this->mapper->method('findForUserAndHouse')->willReturn(null);
		$this->expectException(ForbiddenException::class);
		$this->svc->requireMember(1, 'bob');
	}

	public function testRequireAdminAllowsAdmin(): void {
		$this->mapper->method('findForUserAndHouse')->willReturn($this->makeMember(HouseMember::ROLE_ADMIN));
		$this->svc->requireAdmin(1, 'alice');
		$this->assertTrue(true);
	}

	public function testRequireAdminAllowsOwner(): void {
		$this->mapper->method('findForUserAndHouse')->willReturn($this->makeMember(HouseMember::ROLE_OWNER));
		$this->svc->requireAdmin(1, 'alice');
		$this->assertTrue(true);
	}

	public function testRequireAdminForbidsPlainMember(): void {
		$this->mapper->method('findForUserAndHouse')->willReturn($this->makeMember(HouseMember::ROLE_MEMBER));
		$this->expectException(ForbiddenException::class);
		$this->svc->requireAdmin(1, 'alice');
	}

	public function testRequireOwnerForbidsAdmin(): void {
		$this->mapper->method('findForUserAndHouse')->willReturn($this->makeMember(HouseMember::ROLE_ADMIN));
		$this->expectException(ForbiddenException::class);
		$this->svc->requireOwner(1, 'alice');
	}

	public function testRequireOwnerAllowsOwner(): void {
		$this->mapper->method('findForUserAndHouse')->willReturn($this->makeMember(HouseMember::ROLE_OWNER));
		$this->svc->requireOwner(1, 'alice');
		$this->assertTrue(true);
	}
}
