<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getHouseId()
 * @method void setHouseId(int $houseId)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getRole()
 * @method void setRole(string $role)
 * @method int getJoinedAt()
 * @method void setJoinedAt(int $joinedAt)
 */
class HouseMember extends Entity implements \JsonSerializable {
	public const ROLE_OWNER = 'owner';
	public const ROLE_ADMIN = 'admin';
	public const ROLE_MEMBER = 'member';

	protected int $houseId = 0;
	protected string $userId = '';
	protected string $role = self::ROLE_MEMBER;
	protected int $joinedAt = 0;

	public function __construct() {
		$this->addType('houseId', 'integer');
		$this->addType('joinedAt', 'integer');
	}

	public function isAtLeastAdmin(): bool {
		return $this->role === self::ROLE_OWNER || $this->role === self::ROLE_ADMIN;
	}

	public function isOwner(): bool {
		return $this->role === self::ROLE_OWNER;
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'houseId' => $this->houseId,
			'userId' => $this->userId,
			'role' => $this->role,
			'joinedAt' => $this->joinedAt,
		];
	}
}
