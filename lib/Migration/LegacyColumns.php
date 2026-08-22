<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Migration;

use OCA\Pantry\AppInfo\Application;

/**
 * Single source of truth for columns a later migration was supposed to drop but
 * that schema drift can leave behind.
 *
 * The mirror image of {@see ExpectedColumns}: when a migration is recorded as
 * executed without its `dropColumn()` actually reaching the database, the column
 * lingers. Because item queries hydrate with `SELECT *`, a stray column the
 * entity no longer knows about makes {@see \OCP\AppFramework\Db\Entity::fromRow()}
 * throw "<attribute> is not a valid attribute" on every request — the whole
 * checklist stops loading.
 *
 * Declaring the leftovers here lets `occ pantry:repair-schema` (via
 * {@see SchemaRepairService}) drop them so reads recover. Core's
 * `occ db:add-missing-columns` only ever adds columns, so it cannot heal this.
 */
final class LegacyColumns {
	/**
	 * @return list<array{table: string, name: string}>
	 */
	public static function all(): array {
		return [
			// list_items — per-item price columns (Version19) moved to
			// pantry_item_prices by Version25 and dropped by Version26. If the
			// drop drifted, every list load throws "priceType is not a valid
			// attribute" because the entity no longer declares them.
			self::col('list_items', 'price_type'),
			self::col('list_items', 'price_min'),
			self::col('list_items', 'price_max'),
			self::col('list_items', 'price_currency'),
		];
	}

	/**
	 * @return array{table: string, name: string}
	 */
	private static function col(string $table, string $name): array {
		return [
			'table' => Application::tableName($table),
			'name' => $name,
		];
	}
}
