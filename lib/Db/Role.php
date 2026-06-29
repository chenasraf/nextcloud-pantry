<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getHouseId()
 * @method void setHouseId(int $houseId)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getRoleType()
 * @method void setRoleType(string $roleType)
 * @method bool getCanViewLists()
 * @method void setCanViewLists(bool $v)
 * @method bool getCanCreateLists()
 * @method void setCanCreateLists(bool $v)
 * @method bool getCanEditLists()
 * @method void setCanEditLists(bool $v)
 * @method bool getCanDeleteLists()
 * @method void setCanDeleteLists(bool $v)
 * @method bool getCanAddItems()
 * @method void setCanAddItems(bool $v)
 * @method bool getCanDeleteItems()
 * @method void setCanDeleteItems(bool $v)
 * @method bool getCanCopyItems()
 * @method void setCanCopyItems(bool $v)
 * @method bool getCanMoveItems()
 * @method void setCanMoveItems(bool $v)
 * @method bool getCanCheckItems()
 * @method void setCanCheckItems(bool $v)
 * @method bool getCanViewPhotos()
 * @method void setCanViewPhotos(bool $v)
 * @method bool getCanUploadPhotos()
 * @method void setCanUploadPhotos(bool $v)
 * @method bool getCanUpdatePhotos()
 * @method void setCanUpdatePhotos(bool $v)
 * @method bool getCanDeletePhotos()
 * @method void setCanDeletePhotos(bool $v)
 * @method bool getCanMovePhotos()
 * @method void setCanMovePhotos(bool $v)
 * @method bool getCanViewNotes()
 * @method void setCanViewNotes(bool $v)
 * @method bool getCanCreateNotes()
 * @method void setCanCreateNotes(bool $v)
 * @method bool getCanUpdateNotes()
 * @method void setCanUpdateNotes(bool $v)
 * @method bool getCanDeleteNotes()
 * @method void setCanDeleteNotes(bool $v)
 */
class Role extends Entity implements \JsonSerializable {
	public const TYPE_ADMIN = 'admin';
	public const TYPE_DEFAULT = 'default';
	public const TYPE_NORMAL = 'normal';

	/**
	 * Capability key (camelCase, used in #[Permission] attributes and the API)
	 * mapped to the entity property name.
	 *
	 * @var array<string, string>
	 */
	public const CAPABILITIES = [
		'canViewLists' => 'canViewLists',
		'canCreateLists' => 'canCreateLists',
		'canEditLists' => 'canEditLists',
		'canDeleteLists' => 'canDeleteLists',
		'canAddItems' => 'canAddItems',
		'canDeleteItems' => 'canDeleteItems',
		'canCopyItems' => 'canCopyItems',
		'canMoveItems' => 'canMoveItems',
		'canCheckItems' => 'canCheckItems',
		'canViewPhotos' => 'canViewPhotos',
		'canUploadPhotos' => 'canUploadPhotos',
		'canUpdatePhotos' => 'canUpdatePhotos',
		'canDeletePhotos' => 'canDeletePhotos',
		'canMovePhotos' => 'canMovePhotos',
		'canViewNotes' => 'canViewNotes',
		'canCreateNotes' => 'canCreateNotes',
		'canUpdateNotes' => 'canUpdateNotes',
		'canDeleteNotes' => 'canDeleteNotes',
	];

	protected int $houseId = 0;
	protected string $name = '';
	protected string $roleType = self::TYPE_NORMAL;

	protected bool $canViewLists = false;
	protected bool $canCreateLists = false;
	protected bool $canEditLists = false;
	protected bool $canDeleteLists = false;
	protected bool $canAddItems = false;
	protected bool $canDeleteItems = false;
	protected bool $canCopyItems = false;
	protected bool $canMoveItems = false;
	protected bool $canCheckItems = false;
	protected bool $canViewPhotos = false;
	protected bool $canUploadPhotos = false;
	protected bool $canUpdatePhotos = false;
	protected bool $canDeletePhotos = false;
	protected bool $canMovePhotos = false;
	protected bool $canViewNotes = false;
	protected bool $canCreateNotes = false;
	protected bool $canUpdateNotes = false;
	protected bool $canDeleteNotes = false;

	public function __construct() {
		$this->addType('houseId', 'integer');
		foreach (self::CAPABILITIES as $prop) {
			$this->addType($prop, 'boolean');
		}
		// Force role_type into INSERTs even when it matches the PHP default
		// ('normal') — role_type has no DB default, so omitting it violates the
		// not-null constraint. (Same pattern as HouseMember::$role.)
		$this->markFieldUpdated('roleType');
	}

	public function isAdmin(): bool {
		return $this->roleType === self::TYPE_ADMIN;
	}

	/**
	 * Built-in roles (Admin/Member) cannot be deleted and their role_type is
	 * immutable. Only their name (and, for Member, capabilities) may change.
	 */
	public function isBuiltin(): bool {
		return $this->roleType === self::TYPE_ADMIN || $this->roleType === self::TYPE_DEFAULT;
	}

	/**
	 * Whether this role grants the given capability. Admin roles grant
	 * everything regardless of the stored column values.
	 */
	public function hasCapability(string $capKey): bool {
		if ($this->isAdmin()) {
			return true;
		}
		$prop = self::CAPABILITIES[$capKey] ?? null;
		if ($prop === null) {
			return false;
		}
		return (bool)$this->{'get' . ucfirst($prop)}();
	}

	/**
	 * @return array<string, bool>
	 */
	public function capabilityMap(): array {
		$out = [];
		foreach (self::CAPABILITIES as $key => $prop) {
			$out[$key] = $this->hasCapability($key);
		}
		return $out;
	}

	public function jsonSerialize(): array {
		return array_merge([
			'id' => $this->id,
			'houseId' => $this->houseId,
			'name' => $this->name,
			'roleType' => $this->roleType,
		], $this->capabilityMap());
	}
}
