<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Latch\Payload;

/**
 * Filter payload representing a checklist item draft before it is persisted.
 *
 * Used by `item.before-create` (the entire input array is the draft) and
 * `item.before-update` (`$data` is the patch, `$existing` is the loaded item
 * as an associative array).
 *
 * Handlers mutate via `with*` helpers (immutable chaining, Latch convention).
 *
 * @psalm-import-type PantryListItem from \OCA\Pantry\ResponseDefinitions
 */
final class ChecklistItemPayload {
	/**
	 * @param int $listId
	 * @param array<string,mixed> $data Mutable draft fields (name, description, categoryId, …).
	 * @param string|null $actorUid User performing the operation, if known.
	 * @param array<string,mixed>|null $existing Existing item as array, for update flows; null on create.
	 */
	public function __construct(
		public readonly int $listId,
		public readonly array $data,
		public readonly ?string $actorUid = null,
		public readonly ?array $existing = null,
	) {
	}

	public function withData(array $data): self {
		return new self($this->listId, $data, $this->actorUid, $this->existing);
	}

	public function withField(string $key, mixed $value): self {
		$data = $this->data;
		$data[$key] = $value;
		return new self($this->listId, $data, $this->actorUid, $this->existing);
	}
}
