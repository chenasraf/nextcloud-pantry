<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCP\AppFramework\Db\Entity;

/**
 * The per-trip check log: one row per item checked off during a session,
 * recording the store that was active at the time (null if none). The log
 * mirrors the session's current checked set — unchecking removes the row.
 *
 * @method int getSessionId()
 * @method void setSessionId(int $sessionId)
 * @method int getItemId()
 * @method void setItemId(int $itemId)
 * @method int|null getStoreId()
 * @method void setStoreId(?int $storeId)
 * @method int getCheckedAt()
 * @method void setCheckedAt(int $checkedAt)
 */
class ShoppingSessionItem extends Entity {
	protected int $sessionId = 0;
	protected int $itemId = 0;
	protected ?int $storeId = null;
	protected int $checkedAt = 0;

	public function __construct() {
		$this->addType('sessionId', 'integer');
		$this->addType('itemId', 'integer');
		$this->addType('storeId', 'integer');
		$this->addType('checkedAt', 'integer');
	}
}
