<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Latch\Payload;

use OCA\Pantry\Db\ChecklistItem;

/**
 * Filter payload for item name display post-processing.
 *
 * Handlers transform `$name` and return a new payload via `withName()`.
 * The original $item is read-only context so handlers can branch on item
 * state (category, qty, …) without re-querying.
 */
final class ItemNameRenderPayload {
	public function __construct(
		public readonly ChecklistItem $item,
		public readonly string $name,
		public readonly ?string $viewerUid = null,
	) {
	}

	public function withName(string $name): self {
		return new self($this->item, $name, $this->viewerUid);
	}
}
