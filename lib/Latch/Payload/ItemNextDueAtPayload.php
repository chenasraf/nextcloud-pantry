<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Latch\Payload;

use OCA\Pantry\Db\ChecklistItem;

/**
 * Filter payload for recurring-item next-due-at computation.
 *
 * Pantry computes the next due timestamp from the item's rrule and either
 * "now" (repeat-from-completion) or the creation anchor (fixed schedule).
 * Handlers can override this — useful when an external scheduler owns the
 * actual cadence (calendar app, supply-chain replenishment integration, …).
 *
 * `$nextDueAt` is the unix timestamp Pantry would have written; null means
 * Pantry intends to clear the schedule. Handlers return a new payload via
 * `withNextDueAt()`.
 */
final class ItemNextDueAtPayload {
	public function __construct(
		public readonly ChecklistItem $item,
		public readonly ?int $nextDueAt,
		public readonly int $now,
	) {
	}

	public function withNextDueAt(?int $ts): self {
		return new self($this->item, $ts, $this->now);
	}
}
