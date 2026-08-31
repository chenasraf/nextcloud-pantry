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

			// lists — archived_at (Version29); the active-list query filters on it
			// unconditionally, so a missing column breaks the whole list index.
			self::col('lists', 'archived_at', Types::BIGINT, ['notnull' => false, 'length' => 20]),

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

			// roles — custom-fields capability (Version30). The role SELECT * hydrates
			// every column into the entity, so a missing column breaks role loading
			// and, with it, every permission-gated request.
			self::col('roles', 'can_edit_fields', Types::BOOLEAN, ['notnull' => false, 'default' => false]),

			// field_defs — optional config columns (Version30). Definition queries
			// hydrate the whole row, so a missing one breaks custom-field loading.
			self::col('field_defs', 'list_id', Types::BIGINT, ['notnull' => false, 'length' => 20]),
			self::col('field_defs', 'hint', Types::STRING, ['notnull' => false, 'length' => 255]),
			self::col('field_defs', 'multiline', Types::BOOLEAN, ['notnull' => false, 'default' => false]),
			self::col('field_defs', 'default_text', Types::STRING, ['notnull' => false, 'length' => 1024]),
			self::col('field_defs', 'default_number', Types::FLOAT, ['notnull' => false, 'default' => null]),
			self::col('field_defs', 'default_bool', Types::BOOLEAN, ['notnull' => false, 'default' => false]),
			self::col('field_defs', 'default_option_id', Types::BIGINT, ['notnull' => false, 'length' => 20]),
			self::col('field_defs', 'date_mode', Types::STRING, ['notnull' => false, 'length' => 16]),
			self::col('field_defs', 'default_offset_days', Types::INTEGER, ['notnull' => false, 'default' => null]),
			self::col('field_defs', 'notify_default', Types::BOOLEAN, ['notnull' => false, 'default' => false]),
			self::col('field_defs', 'lead_days', Types::INTEGER, ['notnull' => false, 'default' => 0]),
			self::col('field_defs', 'override_policy', Types::STRING, ['notnull' => false, 'length' => 16]),
			self::col('field_defs', 'stop_when_done', Types::BOOLEAN, ['notnull' => false, 'default' => false]),
			self::col('field_defs', 'deleted_at', Types::BIGINT, ['notnull' => false, 'length' => 20]),

			// field_values — typed value + reminder columns (Version30). Populated by
			// value writes; the reminder scan and SELECT * hydration touch these.
			self::col('field_values', 'value_text', Types::TEXT, ['notnull' => false, 'default' => null]),
			self::col('field_values', 'value_number', Types::FLOAT, ['notnull' => false, 'default' => null]),
			self::col('field_values', 'value_bool', Types::BOOLEAN, ['notnull' => false, 'default' => false]),
			self::col('field_values', 'value_date', Types::BIGINT, ['notnull' => false, 'length' => 20]),
			self::col('field_values', 'value_option_id', Types::BIGINT, ['notnull' => false, 'length' => 20]),
			self::col('field_values', 'offset_days', Types::INTEGER, ['notnull' => false, 'default' => null]),
			self::col('field_values', 'notify_override', Types::BOOLEAN, ['notnull' => false, 'default' => false]),
			self::col('field_values', 'notify_enabled', Types::BOOLEAN, ['notnull' => false, 'default' => false]),
			self::col('field_values', 'notify_lead_days', Types::INTEGER, ['notnull' => false, 'default' => null]),
			self::col('field_values', 'notified_for_date', Types::BIGINT, ['notnull' => false, 'length' => 20]),
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
