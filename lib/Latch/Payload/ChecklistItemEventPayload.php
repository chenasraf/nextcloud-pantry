<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Latch\Payload;

use OCA\Pantry\Db\ChecklistItem;

/**
 * Action payload broadcast after a checklist item lifecycle change.
 *
 * `$previous` is set on update events (snapshot before the change as an array)
 * so handlers can diff without re-querying.
 */
final class ChecklistItemEventPayload {
	/**
	 * @param array<string,mixed>|null $previous Pre-change snapshot, or null for create-style events.
	 */
	public function __construct(
		public readonly ChecklistItem $item,
		public readonly ?string $actorUid = null,
		public readonly ?array $previous = null,
	) {
	}
}
