<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Db\HouseMemberMapper;
use OCA\Pantry\Db\NoteMapper;
use OCA\Pantry\Db\PhotoMapper;
use OCA\Pantry\Db\Share;
use OCA\Pantry\Db\ShareMapper;
use OCA\Pantry\Exception\ForbiddenException;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * Polymorphic per-user sharing. A share grants a specific house member view or
 * edit access to a single entity, additive on top of the role system: a user's
 * effective level is max(role-derived level, share level). For photos the level
 * also inherits from an editor/viewer share on the containing folder.
 */
class ShareService {
	/** Level ranking for max() comparisons. */
	private const RANK = [
		PermissionService::LEVEL_NONE => 0,
		PermissionService::LEVEL_VIEW => 1,
		PermissionService::LEVEL_EDIT => 2,
	];

	public function __construct(
		private ShareMapper $shareMapper,
		private PermissionService $permissions,
		private PhotoMapper $photoMapper,
		private NoteMapper $noteMapper,
		private HouseMemberMapper $memberMapper,
	) {
	}

	/**
	 * The effective access level a user has on an entity: the max of their
	 * role-derived level, a direct share, and (for photos) a share on the
	 * containing folder. One of PermissionService::LEVEL_*.
	 *
	 * @param Share::TYPE_* $type
	 */
	public function effectiveLevel(int $houseId, string $uid, string $type, int $entityId): string {
		$level = $this->permissions->roleLevel($houseId, $uid, $type, $entityId);
		$level = $this->max($level, $this->shareLevel($uid, $type, $entityId));

		if ($type === Share::TYPE_PHOTO) {
			$folderId = $this->photoFolderId($entityId);
			if ($folderId !== null) {
				$level = $this->max($level, $this->shareLevel($uid, Share::TYPE_PHOTO_FOLDER, $folderId));
			}
		}

		return $level;
	}

	/**
	 * @param Share::TYPE_* $type
	 */
	public function canView(int $houseId, string $uid, string $type, int $entityId): bool {
		return $this->effectiveLevel($houseId, $uid, $type, $entityId) !== PermissionService::LEVEL_NONE;
	}

	/**
	 * @param Share::TYPE_* $type
	 */
	public function canEdit(int $houseId, string $uid, string $type, int $entityId): bool {
		return $this->effectiveLevel($houseId, $uid, $type, $entityId) === PermissionService::LEVEL_EDIT;
	}

	/**
	 * Whether the user holds an editor-level share on the entity (excluding
	 * roles). For photos this includes an editor share on the containing folder.
	 * Used as an additive bypass on top of role capability checks.
	 *
	 * @param Share::TYPE_* $type
	 */
	public function hasEditShare(string $uid, string $type, int $entityId): bool {
		if ($this->shareLevel($uid, $type, $entityId) === Share::PERM_EDIT) {
			return true;
		}
		if ($type === Share::TYPE_PHOTO) {
			$folderId = $this->photoFolderId($entityId);
			if ($folderId !== null && $this->shareLevel($uid, Share::TYPE_PHOTO_FOLDER, $folderId) === Share::PERM_EDIT) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The shares on an entity.
	 *
	 * @param Share::TYPE_* $type
	 *
	 * @return Share[]
	 */
	public function listForEntity(string $type, int $entityId): array {
		return $this->shareMapper->findForEntity($type, $entityId);
	}

	/**
	 * A user's shares within a house, indexed by type and entity id, in a single
	 * query. Lets listing endpoints resolve per-item edit flags without a query
	 * per row.
	 *
	 * @return array<string, array<int, string>> type => (entityId => permission)
	 */
	public function userShareMap(int $houseId, string $uid): array {
		$map = [];
		foreach ($this->shareMapper->findForUserInHouse($uid, $houseId) as $share) {
			$map[$share->getEntityType()][(int)$share->getEntityId()] = $share->getPermission();
		}
		return $map;
	}

	/**
	 * Resolve an effective edit flag for one entity from a precomputed share map
	 * and role-derived edit flag. For photos, an editor share on the containing
	 * folder also grants edit.
	 *
	 * @param Share::TYPE_* $type
	 * @param array<string, array<int, string>> $shareMap
	 */
	public function canEditFromMap(string $type, int $entityId, bool $roleEdit, array $shareMap, ?int $folderId = null): bool {
		if ($roleEdit) {
			return true;
		}
		if (($shareMap[$type][$entityId] ?? null) === Share::PERM_EDIT) {
			return true;
		}
		if ($type === Share::TYPE_PHOTO && $folderId !== null
			&& ($shareMap[Share::TYPE_PHOTO_FOLDER][$folderId] ?? null) === Share::PERM_EDIT) {
			return true;
		}
		return false;
	}

	/**
	 * Replace the full set of shares on an entity. Only the entity's creator or
	 * a house admin may manage shares; every recipient must be a house member.
	 *
	 * @param Share::TYPE_* $type
	 * @param array<array{uid: string, permission: string}> $entries
	 */
	public function setSharesForEntity(int $houseId, string $uid, string $type, int $entityId, array $entries): void {
		if (!$this->canManageShares($houseId, $uid, $type, $entityId)) {
			throw new ForbiddenException('You cannot manage sharing for this item');
		}
		foreach ($entries as $entry) {
			$recipient = (string)($entry['uid'] ?? '');
			if ($recipient === '' || $this->memberMapper->findForUserAndHouse($recipient, $houseId) === null) {
				throw new \InvalidArgumentException('Can only share with members of this house');
			}
		}
		$this->shareMapper->setSharesForEntity($houseId, $type, $entityId, $uid, $entries);
	}

	/**
	 * Whether a user may add/remove shares on an entity: house admins always
	 * may; otherwise only the entity's creator (notes/photos). Checklists and
	 * folders have no creator column, so those are admin-only.
	 *
	 * @param Share::TYPE_* $type
	 */
	public function canManageShares(int $houseId, string $uid, string $type, int $entityId): bool {
		if ($this->permissions->isAdmin($houseId, $uid)) {
			return true;
		}
		try {
			if ($type === Share::TYPE_NOTE) {
				return $this->noteMapper->findById($entityId)->getCreatedBy() === $uid;
			}
			if ($type === Share::TYPE_PHOTO) {
				return $this->photoMapper->findById($entityId)->getUploadedBy() === $uid;
			}
		} catch (DoesNotExistException) {
			return false;
		}
		return false;
	}

	/**
	 * @param Share::TYPE_* $type
	 */
	private function shareLevel(string $uid, string $type, int $entityId): string {
		$share = $this->shareMapper->findForUserAndEntity($uid, $type, $entityId);
		return $share?->getPermission() ?? PermissionService::LEVEL_NONE;
	}

	private function photoFolderId(int $photoId): ?int {
		try {
			return $this->photoMapper->findById($photoId)->getFolderId();
		} catch (DoesNotExistException) {
			return null;
		}
	}

	private function max(string $a, string $b): string {
		return (self::RANK[$a] ?? 0) >= (self::RANK[$b] ?? 0) ? $a : $b;
	}
}
