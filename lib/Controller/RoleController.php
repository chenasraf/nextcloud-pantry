<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Controller;

use OCA\Pantry\Db\ListRoleMapper;
use OCA\Pantry\Permission\Permission;
use OCA\Pantry\ResponseDefinitions;
use OCA\Pantry\Service\RoleService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

/**
 * @psalm-import-type PantryRole from ResponseDefinitions
 * @psalm-import-type PantrySuccess from ResponseDefinitions
 */
final class RoleController extends OCSController {
	use TranslatesDomainExceptions;

	public function __construct(
		string $appName,
		IRequest $request,
		private RoleService $roles,
		private ListRoleMapper $listRoleMapper,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * List the roles defined in a house
	 *
	 * @param int $houseId House id.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryRole>, array{}>
	 *
	 * 200: Roles returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/roles')]
	#[NoAdminRequired]
	#[Permission]
	public function index(int $houseId): DataResponse {
		return $this->runAction(function () use ($houseId): DataResponse {
			$roles = $this->roles->listForHouse($houseId);
			return new DataResponse(array_map(fn ($r) => $r->jsonSerialize(), $roles));
		});
	}

	/**
	 * Create a custom role
	 *
	 * @param int $houseId House id.
	 * @param string $name Role name.
	 * @param array<string, bool> $caps Capability map (capability key => granted).
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryRole, array{}>
	 *
	 * 200: Role created
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/roles')]
	#[NoAdminRequired]
	#[Permission(admin: true)]
	public function create(int $houseId, string $name, array $caps = []): DataResponse {
		return $this->runAction(function () use ($houseId, $name, $caps): DataResponse {
			$role = $this->roles->create($houseId, $name, $caps);
			return new DataResponse($role->jsonSerialize());
		});
	}

	/**
	 * Fetch a single role
	 *
	 * @param int $houseId House id.
	 * @param int $roleId Role id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryRole, array{}>
	 *
	 * 200: Role returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/roles/{roleId}', requirements: ['roleId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission]
	public function show(int $houseId, int $roleId): DataResponse {
		return $this->runAction(function () use ($houseId, $roleId): DataResponse {
			return new DataResponse($this->roles->get($houseId, $roleId)->jsonSerialize());
		});
	}

	/**
	 * Update a role's name and/or capabilities
	 *
	 * role_type is immutable; the Admin role's capabilities are locked.
	 *
	 * @param int $houseId House id.
	 * @param int $roleId Role id.
	 * @param string|null $name New name.
	 * @param array<string, bool>|null $caps Capability map to apply.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryRole, array{}>
	 *
	 * 200: Role updated
	 */
	#[ApiRoute(verb: 'PATCH', url: '/api/houses/{houseId}/roles/{roleId}', requirements: ['roleId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(admin: true)]
	public function update(int $houseId, int $roleId, ?string $name = null, ?array $caps = null): DataResponse {
		return $this->runAction(function () use ($houseId, $roleId, $name, $caps): DataResponse {
			$role = $this->roles->update($houseId, $roleId, $name, $caps);
			return new DataResponse($role->jsonSerialize());
		});
	}

	/**
	 * Delete a custom role
	 *
	 * Built-in roles (Admin, Member) cannot be deleted.
	 *
	 * @param int $houseId House id.
	 * @param int $roleId Role id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Role deleted
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/roles/{roleId}', requirements: ['roleId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(admin: true)]
	public function destroy(int $houseId, int $roleId): DataResponse {
		return $this->runAction(function () use ($houseId, $roleId): DataResponse {
			$this->roles->delete($houseId, $roleId);
			return new DataResponse(['success' => true]);
		});
	}

	/**
	 * Replace the set of roles assigned to a member
	 *
	 * @param int $houseId House id.
	 * @param int $memberId Member id.
	 * @param list<int> $roleIds Role ids to assign.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Member roles updated
	 */
	#[ApiRoute(verb: 'PUT', url: '/api/houses/{houseId}/members/{memberId}/roles')]
	#[NoAdminRequired]
	#[Permission(admin: true)]
	public function setMemberRoles(int $houseId, int $memberId, array $roleIds = []): DataResponse {
		return $this->runAction(function () use ($houseId, $memberId, $roleIds): DataResponse {
			$this->roles->setMemberRoles($houseId, $memberId, array_map('intval', $roleIds));
			return new DataResponse(['success' => true]);
		});
	}

	/**
	 * Get the roles allowed to access a checklist (empty = open to everyone)
	 *
	 * @param int $houseId House id.
	 * @param int $listId List id.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<int>, array{}>
	 *
	 * 200: Allowed role ids returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/lists/{listId}/roles')]
	#[NoAdminRequired]
	#[Permission(admin: true)]
	public function listRoles(int $houseId, int $listId): DataResponse {
		return $this->runAction(function () use ($listId): DataResponse {
			return new DataResponse($this->listRoleMapper->findRoleIdsForList($listId));
		});
	}

	/**
	 * Replace the set of roles allowed to access a checklist
	 *
	 * An empty list leaves the checklist open to everyone.
	 *
	 * @param int $houseId House id.
	 * @param int $listId List id.
	 * @param list<int> $roleIds Role ids allowed to access the list.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: List access updated
	 */
	#[ApiRoute(verb: 'PUT', url: '/api/houses/{houseId}/lists/{listId}/roles')]
	#[NoAdminRequired]
	#[Permission(admin: true)]
	public function setListRoles(int $houseId, int $listId, array $roleIds = []): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $roleIds): DataResponse {
			$this->roles->setListRoles($houseId, $listId, array_map('intval', $roleIds));
			return new DataResponse(['success' => true]);
		});
	}
}
