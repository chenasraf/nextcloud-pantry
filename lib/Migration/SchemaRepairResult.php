<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Migration;

/**
 * Outcome of a {@see SchemaRepairService::repair()} run.
 */
class SchemaRepairResult {
	/**
	 * @param list<string> $added Columns added (or, in dry-run, that would be added), as "table.column".
	 * @param list<string> $missingTables Expected tables that do not exist, so their columns were skipped.
	 * @param string $sql The change script, populated only in dry-run.
	 * @param list<string> $dropped Legacy columns dropped (or, in dry-run, that would be dropped), as "table.column".
	 */
	public function __construct(
		public readonly array $added,
		public readonly array $missingTables,
		public readonly string $sql,
		public readonly bool $dryRun,
		public readonly array $dropped = [],
	) {
	}

	public function madeChanges(): bool {
		return $this->added !== [] || $this->dropped !== [];
	}
}
