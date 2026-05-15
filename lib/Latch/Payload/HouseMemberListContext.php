<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Latch\Payload;

/**
 * Collector context for `house.member.list`.
 *
 * External apps invoke this to enumerate members of a household — for
 * assignment pickers, share targets, etc. Pantry's built-in handler
 * responds with MemberSummary[] sourced from HouseService::listMembers(),
 * gated by the viewerUid's membership in the same house.
 */
final class HouseMemberListContext {
	public function __construct(
		public readonly int $houseId,
		public readonly string $viewerUid,
	) {
	}
}
