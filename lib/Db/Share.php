<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getHouseId()
 * @method void setHouseId(int $houseId)
 * @method string getEntityType()
 * @method void setEntityType(string $entityType)
 * @method int getEntityId()
 * @method void setEntityId(int $entityId)
 * @method string getSharedWithUid()
 * @method void setSharedWithUid(string $sharedWithUid)
 * @method string getPermission()
 * @method void setPermission(string $permission)
 * @method string getCreatedBy()
 * @method void setCreatedBy(string $createdBy)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class Share extends Entity implements \JsonSerializable {
	public const TYPE_CHECKLIST = 'checklist';
	public const TYPE_NOTE = 'note';
	public const TYPE_PHOTO = 'photo';
	// Namespaced so future folder kinds (e.g. note folders) get their own type.
	public const TYPE_PHOTO_FOLDER = 'photos-folder';

	public const PERM_VIEW = 'view';
	public const PERM_EDIT = 'edit';

	/** @var list<string> */
	public const TYPES = [self::TYPE_CHECKLIST, self::TYPE_NOTE, self::TYPE_PHOTO, self::TYPE_PHOTO_FOLDER];

	protected int $houseId = 0;
	protected string $entityType = '';
	protected int $entityId = 0;
	protected string $sharedWithUid = '';
	protected string $permission = self::PERM_VIEW;
	protected string $createdBy = '';
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('houseId', 'integer');
		$this->addType('entityId', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'houseId' => $this->houseId,
			'entityType' => $this->entityType,
			'entityId' => $this->entityId,
			'sharedWithUid' => $this->sharedWithUid,
			'permission' => $this->permission,
			'createdBy' => $this->createdBy,
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
		];
	}
}
