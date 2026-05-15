<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Latch\Payload;

use OCA\Pantry\Db\ChecklistItem;

/**
 * Collector context for `item.extra-actions`.
 *
 * Handlers return arrays of `{id, label, icon?, url?}` rendered as extra
 * row actions for the item in the API response.
 */
final class ItemActionCollectContext {
	public function __construct(
		public readonly ChecklistItem $item,
		public readonly ?string $viewerUid,
	) {
	}
}
