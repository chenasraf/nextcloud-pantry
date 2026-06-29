<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Db\HouseMemberRoleMapper;
use OCA\Pantry\Db\ListRoleMapper;
use OCA\Pantry\Db\Role;
use OCA\Pantry\Db\RoleMapper;
use OCA\Pantry\Service\PermissionService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PermissionServiceTest extends TestCase {
	/** @var RoleMapper&MockObject */
	private RoleMapper $roleMapper;
	/** @var HouseMemberRoleMapper&MockObject */
	private HouseMemberRoleMapper $memberRoleMapper;
	/** @var ListRoleMapper&MockObject */
	private ListRoleMapper $listRoleMapper;
	private PermissionService $svc;

	protected function setUp(): void {
		$this->roleMapper = $this->createMock(RoleMapper::class);
		$this->memberRoleMapper = $this->createMock(HouseMemberRoleMapper::class);
		$this->listRoleMapper = $this->createMock(ListRoleMapper::class);
		$this->svc = new PermissionService(
			$this->roleMapper,
			$this->memberRoleMapper,
			$this->listRoleMapper,
		);
	}

	private function makeRole(int $id, string $roleType, array $caps = []): Role {
		$role = new Role();
		$role->setId($id);
		$role->setHouseId(1);
		$role->setName('Role ' . $id);
		$role->setRoleType($roleType);
		foreach (Role::CAPABILITIES as $key => $prop) {
			$role->{'set' . ucfirst($prop)}(in_array($key, $caps, true));
		}
		return $role;
	}

	public function testEffectiveCapabilitiesOrsAcrossRoles(): void {
		$viewer = $this->makeRole(10, Role::TYPE_NORMAL, ['canViewLists', 'canViewNotes']);
		$editor = $this->makeRole(11, Role::TYPE_NORMAL, ['canEditLists', 'canAddItems']);
		$this->roleMapper->method('findByHouse')->willReturn([$viewer, $editor]);
		$this->memberRoleMapper->method('findRoleIdsForUserInHouse')->willReturn([10, 11]);

		$caps = $this->svc->effectiveCapabilities(1, 'alice');

		$this->assertTrue($caps['canViewLists']);
		$this->assertTrue($caps['canViewNotes']);
		$this->assertTrue($caps['canEditLists']);
		$this->assertTrue($caps['canAddItems']);
		$this->assertFalse($caps['canDeleteLists']);
		$this->assertFalse($caps['canUploadPhotos']);
	}

	public function testAdminRoleGrantsEverything(): void {
		$admin = $this->makeRole(1, Role::TYPE_ADMIN);
		$this->roleMapper->method('findByHouse')->willReturn([$admin]);
		$this->memberRoleMapper->method('findRoleIdsForUserInHouse')->willReturn([1]);

		$this->assertTrue($this->svc->isAdmin(1, 'alice'));
		$caps = $this->svc->effectiveCapabilities(1, 'alice');
		foreach ($caps as $granted) {
			$this->assertTrue($granted);
		}
	}

	public function testNoRolesGrantsNothing(): void {
		$this->roleMapper->method('findByHouse')->willReturn([]);
		$this->memberRoleMapper->method('findRoleIdsForUserInHouse')->willReturn([]);

		$this->assertFalse($this->svc->isAdmin(1, 'alice'));
		$this->assertFalse($this->svc->can(1, 'alice', 'canViewLists'));
		foreach ($this->svc->effectiveCapabilities(1, 'alice') as $granted) {
			$this->assertFalse($granted);
		}
	}

	public function testCanAccessListOpenWhenNoRolesAssigned(): void {
		$this->listRoleMapper->method('findRoleIdsForList')->willReturn([]);
		// Membership not even consulted for an open list.
		$this->assertTrue($this->svc->canAccessList(1, 'alice', 99));
	}

	public function testCanAccessListAllowsMatchingRole(): void {
		$this->listRoleMapper->method('findRoleIdsForList')->willReturn([11]);
		$this->memberRoleMapper->method('findRoleIdsForUserInHouse')->willReturn([11, 12]);
		$this->assertTrue($this->svc->canAccessList(1, 'alice', 99));
	}

	public function testCanAccessListDeniesNonMatchingRole(): void {
		$this->listRoleMapper->method('findRoleIdsForList')->willReturn([11]);
		$this->memberRoleMapper->method('findRoleIdsForUserInHouse')->willReturn([12]);
		// Not an admin and holds no allowed role.
		$this->roleMapper->method('findByHouse')->willReturn([$this->makeRole(12, Role::TYPE_NORMAL)]);
		$this->assertFalse($this->svc->canAccessList(1, 'alice', 99));
	}

	public function testCanAccessListAdminBypassesRestriction(): void {
		$this->listRoleMapper->method('findRoleIdsForList')->willReturn([11]);
		// User's role id (1) is not in the allowed set, but it is an admin role.
		$this->memberRoleMapper->method('findRoleIdsForUserInHouse')->willReturn([1]);
		$this->roleMapper->method('findByHouse')->willReturn([$this->makeRole(1, Role::TYPE_ADMIN)]);
		$this->assertTrue($this->svc->canAccessList(1, 'alice', 99));
	}
}
