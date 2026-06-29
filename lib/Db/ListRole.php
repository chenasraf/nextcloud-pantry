<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getListId()
 * @method void setListId(int $listId)
 * @method int getRoleId()
 * @method void setRoleId(int $roleId)
 */
class ListRole extends Entity {
	protected int $listId = 0;
	protected int $roleId = 0;

	public function __construct() {
		$this->addType('listId', 'integer');
		$this->addType('roleId', 'integer');
	}
}
