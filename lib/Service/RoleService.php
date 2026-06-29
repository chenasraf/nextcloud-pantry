<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Db\ChecklistMapper;
use OCA\Pantry\Db\HouseMemberMapper;
use OCA\Pantry\Db\HouseMemberRoleMapper;
use OCA\Pantry\Db\ListRoleMapper;
use OCA\Pantry\Db\Role;
use OCA\Pantry\Db\RoleMapper;
use OCA\Pantry\Exception\ForbiddenException;
use OCA\Pantry\Exception\NotFoundException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;

class RoleService {
	public function __construct(
		private RoleMapper $roleMapper,
		private HouseMemberMapper $memberMapper,
		private HouseMemberRoleMapper $memberRoleMapper,
		private ListRoleMapper $listRoleMapper,
		private ChecklistMapper $listMapper,
		private IDBConnection $db,
	) {
	}

	/**
	 * Create the built-in Admin and Member roles for a freshly created house.
	 * Both are seeded with every capability granted so existing "all members
	 * can do everything" behavior is preserved. Returns [admin, member].
	 *
	 * @return array{0: Role, 1: Role}
	 */
	public function seedBuiltins(int $houseId): array {
		$admin = new Role();
		$admin->setHouseId($houseId);
		$admin->setName('Admin');
		$admin->setRoleType(Role::TYPE_ADMIN);
		$this->grantAll($admin);
		/** @var Role $admin */
		$admin = $this->roleMapper->insert($admin);

		$member = new Role();
		$member->setHouseId($houseId);
		$member->setName('Member');
		$member->setRoleType(Role::TYPE_DEFAULT);
		$this->grantAll($member);
		/** @var Role $member */
		$member = $this->roleMapper->insert($member);

		return [$admin, $member];
	}

	/**
	 * @return Role[]
	 */
	public function listForHouse(int $houseId): array {
		return $this->roleMapper->findByHouse($houseId);
	}

	public function get(int $houseId, int $roleId): Role {
		try {
			$role = $this->roleMapper->findById($roleId);
		} catch (DoesNotExistException) {
			throw new NotFoundException('Role not found');
		}
		if ($role->getHouseId() !== $houseId) {
			throw new NotFoundException('Role does not belong to this house');
		}
		return $role;
	}

	/**
	 * @param array<string, bool> $caps
	 */
	public function create(int $houseId, string $name, array $caps): Role {
		$name = trim($name);
		if ($name === '') {
			throw new \InvalidArgumentException('Role name cannot be empty');
		}
		$role = new Role();
		$role->setHouseId($houseId);
		$role->setName($name);
		$role->setRoleType(Role::TYPE_NORMAL);
		$this->applyCaps($role, $caps);
		/** @var Role $role */
		$role = $this->roleMapper->insert($role);
		return $role;
	}

	/**
	 * Update a role's name and/or capabilities. role_type is immutable. The
	 * Admin role's capabilities are locked (always all granted) and silently
	 * ignored.
	 *
	 * @param array<string, bool>|null $caps
	 */
	public function update(int $houseId, int $roleId, ?string $name, ?array $caps): Role {
		$role = $this->get($houseId, $roleId);
		if ($name !== null) {
			$name = trim($name);
			if ($name === '') {
				throw new \InvalidArgumentException('Role name cannot be empty');
			}
			$role->setName($name);
		}
		if ($caps !== null && !$role->isAdmin()) {
			$this->applyCaps($role, $caps);
		}
		$this->roleMapper->update($role);
		return $role;
	}

	public function delete(int $houseId, int $roleId): void {
		$role = $this->get($houseId, $roleId);
		if ($role->isBuiltin()) {
			throw new ForbiddenException('Built-in roles cannot be deleted');
		}
		$this->db->beginTransaction();
		try {
			$this->memberRoleMapper->deleteByRole($roleId);
			$this->listRoleMapper->deleteByRole($roleId);
			$this->roleMapper->delete($role);
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	/**
	 * Replace the set of roles assigned to a member.
	 *
	 * @param int[] $roleIds
	 */
	public function setMemberRoles(int $houseId, int $memberId, array $roleIds): void {
		try {
			$member = $this->memberMapper->findById($memberId);
		} catch (DoesNotExistException) {
			throw new NotFoundException('Member not found');
		}
		if ($member->getHouseId() !== $houseId) {
			throw new NotFoundException('Member does not belong to this house');
		}
		$this->assertRolesInHouse($houseId, $roleIds);
		$this->memberRoleMapper->setRolesForMember($memberId, $roleIds);
	}

	/**
	 * Replace the set of roles allowed to access a checklist. An empty list
	 * leaves the checklist open to everyone.
	 *
	 * @param int[] $roleIds
	 */
	public function setListRoles(int $houseId, int $listId, array $roleIds): void {
		try {
			$list = $this->listMapper->findById($listId);
		} catch (DoesNotExistException) {
			throw new NotFoundException('List not found');
		}
		if ($list->getHouseId() !== $houseId) {
			throw new NotFoundException('List does not belong to this house');
		}
		$this->assertRolesInHouse($houseId, $roleIds);
		$this->listRoleMapper->setRolesForList($listId, $roleIds);
	}

	/**
	 * @param int[] $roleIds
	 */
	private function assertRolesInHouse(int $houseId, array $roleIds): void {
		if ($roleIds === []) {
			return;
		}
		$valid = [];
		foreach ($this->roleMapper->findByHouse($houseId) as $role) {
			$valid[(int)$role->getId()] = true;
		}
		foreach ($roleIds as $id) {
			if (!isset($valid[(int)$id])) {
				throw new NotFoundException('Role does not belong to this house: ' . $id);
			}
		}
	}

	private function grantAll(Role $role): void {
		foreach (Role::CAPABILITIES as $prop) {
			$role->{'set' . ucfirst($prop)}(true);
		}
	}

	/**
	 * @param array<string, bool> $caps
	 */
	private function applyCaps(Role $role, array $caps): void {
		foreach (Role::CAPABILITIES as $key => $prop) {
			if (array_key_exists($key, $caps)) {
				$role->{'set' . ucfirst($prop)}((bool)$caps[$key]);
			}
		}
	}
}
