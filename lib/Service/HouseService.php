<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Db\House;
use OCA\Pantry\Db\HouseMapper;
use OCA\Pantry\Db\HouseMember;
use OCA\Pantry\Db\HouseMemberMapper;
use OCA\Pantry\Db\ShoppingListItemMapper;
use OCA\Pantry\Db\ShoppingListMapper;
use OCA\Pantry\Exception\ForbiddenException;
use OCA\Pantry\Exception\NotFoundException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;
use OCP\IUserManager;

class HouseService {
	public function __construct(
		private HouseMapper $houseMapper,
		private HouseMemberMapper $memberMapper,
		private ShoppingListMapper $listMapper,
		private ShoppingListItemMapper $itemMapper,
		private IDBConnection $db,
		private IUserManager $userManager,
	) {
	}

	/**
	 * @return House[]
	 */
	public function listForUser(string $uid): array {
		return $this->houseMapper->findAllForUser($uid);
	}

	public function get(int $houseId): House {
		try {
			return $this->houseMapper->findById($houseId);
		} catch (DoesNotExistException) {
			throw new NotFoundException('House not found');
		}
	}

	public function create(string $uid, string $name, ?string $description): House {
		$name = trim($name);
		if ($name === '') {
			throw new \InvalidArgumentException('House name cannot be empty');
		}

		$this->db->beginTransaction();
		try {
			$now = time();

			$house = new House();
			$house->setName($name);
			$house->setDescription($description !== null && $description !== '' ? $description : null);
			$house->setOwnerUid($uid);
			$house->setCreatedAt($now);
			$house->setUpdatedAt($now);
			/** @var House $house */
			$house = $this->houseMapper->insert($house);

			$member = new HouseMember();
			$member->setHouseId((int)$house->getId());
			$member->setUserId($uid);
			$member->setRole(HouseMember::ROLE_OWNER);
			$member->setJoinedAt($now);
			$this->memberMapper->insert($member);

			$this->db->commit();
			return $house;
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	public function update(int $houseId, array $patch): House {
		$house = $this->get($houseId);
		if (isset($patch['name'])) {
			$name = trim((string)$patch['name']);
			if ($name === '') {
				throw new \InvalidArgumentException('House name cannot be empty');
			}
			$house->setName($name);
		}
		if (array_key_exists('description', $patch)) {
			$desc = $patch['description'];
			$house->setDescription(is_string($desc) && $desc !== '' ? $desc : null);
		}
		$house->setUpdatedAt(time());
		$this->houseMapper->update($house);
		return $house;
	}

	public function delete(int $houseId): void {
		$house = $this->get($houseId);

		$this->db->beginTransaction();
		try {
			// Delete all items under all lists of this house
			foreach ($this->listMapper->findByHouse($houseId) as $list) {
				$this->itemMapper->deleteByList((int)$list->getId());
			}
			$this->listMapper->deleteByHouse($houseId);
			$this->memberMapper->deleteByHouse($houseId);
			$this->houseMapper->delete($house);
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	/**
	 * @return HouseMember[]
	 */
	public function listMembers(int $houseId): array {
		return $this->memberMapper->findByHouse($houseId);
	}

	public function addMember(int $houseId, string $userId, string $role): HouseMember {
		$role = $this->normalizeAssignableRole($role);

		if ($this->userManager->get($userId) === null) {
			throw new NotFoundException('User not found: ' . $userId);
		}

		if ($this->memberMapper->findForUserAndHouse($userId, $houseId) !== null) {
			throw new \InvalidArgumentException('User is already a member of this house');
		}

		$member = new HouseMember();
		$member->setHouseId($houseId);
		$member->setUserId($userId);
		$member->setRole($role);
		$member->setJoinedAt(time());
		/** @var HouseMember $saved */
		$saved = $this->memberMapper->insert($member);
		return $saved;
	}

	public function updateMemberRole(int $houseId, int $memberId, string $role): HouseMember {
		$role = $this->normalizeAssignableRole($role);
		$member = $this->getMember($houseId, $memberId);
		if ($member->isOwner()) {
			throw new ForbiddenException('Cannot change the role of the house owner');
		}
		$member->setRole($role);
		$this->memberMapper->update($member);
		return $member;
	}

	public function removeMember(int $houseId, int $memberId): void {
		$member = $this->getMember($houseId, $memberId);
		if ($member->isOwner()) {
			throw new ForbiddenException('Cannot remove the house owner');
		}
		$this->memberMapper->delete($member);
	}

	public function leaveHouse(int $houseId, string $uid): void {
		$member = $this->memberMapper->findForUserAndHouse($uid, $houseId);
		if ($member === null) {
			throw new NotFoundException('Not a member of this house');
		}
		if ($member->isOwner()) {
			throw new ForbiddenException('Owner cannot leave the house. Transfer ownership or delete the house.');
		}
		$this->memberMapper->delete($member);
	}

	private function getMember(int $houseId, int $memberId): HouseMember {
		try {
			$member = $this->memberMapper->findById($memberId);
		} catch (DoesNotExistException) {
			throw new NotFoundException('Member not found');
		}
		if ($member->getHouseId() !== $houseId) {
			throw new NotFoundException('Member does not belong to this house');
		}
		return $member;
	}

	private function normalizeAssignableRole(string $role): string {
		if ($role === HouseMember::ROLE_ADMIN) {
			return HouseMember::ROLE_ADMIN;
		}
		if ($role === HouseMember::ROLE_MEMBER) {
			return HouseMember::ROLE_MEMBER;
		}
		throw new \InvalidArgumentException('Role must be "admin" or "member"');
	}
}
