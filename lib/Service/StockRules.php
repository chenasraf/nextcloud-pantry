<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

/**
 * How much of an inventory item is on hand, and what it takes to restock it.
 *
 * Every stock value is free text like any quantity: the first number in it is
 * its value and the rest (unit, notes) is left alone. The web client ports
 * these rules for optimistic updates, and both are pinned by the shared
 * vectors in tests/stock-vectors.json.
 */
final class StockRules {
	public const MODE_COUNT = 'count';
	public const MODE_LEVEL = 'level';
	public const MODES = [self::MODE_COUNT, self::MODE_LEVEL];

	public const LEVEL_FULL = 'full';
	public const LEVEL_LOW = 'low';
	public const LEVEL_OUT = 'out';
	public const LEVELS = [self::LEVEL_FULL, self::LEVEL_LOW, self::LEVEL_OUT];

	public const STATUS_OK = 'ok';
	public const STATUS_LOW = 'low';
	public const STATUS_OUT = 'out';
	public const STATUS_UNCOUNTED = 'uncounted';

	public const EXPIRY_NONE = 'none';
	public const EXPIRY_OK = 'ok';
	public const EXPIRY_EXPIRING = 'expiring';
	public const EXPIRY_EXPIRED = 'expired';

	public const MAX_SHELF_LIFE_DAYS = 36500;
	public const MAX_EXPIRY_LEAD_DAYS = 365;

	private const FIRST_NUMBER = '/\d+(?:\.\d+)?/';

	public static function firstNumber(?string $text): ?float {
		if ($text === null || preg_match(self::FIRST_NUMBER, $text, $match) !== 1) {
			return null;
		}
		return (float)$match[0];
	}

	/**
	 * `$text` with its first number swapped for `$value`; text without a
	 * number is returned as it is.
	 */
	public static function withNumber(string $text, float $value): string {
		return preg_replace(self::FIRST_NUMBER, self::formatNumber($value), $text, 1) ?? $text;
	}

	/**
	 * Rounded to six places, so subtracting decimals never shows float noise
	 * ("1.2" - "1.1" is "0.1").
	 */
	public static function formatNumber(float $value): string {
		$rounded = round($value, 6);
		if ($rounded == floor($rounded)) {
			return (string)(int)$rounded;
		}
		return rtrim(rtrim(sprintf('%.6F', $rounded), '0'), '.');
	}

	/**
	 * @param array{stockMode: ?string, stockLevel: ?string, quantity: ?string, target: ?string, reorderPoint: ?string, ...} $item
	 * @return array{stockStatus: 'ok'|'low'|'out'|'uncounted', shortfall: ?string}
	 */
	public static function evaluate(array $item): array {
		if ($item['stockMode'] === self::MODE_LEVEL) {
			return self::evaluateLevel($item['stockLevel'], $item['quantity']);
		}
		return self::evaluateCount($item['quantity'], $item['target'], $item['reorderPoint']);
	}

	/**
	 * @param array{stockMode: ?string, stockLevel: ?string, quantity: ?string, target: ?string, reorderPoint: ?string, ...} $item
	 */
	public static function needsRestock(array $item): bool {
		$status = self::evaluate($item)['stockStatus'];
		return $status === self::STATUS_LOW || $status === self::STATUS_OUT;
	}

	/**
	 * Where an item stands against its expiry date. It is good through the
	 * date and expired from the day after. It is expiring from `$leadDays`
	 * before the date, but only while fewer days are left than its shelf life,
	 * so a short-lived item that was just restocked is not flagged at once. An
	 * item with none left reports none: there is nothing to go off. One not
	 * counted still has some, so it reports.
	 *
	 * @param array{stockMode: ?string, stockLevel: ?string, quantity: ?string, expiresAt: ?int, shelfLifeDays: ?int, ...} $item
	 * @param int $today Midnight of the current day.
	 * @return 'none'|'ok'|'expiring'|'expired'
	 */
	public static function expiryStatus(array $item, int $leadDays, int $today): string {
		if ($item['expiresAt'] === null || self::isEmpty($item)) {
			return self::EXPIRY_NONE;
		}
		$daysLeft = self::daysUntil($item['expiresAt'], $today);
		if ($daysLeft < 0) {
			return self::EXPIRY_EXPIRED;
		}
		$shelfLife = $item['shelfLifeDays'];
		if ($daysLeft <= max(0, $leadDays) && ($shelfLife === null || $daysLeft < $shelfLife)) {
			return self::EXPIRY_EXPIRING;
		}
		return self::EXPIRY_OK;
	}

