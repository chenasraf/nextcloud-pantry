<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Latch\Payload;

/**
 * Collector context for `house.list`.
 *
 * External apps invoke this collect point to ask Pantry "which households
 * does this user belong to?" so they can present a household picker.
 * Pantry's built-in `PantryProvider` handler responds with HouseSummary[]
 * sourced from HouseService::listForUser().
 */
final class HouseListContext {
	public function __construct(
		public readonly string $userId,
	) {
	}
}
