<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Latch\Payload;

use OCA\Pantry\Db\House;

/**
 * Action payload broadcast after a house lifecycle change.
 */
final class HouseEventPayload {
	public function __construct(
		public readonly House $house,
		public readonly ?string $actorUid = null,
	) {
	}
}
