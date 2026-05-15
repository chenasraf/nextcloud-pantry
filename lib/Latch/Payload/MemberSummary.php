<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Latch\Payload;

/**
 * Compact house-member DTO returned from the `house.member.list` collector.
 */
final class MemberSummary implements \JsonSerializable {
	public function __construct(
		public readonly int $id,
		public readonly int $houseId,
		public readonly string $userId,
		public readonly string $displayName,
		public readonly string $role,
	) {
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'houseId' => $this->houseId,
			'userId' => $this->userId,
			'displayName' => $this->displayName,
			'role' => $this->role,
		];
	}
}
