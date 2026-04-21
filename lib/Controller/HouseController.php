<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Controller;

use OCA\Pantry\Db\House;
use OCA\Pantry\Db\HouseMember;
use OCA\Pantry\Exception\ForbiddenException;
use OCA\Pantry\ResponseDefinitions;
use OCA\Pantry\Service\HouseAuthService;
use OCA\Pantry\Service\HouseService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @psalm-import-type PantryHouse from ResponseDefinitions
 * @psalm-import-type PantryMember from ResponseDefinitions
 * @psalm-import-type PantrySuccess from ResponseDefinitions
 */
final class HouseController extends OCSController {
	use TranslatesDomainExceptions;

	public function __construct(
		string $appName,
		IRequest $request,
		private HouseService $houseService,
		private HouseAuthService $auth,
		private IUserSession $userSession,
		private \OCP\IUserManager $userManager,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * List houses the current user belongs to
	 *
	 * @param int<1, 500> $limit Maximum number of houses to return.
	 * @param int<0, max> $offset Number of houses to skip.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryHouse>, array{}>
	 *
	 * 200: Houses returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses')]
	#[NoAdminRequired]
	public function index(int $limit = 100, int $offset = 0): DataResponse {
		return $this->runAction(function () use ($limit, $offset): DataResponse {
			$uid = $this->requireUid();
			$houses = $this->houseService->listForUser($uid);
			$sliced = array_slice($houses, max(0, $offset), max(0, $limit));
			$out = [];
			foreach ($sliced as $house) {
				$member = $this->auth->requireMember((int)$house->getId(), $uid);
				$out[] = $this->serializeHouseWithRole($house, $member->getRole());
			}
			return new DataResponse($out);
		});
	}

	/**
	 * Create a new house
	 *
	 * The caller becomes the owner.
	 *
	 * @param string $name House name.
	 * @param string|null $description Optional description.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryHouse, array{}>
	 *
	 * 200: House created
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses')]
	#[NoAdminRequired]
	public function create(string $name, ?string $description = null): DataResponse {
		return $this->runAction(function () use ($name, $description): DataResponse {
			$uid = $this->requireUid();
			$house = $this->houseService->create($uid, $name, $description);
			return new DataResponse($this->serializeHouseWithRole($house, HouseMember::ROLE_OWNER));
		});
	}

	/**
	 * Fetch a single house
	 *
	 * The caller must be a member.
	 *
	 * @param int $houseId House id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryHouse, array{}>
	 *
	 * 200: House returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}')]
	#[NoAdminRequired]
	public function show(int $houseId): DataResponse {
		return $this->runAction(function () use ($houseId): DataResponse {
			$uid = $this->requireUid();
			$member = $this->auth->requireMember($houseId, $uid);
			$house = $this->houseService->get($houseId);
			return new DataResponse($this->serializeHouseWithRole($house, $member->getRole()));
		});
	}

	/**
	 * Update a house
	 *
	 * Requires admin or owner role.
	 *
	 * @param int $houseId House id.
	 * @param string|null $name New name.
	 * @param string|null $description New description.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryHouse, array{}>
	 *
	 * 200: House updated
	 */
	#[ApiRoute(verb: 'PATCH', url: '/api/houses/{houseId}')]
	#[NoAdminRequired]
	public function update(int $houseId, ?string $name = null, ?string $description = null): DataResponse {
		return $this->runAction(function () use ($houseId, $name, $description): DataResponse {
			$uid = $this->requireUid();
			$member = $this->auth->requireAdmin($houseId, $uid);
			$patch = [];
			if ($name !== null) {
				$patch['name'] = $name;
			}
			if ($description !== null) {
				$patch['description'] = $description;
			}
			$house = $this->houseService->update($houseId, $patch);
			return new DataResponse($this->serializeHouseWithRole($house, $member->getRole()));
		});
	}

	/**
	 * Delete a house and all of its data
	 *
	 * Owner only. Removes all lists, items, photos, notes and member records.
	 *
	 * @param int $houseId House id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: House deleted
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}')]
	#[NoAdminRequired]
	public function destroy(int $houseId): DataResponse {
		return $this->runAction(function () use ($houseId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireOwner($houseId, $uid);
			$this->houseService->delete($houseId);
			return new DataResponse(['success' => true]);
		});
	}

	/**
	 * List members of a house
	 *
	 * @param int $houseId House id.
	 * @param int<1, 500> $limit Maximum number of members to return.
	 * @param int<0, max> $offset Number of members to skip.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryMember>, array{}>
	 *
	 * 200: Members returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/members')]
	#[NoAdminRequired]
	public function listMembers(int $houseId, int $limit = 100, int $offset = 0): DataResponse {
		return $this->runAction(function () use ($houseId, $limit, $offset): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$members = array_slice(
				$this->houseService->listMembers($houseId),
				max(0, $offset),
				max(0, $limit),
			);
			$out = array_map(fn (HouseMember $m) => $this->serializeMember($m), $members);
			return new DataResponse($out);
		});
	}

	/**
	 * Add a member to a house
	 *
	 * Requires admin or owner role.
	 *
	 * @param int $houseId House id.
	 * @param string $userId Nextcloud user id to add.
	 * @param string $role Role: "admin" or "member".
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryMember, array{}>
	 *
	 * 200: Member added
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/members')]
	#[NoAdminRequired]
	public function addMember(int $houseId, string $userId, string $role = HouseMember::ROLE_MEMBER): DataResponse {
		return $this->runAction(function () use ($houseId, $userId, $role): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireAdmin($houseId, $uid);
			$member = $this->houseService->addMember($houseId, $userId, $role);
			return new DataResponse($this->serializeMember($member));
		});
	}

	/**
	 * Change a member's role
	 *
	 * Requires admin or owner role. The owner's role cannot be changed.
	 *
	 * @param int $houseId House id.
	 * @param int $memberId Member id.
	 * @param string $role New role.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryMember, array{}>
	 *
	 * 200: Role updated
	 */
	#[ApiRoute(verb: 'PATCH', url: '/api/houses/{houseId}/members/{memberId}')]
	#[NoAdminRequired]
	public function updateMember(int $houseId, int $memberId, string $role): DataResponse {
		return $this->runAction(function () use ($houseId, $memberId, $role): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireAdmin($houseId, $uid);
			$member = $this->houseService->updateMemberRole($houseId, $memberId, $role);
			return new DataResponse($this->serializeMember($member));
		});
	}