	public static function expiryNeedsRestock(string $expiryStatus): bool {
		return $expiryStatus === self::EXPIRY_EXPIRING || $expiryStatus === self::EXPIRY_EXPIRED;
	}

	/**
	 * Whole days from one midnight to another. Rounded, so a daylight-saving
	 * shift, or a date picked at midnight in another timezone, still lands on
	 * its day.
	 */
	public static function daysUntil(int $date, int $today): int {
		return (int)round(($date - $today) / 86400);
	}

	/** Midnight of the current day, the way date fields resolve "today". */
	public static function today(): int {
		return (new \DateTimeImmutable('today'))->getTimestamp();
	}

	/** Midnight `$days` days after the midnight `$day`. */
	public static function addDays(int $day, int $days): int {
		return (new \DateTimeImmutable())->setTimestamp($day)->modify(sprintf('%+d days', $days))->getTimestamp();
	}

	/**
	 * @param array{stockMode: ?string, stockLevel: ?string, quantity: ?string, ...} $item
	 */
	private static function isEmpty(array $item): bool {
		if ($item['stockMode'] === self::MODE_LEVEL) {
			return $item['stockLevel'] === self::LEVEL_OUT;
		}
		$onHand = trim($item['quantity'] ?? '');
		return $onHand === '' || self::firstNumber($onHand) === 0.0;
	}

	/**
	 * @return array{stockStatus: 'ok'|'low'|'out'|'uncounted', shortfall: ?string}
	 */
	private static function evaluateLevel(?string $level, ?string $restock): array {
		if ($level !== self::LEVEL_LOW && $level !== self::LEVEL_OUT) {
			return ['stockStatus' => self::STATUS_OK, 'shortfall' => null];
		}
		$restock = trim($restock ?? '');
		return [
			'stockStatus' => $level === self::LEVEL_OUT ? self::STATUS_OUT : self::STATUS_LOW,
			'shortfall' => $restock !== '' ? $restock : null,
		];
	}

	/**
	 * @return array{stockStatus: 'ok'|'low'|'out'|'uncounted', shortfall: ?string}
	 */
	private static function evaluateCount(?string $onHandText, ?string $targetText, ?string $reorderText): array {
		$onHandText = trim($onHandText ?? '');
		$onHand = $onHandText === '' ? 0.0 : self::firstNumber($onHandText);
		$target = self::firstNumber($targetText);
		if ($onHand === null || $target === null) {
			return ['stockStatus' => self::STATUS_UNCOUNTED, 'shortfall' => null];
		}
		$reorder = self::firstNumber($reorderText);
		$low = $reorder !== null ? $onHand <= $reorder : $onHand < $target;
		if (!$low) {
			return ['stockStatus' => self::STATUS_OK, 'shortfall' => null];
		}
		$missing = $target - $onHand;
		return [
			'stockStatus' => $onHand == 0 ? self::STATUS_OUT : self::STATUS_LOW,
			'shortfall' => $missing > 0 ? self::withNumber(trim((string)$targetText), $missing) : null,
		];
	}

	/**
	 * The on-hand amount a count item starts with when added: full, which is
	 * the target, unless an amount was given.
	 */
	public static function startingOnHand(?string $onHand, ?string $target): ?string {
		if (trim($onHand ?? '') !== '' || self::firstNumber($target) === null) {
			return $onHand;
		}
		return trim((string)$target);
	}

	/**
	 * The stock of an item arriving on an inventory from a list that does not
	 * count stock: a checklist (its quantity) or a template (its quantity,
	 * plus the mode, target and reorder point it keeps). It starts full. A
	 * count item's target is the template's target, else the quantity, and it
	 * has exactly that on hand; a level item is full and restocks the quantity,
	 * else the target.
	 *
	 * @return array{stockMode: 'count'|'level', stockLevel: ?string, quantity: ?string, target: ?string, reorderPoint: ?string}
	 */
	public static function startFull(string $mode, ?string $quantity, ?string $target, ?string $reorderPoint): array {
		$quantity = self::nullIfBlank($quantity);
		$target = self::nullIfBlank($target);
		if ($mode === self::MODE_LEVEL) {
			return [
				'stockMode' => self::MODE_LEVEL,
				'stockLevel' => self::LEVEL_FULL,
				'quantity' => $quantity ?? $target,
				'target' => $target,
				'reorderPoint' => self::nullIfBlank($reorderPoint),
			];
		}
		$target ??= $quantity;
		return [
			'stockMode' => self::MODE_COUNT,
			'stockLevel' => null,
			'quantity' => $target,
			'target' => $target,
			'reorderPoint' => self::nullIfBlank($reorderPoint),
		];
	}

