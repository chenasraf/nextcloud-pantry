<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Db\HouseMemberRoleMapper;
use OCA\Pantry\Db\ListRoleMapper;
use OCA\Pantry\Db\Role;
use OCA\Pantry\Db\RoleMapper;

/**
 * Resolves a user's effective permissions within a house from the roles they
 * hold. Effective capabilities are the OR across every role; an admin role
 * short-circuits to "everything granted".
 */
class PermissionService {
	public function __construct(
		private RoleMapper $roleMapper,
		private HouseMemberRoleMapper $memberRoleMapper,
		private ListRoleMapper $listRoleMapper,
	) {
	}

	/**
	 * The roles a user holds in a house.
	 *
	 * @return Role[]
	 */
	public function rolesForUser(int $houseId, string $uid): array {
		$roleIds = $this->memberRoleMapper->findRoleIdsForUserInHouse($uid, $houseId);
		if ($roleIds === []) {
			return [];
		}
		$byId = [];
		foreach ($this->roleMapper->findByHouse($houseId) as $role) {
			$byId[(int)$role->getId()] = $role;
		}
		$roles = [];
		foreach ($roleIds as $id) {
			if (isset($byId[$id])) {
				$roles[] = $byId[$id];
			}
		}
		return $roles;
	}

	public function isAdmin(int $houseId, string $uid): bool {
		foreach ($this->rolesForUser($houseId, $uid) as $role) {
			if ($role->isAdmin()) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The effective capability map for a user: each capability key mapped to
	 * whether any of the user's roles grants it.
	 *
	 * @return array<string, bool>
	 */
	public function effectiveCapabilities(int $houseId, string $uid): array {
		$roles = $this->rolesForUser($houseId, $uid);
		$caps = [];
		foreach (array_keys(Role::CAPABILITIES) as $key) {
			$caps[$key] = false;
		}
		foreach ($roles as $role) {
			if ($role->isAdmin()) {
				foreach ($caps as $key => $_) {
					$caps[$key] = true;
				}
				break;
			}
			foreach ($caps as $key => $granted) {
				if (!$granted && $role->hasCapability($key)) {
					$caps[$key] = true;
				}
			}
		}
		return $caps;
	}

	public function can(int $houseId, string $uid, string $capKey): bool {
		foreach ($this->rolesForUser($houseId, $uid) as $role) {
			if ($role->hasCapability($capKey)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether a user may access a specific checklist. Admins always may; a list
	 * with no explicit role rows is open to everyone; otherwise the user must
	 * hold at least one of the list's allowed roles.
	 */
	public function canAccessList(int $houseId, string $uid, int $listId): bool {
		$allowed = $this->listRoleMapper->findRoleIdsForList($listId);
		if ($allowed === []) {
			return true;
		}
		$userRoleIds = $this->memberRoleMapper->findRoleIdsForUserInHouse($uid, $houseId);
		if (array_intersect($userRoleIds, $allowed) !== []) {
			return true;
		}
		return $this->isAdmin($houseId, $uid);
	}
}
