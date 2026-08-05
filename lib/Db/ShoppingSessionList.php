<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCP\AppFramework\Db\Entity;

/**
 * A checklist in a shopping session's scope (session ⋈ list join).
 *
 * @method int getSessionId()
 * @method void setSessionId(int $sessionId)
 * @method int getListId()
 * @method void setListId(int $listId)
 */
class ShoppingSessionList extends Entity {
	protected int $sessionId = 0;
	protected int $listId = 0;

	public function __construct() {
		$this->addType('sessionId', 'integer');
		$this->addType('listId', 'integer');
	}
}
