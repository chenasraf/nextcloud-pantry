<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Latch\Payload;

use OCA\Pantry\Db\HouseMember;

/**
 * Action payload broadcast on house membership changes (add, remove, role change).
 *
 * For role changes, $previousRole holds the role before the update.
 */
final class HouseMemberEventPayload {
	public function __construct(
		public readonly HouseMember $member,
		public readonly ?string $actorUid = null,
		public readonly ?string $previousRole = null,
	) {
	}
}
