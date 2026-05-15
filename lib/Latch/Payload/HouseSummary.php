<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Latch\Payload;

/**
 * Compact household DTO returned from the `house.list` collector.
 *
 * Decoupled from the DB entity so external apps don't need to depend on
 * `OCA\Pantry\Db\House` classes.
 */
final class HouseSummary implements \JsonSerializable {
	public function __construct(
		public readonly int $id,
		public readonly string $name,
		public readonly ?string $description,
		public readonly string $ownerUid,
		public readonly string $viewerRole,
	) {
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'name' => $this->name,
			'description' => $this->description,
			'ownerUid' => $this->ownerUid,
			'viewerRole' => $this->viewerRole,
		];
	}
}
