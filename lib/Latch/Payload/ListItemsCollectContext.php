<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Latch\Payload;

/**
 * Collector context for `list.contributed-items`.
 *
 * Handlers return a list of associative arrays matching the PantryListItem
 * shape (without an `id` — contributed items are not stored). Each item
 * should set `contributedBy` to the producing handler's name (Latch does
 * not pass handler identity to the source); contributions without it
 * default to `'external'` on serialization.
 */
final class ListItemsCollectContext {
	public function __construct(
		public readonly int $listId,
		public readonly int $houseId,
		public readonly ?string $viewerUid,
	) {
	}
}
