<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCP\AppFramework\Db\Entity;

/**
 * One item skipped from a live shopping session: hidden from this trip's
 * shopping list without touching the checklist item (it stays on the list and
 * is not marked done). Reversible — unskipping deletes the row.
 *
 * @method int getSessionId()
 * @method void setSessionId(int $sessionId)
 * @method int getItemId()
 * @method void setItemId(int $itemId)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 */
class ShoppingSessionSkip extends Entity {
	protected int $sessionId = 0;
	protected int $itemId = 0;
	protected int $createdAt = 0;

	public function __construct() {
		$this->addType('sessionId', 'integer');
		$this->addType('itemId', 'integer');
		$this->addType('createdAt', 'integer');
	}
}
