<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Config;

use OCA\Pantry\Service\Barcode\OpenFoodFactsProvider;
use OCP\Config\Lexicon\Entry;
use OCP\Config\Lexicon\ILexicon;
use OCP\Config\Lexicon\Strictness;
use OCP\Config\ValueType;

/**
 * Only the fixed keys are listed. Per-house preferences (`<key>_<houseId>`)
 * and notification accumulators (`notif_state_<hash>`) are built at runtime
 * and cannot be declared here.
 */
class ConfigLexicon implements ILexicon {
	public function getStrictness(): Strictness {
		// Any stricter level makes the server drop reads and writes of the
		// dynamic per-house keys above.
		return Strictness::IGNORE;
	}

	public function getAppConfigs(): array {
		return [
			new Entry('barcode_provider', ValueType::STRING, OpenFoodFactsProvider::ID, 'Barcode lookup provider used to resolve scanned products'),
			new Entry('shopping_history_retention_days', ValueType::INT, 0, 'Days to keep closed shopping sessions before purging them (0 keeps them forever)'),
		];
	}

	public function getUserConfigs(): array {
		return [
			new Entry('last_house_id', ValueType::INT, null, 'House the user last opened'),
			new Entry('language', ValueType::STRING, null, 'Language override for the app; unset follows the Nextcloud language'),
			new Entry('row_click_action', ValueType::STRING, null, 'Action when clicking a checklist row: done, view, edit or none'),
			new Entry('tap_row_to_complete', ValueType::BOOL, false, 'Fallback for row_click_action when it is unset: true maps to done'),
			new Entry('reuse_existing_items', ValueType::STRING, 'ask', 'Adding an item that already exists: ask, reuse or never'),
			new Entry('suggest_archived_items', ValueType::BOOL, false, 'Whether archived items appear in add-item suggestions'),
			new Entry('barcode_fill_name', ValueType::BOOL, true, 'Whether a barcode scan fills in the item name'),
			new Entry('barcode_fill_category', ValueType::BOOL, true, 'Whether a barcode scan fills in the item category'),
			new Entry('barcode_fill_image', ValueType::BOOL, true, 'Whether a barcode scan fills in the item image'),
		];
	}
}
