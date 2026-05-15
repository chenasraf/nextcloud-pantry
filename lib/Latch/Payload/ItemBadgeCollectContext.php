<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Latch\Payload;

use OCA\Pantry\Db\ChecklistItem;

/**
 * Collector context for `item.metadata-badges`.
 *
 * Handlers return arrays of `{label, color?, icon?}` rendered as badges on
 * the item in the API response.
 */
final class ItemBadgeCollectContext {
	public function __construct(
		public readonly ChecklistItem $item,
		public readonly ?string $viewerUid,
	) {
	}
}
