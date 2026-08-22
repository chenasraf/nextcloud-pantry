<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Migration;

use OCA\Pantry\AppInfo\Application;
use OCP\DB\Types;

/**
 * Single source of truth for optional columns that item and shopping-mode
 * queries depend on but that schema drift can leave behind.
 *
 * When a migration is recorded as executed without its column actually landing,
 * the app keeps querying a column that isn't there
 * and every affected request errors out. Declaring the columns here lets both
 * `occ db:add-missing-columns` (via {@see \OCA\Pantry\Listener\AddMissingColumnsListener})
 * and `occ pantry:repair-schema` (via {@see SchemaRepairService}) recreate them.
 *
 * Only nullable columns already introduced by an earlier migration belong here;
 * this is a self-healing net for drift, not a place to define new schema.
 */
final class ExpectedColumns {
	/**
	 * @return list<array{table: string, name: string, type: string, options: array<string, mixed>}>
	 */
	public static function all(): array {
		return [
			// list_items — checklist item columns queried on every list load.
			// archived_at (Version13); the item-list filter references it
			// unconditionally, so a missing column takes down the whole checklist.
			self::col('list_items', 'archived_at', Types::BIGINT, ['notnull' => false, 'length' => 20]),

			// categories — list scoping (Version27); category queries filter on
			// list_id, so a missing column breaks category loading and the
			// category-sorted item list.
			self::col('categories', 'list_id', Types::BIGINT, ['notnull' => false, 'length' => 20]),

			// shopsess_items — per-trip snapshot columns (Version21).
			self::col('shopsess_items', 'item_name', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => null]),
			self::col('shopsess_items', 'quantity', Types::STRING, ['notnull' => false, 'length' => 64, 'default' => null]),
			self::col('shopsess_items', 'price_type', Types::STRING, ['notnull' => false, 'length' => 8, 'default' => null]),
			self::col('shopsess_items', 'price_min', Types::FLOAT, ['notnull' => false, 'default' => null]),
			self::col('shopsess_items', 'price_max', Types::FLOAT, ['notnull' => false, 'default' => null]),
			self::col('shopsess_items', 'price_currency', Types::STRING, ['notnull' => false, 'length' => 8, 'default' => null]),
		];
	}

	/**
	 * @param array<string, mixed> $options
	 * @return array{table: string, name: string, type: string, options: array<string, mixed>}
	 */
	private static function col(string $table, string $name, string $type, array $options): array {
		return [
			'table' => Application::tableName($table),
			'name' => $name,
			'type' => $type,
			'options' => $options,
		];
	}
}
