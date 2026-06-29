<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getMemberId()
 * @method void setMemberId(int $memberId)
 * @method int getRoleId()
 * @method void setRoleId(int $roleId)
 */
class HouseMemberRole extends Entity {
	protected int $memberId = 0;
	protected int $roleId = 0;

	public function __construct() {
		$this->addType('memberId', 'integer');
		$this->addType('roleId', 'integer');
	}
}
