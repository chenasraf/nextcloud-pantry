<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

/**
 * Canonical lists of enumerated values used across the app (icon keys, color
 * palettes). The frontend mirrors these lists in TypeScript; whenever you
 * change one side make sure to update the other.
 */
class ConstantsService {
	/** Category icon keys, mirrored in src/components/CategoryPicker/categoryIcons.ts */
	public const CATEGORY_ICON_KEYS = [
		'tag',
		'food',
		'fruit',
		'vegetable',
		'bakery',
		'dairy',
		'meat',
		'fish',
		'snacks',
		'cookie',
		'drinks',
		'coffee',
		'frozen',
		'household',
		'pets',
		'baby',
		'home',
		'leaf',
		'pizza',
	];

	/** Default category color palette, mirrored in the frontend. */
	public const CATEGORY_COLORS = [
		'#ef4444',
		'#f97316',
		'#eab308',
		'#22c55e',
		'#14b8a6',
		'#0ea5e9',
		'#6366f1',
		'#a855f7',
		'#ec4899',
		'#78716c',
	];

	/** Checklist icon keys, mirrored in src/components/ChecklistIconPicker/checklistIcons.ts */
	public const CHECKLIST_ICON_KEYS = [
		'clipboard-check',
		'clipboard-list',
		'format-list-checks',
		'cart',
		'basket',
		'star',
		'heart',
		'home',
		'calendar',
		'bell',
		'flag',
		'bookmark',
		'pin',
		'map-marker',
		'briefcase',
		'wrench',
		'silverware',
		'coffee',
		'gift',
		'book',
		'school',
		'palette',
		'camera',
		'music',
		'gamepad',
		'run',
		'dumbbell',
		'pill',
		'paw',
		'flower',
		'tree',
		'broom',
		'lightbulb',
		'package',
		'car',
		'bike',
		'beach',
		'tag',
	];

	/** Default note color palette, mirrored in src/components/Notes/noteColors.ts */
	public const NOTE_COLORS = [
		'#f44336',
		'#e91e63',
		'#9c27b0',
		'#673ab7',
		'#3f51b5',
		'#2196f3',
		'#03a9f4',
		'#00bcd4',
		'#009688',
		'#4caf50',
		'#8bc34a',
		'#cddc39',
		'#ffeb3b',
		'#ffc107',
		'#ff9800',
		'#ff5722',
	];

	/**
	 * @return array{
	 *     categoryIcons: list<string>,
	 *     categoryColors: list<string>,
	 *     checklistIcons: list<string>,
	 *     noteColors: list<string>,
	 * }
	 */
	public function all(): array {
		return [
			'categoryIcons' => self::CATEGORY_ICON_KEYS,
			'categoryColors' => self::CATEGORY_COLORS,
			'checklistIcons' => self::CHECKLIST_ICON_KEYS,
			'noteColors' => self::NOTE_COLORS,
		];
	}
}
