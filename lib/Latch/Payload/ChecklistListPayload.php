<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Latch\Payload;

/**
 * Filter payload representing a checklist (list) draft before persistence.
 *
 * On create, $data holds the full draft (name, description, icon, …) and
 * $existing is null. On update, $data is the patch and $existing is the
 * current list serialized as an array.
 */
final class ChecklistListPayload {
	/**
	 * @param int $houseId
	 * @param array<string,mixed> $data
	 * @param string|null $actorUid
	 * @param array<string,mixed>|null $existing
	 */
	public function __construct(
		public readonly int $houseId,
		public readonly array $data,
		public readonly ?string $actorUid = null,
		public readonly ?array $existing = null,
	) {
	}

	public function withData(array $data): self {
		return new self($this->houseId, $data, $this->actorUid, $this->existing);
	}

	public function withField(string $key, mixed $value): self {
		$data = $this->data;
		$data[$key] = $value;
		return new self($this->houseId, $data, $this->actorUid, $this->existing);
	}
}
