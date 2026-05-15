<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Latch\Payload;

/**
 * Collector context for `category.suggestions`.
 *
 * Handlers return a list of suggested category ids (int) for the given
 * item draft — most handlers will return either an empty list or a list
 * with a single id. The collector takes the first int in the merged
 * result, so handlers that want strong precedence should set a low
 * `priority()` value.
 */
final class CategorySuggestionContext {
	public function __construct(
		public readonly int $houseId,
		public readonly int $listId,
		public readonly string $itemName,
		public readonly ?string $quantity = null,
		public readonly ?string $description = null,
	) {
	}
}