	/**
	 * The quantity a checklist row asks for when an inventory item lands on a
	 * checklist: the shortfall when it needs restocking, else the full amount
	 * ({@see fullAmount()}).
	 *
	 * @param array{stockMode: ?string, stockLevel: ?string, quantity: ?string, target: ?string, reorderPoint: ?string} $item
	 */
	public static function checklistQuantity(array $item): ?string {
		$result = self::evaluate($item);
		if (($result['stockStatus'] === self::STATUS_LOW || $result['stockStatus'] === self::STATUS_OUT) && $result['shortfall'] !== null) {
			return $result['shortfall'];
		}
		return self::fullAmount($item);
	}

	/**
	 * What restocks an inventory item from empty: a count item's target, a
	 * level item's restock amount.
	 *
	 * @param array{stockMode: ?string, stockLevel: ?string, quantity: ?string, target: ?string, reorderPoint: ?string} $item
	 */
	public static function fullAmount(array $item): ?string {
		return self::nullIfBlank($item['stockMode'] === self::MODE_LEVEL ? $item['quantity'] : $item['target']);
	}

	/**
	 * The fields a switch of stock mode rewrites. Count to level takes the
	 * level from the status (an uncounted item has "some", so it is full) and
	 * restocks the shortfall when low, else the target. Level to count makes
	 * the restock amount the target and fills on hand to match the level:
	 * full, empty, or the reorder point (blank when there is none, for the
	 * member to fill in). The target and reorder point stay stored in level
	 * mode.
	 *
	 * @param array{stockMode: ?string, stockLevel: ?string, quantity: ?string, target: ?string, reorderPoint: ?string} $item
	 * @return array{stockMode: 'count'|'level', stockLevel: ?string, quantity: ?string, target: ?string}
	 */
	public static function convert(array $item, string $toMode): array {
		$fromMode = $item['stockMode'] === self::MODE_LEVEL ? self::MODE_LEVEL : self::MODE_COUNT;
		if ($toMode === $fromMode) {
			return [
				'stockMode' => $fromMode,
				'stockLevel' => $item['stockLevel'],
				'quantity' => $item['quantity'],
				'target' => $item['target'],
			];
		}
		if ($fromMode === self::MODE_COUNT) {
			$result = self::evaluate($item);
			$level = match ($result['stockStatus']) {
				self::STATUS_LOW => self::LEVEL_LOW,
				self::STATUS_OUT => self::LEVEL_OUT,
				default => self::LEVEL_FULL,
			};
			$restock = $level !== self::LEVEL_FULL && $result['shortfall'] !== null
				? $result['shortfall']
				: self::nullIfBlank($item['target']);
			return [
				'stockMode' => self::MODE_LEVEL,
				'stockLevel' => $level,
				'quantity' => $restock,
				'target' => $item['target'],
			];
		}

		$target = self::nullIfBlank($item['quantity']) ?? self::nullIfBlank($item['target']);
		$targetText = $target ?? '';
		$reorder = self::firstNumber($item['reorderPoint']);
		$onHand = match ($item['stockLevel'] ?? self::LEVEL_FULL) {
			self::LEVEL_OUT => self::firstNumber($targetText) !== null ? self::withNumber($targetText, 0) : '0',
			self::LEVEL_LOW => $reorder === null
				? null
				: (self::firstNumber($targetText) !== null ? self::withNumber($targetText, $reorder) : self::formatNumber($reorder)),
			default => $target,
		};
		return [
			'stockMode' => self::MODE_COUNT,
			'stockLevel' => $item['stockLevel'],
			'quantity' => $onHand,
			'target' => $target,
		];
	}

	private static function nullIfBlank(?string $text): ?string {
		$text = trim($text ?? '');
		return $text !== '' ? $text : null;
	}
}
