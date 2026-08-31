<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Db\CategoryMapper;
use OCA\Pantry\Db\ChecklistItemMapper;
use OCA\Pantry\Db\ChecklistMapper;
use OCA\Pantry\Db\House;
use OCA\Pantry\Db\HouseMapper;
use OCA\Pantry\Db\HouseMember;
use OCA\Pantry\Db\HouseMemberMapper;
use OCA\Pantry\Db\HouseMemberRoleMapper;
use OCA\Pantry\Db\ListRoleMapper;
use OCA\Pantry\Db\NoteMapper;
use OCA\Pantry\Db\PhotoFolderMapper;
use OCA\Pantry\Db\PhotoMapper;
use OCA\Pantry\Db\Role;
use OCA\Pantry\Db\RoleMapper;
use OCA\Pantry\Db\ShareMapper;
use OCA\Pantry\Exception\ForbiddenException;
use OCA\Pantry\Exception\NotFoundException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;
use OCP\IUserManager;

class HouseService {
	public function __construct(
		private HouseMapper $houseMapper,
		private HouseMemberMapper $memberMapper,
		private ChecklistMapper $listMapper,
		private ChecklistItemMapper $itemMapper,
		private CategoryMapper $categoryMapper,
		private \OCA\Pantry\Db\LabelMapper $labelMapper,
		private \OCA\Pantry\Db\ItemLabelMapper $itemLabelMapper,
		private \OCA\Pantry\Db\StoreMapper $storeMapper,
		private \OCA\Pantry\Db\ItemStoreMapper $itemStoreMapper,
		private \OCA\Pantry\Db\ItemPriceMapper $itemPriceMapper,
		private \OCA\Pantry\Db\ShoppingReminderMapper $shoppingReminderMapper,
		private \OCA\Pantry\Db\FieldDefinitionMapper $fieldDefMapper,
		private \OCA\Pantry\Db\FieldOptionMapper $fieldOptionMapper,
		private \OCA\Pantry\Db\FieldValueMapper $fieldValueMapper,
		private PhotoMapper $photoMapper,
		private PhotoFolderMapper $photoFolderMapper,
		private NoteMapper $noteMapper,
		private RoleMapper $roleMapper,
		private HouseMemberRoleMapper $memberRoleMapper,
		private ListRoleMapper $listRoleMapper,
		private ShareMapper $shareMapper,
		private RoleService $roles,
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
			/** @var HouseMember $member */
			$member = $this->memberMapper->insert($member);

			[$adminRole] = $this->roles->seedBuiltins((int)$house->getId());
			$this->memberRoleMapper->setRolesForMember((int)$member->getId(), [(int)$adminRole->getId()]);

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
		if (array_key_exists('trashRetentionDays', $patch)) {
			$house->setTrashRetentionDays($this->normalizeRetentionDays($patch['trashRetentionDays']));
		}
		if (array_key_exists('recurrenceTime', $patch)) {
			$house->setRecurrenceTime($this->normalizeRecurrenceTime($patch['recurrenceTime']));
		}
		if (array_key_exists('fieldReminderTime', $patch)) {
			$house->setFieldReminderTime($this->normalizeFieldReminderTime($patch['fieldReminderTime']));
		}
		$house->setUpdatedAt(time());
		$this->houseMapper->update($house);
		return $house;
	}

	/**
	 * Coerce the trash retention days value into [0, House::MAX_TRASH_RETENTION_DAYS].
	 * 0 means "never auto-purge".
	 */
	private function normalizeRetentionDays(mixed $value): int {
		if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
			throw new \InvalidArgumentException('trashRetentionDays must be a non-negative integer');
		}
		$days = (int)$value;
		if ($days < 0) {
			throw new \InvalidArgumentException('trashRetentionDays must be a non-negative integer');
		}
		if ($days > House::MAX_TRASH_RETENTION_DAYS) {
			$days = House::MAX_TRASH_RETENTION_DAYS;
		}
		return $days;
	}

	/**
	 * Coerce the recurrence time (minutes since midnight) into [0, House::MAX_RECURRENCE_TIME].
	 */
	private function normalizeRecurrenceTime(mixed $value): int {
		if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
			throw new \InvalidArgumentException('recurrenceTime must be a non-negative integer');
		}
		$minutes = (int)$value;
		if ($minutes < 0) {
			throw new \InvalidArgumentException('recurrenceTime must be a non-negative integer');
		}
		if ($minutes > House::MAX_RECURRENCE_TIME) {
			$minutes = House::MAX_RECURRENCE_TIME;
		}
		return $minutes;
	}

	/**
	 * Coerce the field reminder time (minutes since midnight) into [0, House::MAX_FIELD_REMINDER_TIME].
	 */
	private function normalizeFieldReminderTime(mixed $value): int {
		if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
			throw new \InvalidArgumentException('fieldReminderTime must be a non-negative integer');
		}
		$minutes = (int)$value;
		if ($minutes < 0) {
			throw new \InvalidArgumentException('fieldReminderTime must be a non-negative integer');
		}
		if ($minutes > House::MAX_FIELD_REMINDER_TIME) {
			$minutes = House::MAX_FIELD_REMINDER_TIME;
		}
		return $minutes;
	}

	public function delete(int $houseId): void {
		$house = $this->get($houseId);

		$this->db->beginTransaction();
		try {
			// Remove item-store/item-label links and prices before the items
			// themselves are deleted (deleteByHouse resolves item ids by joining
			// items to lists).
			$this->itemStoreMapper->deleteByHouse($houseId);
			$this->itemLabelMapper->deleteByHouse($houseId);
			$this->itemPriceMapper->deleteByHouse($houseId);
			$this->fieldValueMapper->deleteByHouse($houseId);
			// Delete all items (and their role access rows) under all lists
			foreach ($this->listMapper->findByHouse($houseId) as $list) {
				$this->itemMapper->deleteByList((int)$list->getId());
				$this->listRoleMapper->deleteByList((int)$list->getId());
			}
			$this->listMapper->deleteByHouse($houseId);
			$this->categoryMapper->deleteByHouse($houseId);
			$this->labelMapper->deleteByHouse($houseId);
			$this->storeMapper->deleteByHouse($houseId);
			$this->shoppingReminderMapper->deleteByHouse($houseId);
			// Options are children of field definitions; remove them before the defs.
			$this->fieldOptionMapper->deleteByHouse($houseId);
			$this->fieldDefMapper->deleteByHouse($houseId);
			$this->photoMapper->deleteByHouse($houseId);
			$this->photoFolderMapper->deleteByHouse($houseId);
			$this->noteMapper->deleteByHouse($houseId);
			$this->shareMapper->deleteByHouse($houseId);
			$this->memberRoleMapper->deleteByHouse($houseId);
			$this->roleMapper->deleteByHouse($houseId);
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

		// Seed the pivot with the built-in role matching the requested role so a
		// newly added member starts with sensible permissions. Callers can refine
		// the assignment afterwards via RoleService::setMemberRoles.
		$builtinType = $role === HouseMember::ROLE_ADMIN ? Role::TYPE_ADMIN : Role::TYPE_DEFAULT;
		$builtin = $this->roleMapper->findBuiltin($houseId, $builtinType);
		if ($builtin !== null) {
			$this->memberRoleMapper->setRolesForMember((int)$saved->getId(), [(int)$builtin->getId()]);
		}
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
		$this->memberRoleMapper->deleteByMember($memberId);
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
		$this->memberRoleMapper->deleteByMember((int)$member->getId());
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