	/**
	 * Remove a member from a house
	 *
	 * Requires admin or owner role. The owner cannot be removed.
	 *
	 * @param int $houseId House id.
	 * @param int $memberId Member id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Member removed
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/members/{memberId}')]
	#[NoAdminRequired]
	public function removeMember(int $houseId, int $memberId): DataResponse {
		return $this->runAction(function () use ($houseId, $memberId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireAdmin($houseId, $uid);
			$this->houseService->removeMember($houseId, $memberId);
			return new DataResponse(['success' => true]);
		});
	}

	/**
	 * Leave a house
	 *
	 * Any non-owner member may call this. The owner must transfer ownership first.
	 *
	 * @param int $houseId House id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Left house
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/leave')]
	#[NoAdminRequired]
	public function leave(int $houseId): DataResponse {
		return $this->runAction(function () use ($houseId): DataResponse {
			$uid = $this->requireUid();
			$this->houseService->leaveHouse($houseId, $uid);
			return new DataResponse(['success' => true]);
		});
	}

	/**
	 * Search Nextcloud users for autocomplete
	 *
	 * Excludes the current user from results.
	 *
	 * @param string $search Search query.
	 * @param int<1, 50> $limit Maximum results.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<array{id: string, label: string}>, array{}>
	 *
	 * 200: Users returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/users/autocomplete')]
	#[NoAdminRequired]
	public function autocompleteUsers(string $search = '', int $limit = 10): DataResponse {
		return $this->runAction(function () use ($search, $limit): DataResponse {
			$currentUid = $this->requireUid();
			$users = $this->userManager->search(trim($search), $limit + 1);
			$results = [];
			foreach ($users as $user) {
				if ($user->getUID() === $currentUid) {
					continue;
				}
				$results[] = [
					'id' => $user->getUID(),
					'label' => $user->getDisplayName(),
				];
				if (count($results) >= $limit) {
					break;
				}
			}
			return new DataResponse($results);
		});
	}

	private function requireUid(): string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new ForbiddenException('Not authenticated');
		}
		return $user->getUID();
	}

	/**
	 * @return PantryHouse
	 */
	private function serializeHouseWithRole(House $house, string $role): array {
		return [
			'id' => (int)$house->getId(),
			'name' => $house->getName(),
			'description' => $house->getDescription(),
			'ownerUid' => $house->getOwnerUid(),
			'createdAt' => $house->getCreatedAt(),
			'updatedAt' => $house->getUpdatedAt(),
			'role' => $role,
		];
	}

	/**
	 * @return PantryMember
	 */
	private function serializeMember(HouseMember $member): array {
		$user = $this->userManager->get($member->getUserId());
		return [
			'id' => (int)$member->getId(),
			'houseId' => $member->getHouseId(),
			'userId' => $member->getUserId(),
			'displayName' => $user !== null ? $user->getDisplayName() : $member->getUserId(),
			'role' => $member->getRole(),
			'joinedAt' => $member->getJoinedAt(),
		];
	}
}
