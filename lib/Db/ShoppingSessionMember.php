<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCP\AppFramework\Db\Entity;

/**
 * A housemate who joined someone else's shopping trip.
 *
 * The trip starter has no row here — ownership stays on
 * `shopping_sessions.user_id`. A trip's membership is therefore its owner plus
 * every row here whose `leftAt` is null.
 *
 * Leaving keeps the row and stamps `leftAt` so rejoining the same trip reuses
 * it, honouring the unique (session_id, user_id) index.
 *
 * @method int getSessionId()
 * @method void setSessionId(int $sessionId)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int getJoinedAt()
 * @method void setJoinedAt(int $joinedAt)
 * @method int|null getLeftAt()
 * @method void setLeftAt(?int $leftAt)
 */
class ShoppingSessionMember extends Entity {
	protected int $sessionId = 0;
	protected string $userId = '';
	protected int $joinedAt = 0;
	protected ?int $leftAt = null;

	public function __construct() {
		$this->addType('sessionId', 'integer');
		$this->addType('joinedAt', 'integer');
		$this->addType('leftAt', 'integer');
	}
}
