<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Db\Checklist;

/**
 * Shape of a list's item defaults and the pure transforms over it.
 *
 * Every key holds `{mode, value}`. `none` leaves the composer's own empty
 * state, `fixed` always starts new items with `value`, and `remember` starts
 * them with whatever the last item added used, written back into `value`.
 * Custom fields are a list of `{fieldId, mode, value}`; a field without an
 * entry inherits the default from its own definition.
 *
 * @psalm-type ItemDefaultsEntry = array{mode: string, value?: mixed}
 * @psalm-type ItemDefaultsField = array{fieldId: int, mode: string, value?: array<array-key, mixed>|null}
 * @psalm-type ItemDefaultsData = array{recurrence?: ItemDefaultsEntry, stores?: ItemDefaultsEntry, category?: ItemDefaultsEntry, labels?: ItemDefaultsEntry, quantity?: ItemDefaultsEntry, fields?: list<ItemDefaultsField>}
 * @psalm-type ItemDefaultsRecurrence = array{kind: string, rrule: string|null, repeatFromCompletion: bool}
 */
final class ItemDefaults {
	public const MODE_NONE = 'none';
	public const MODE_FIXED = 'fixed';
	public const MODE_REMEMBER = 'remember';

	public const MODES = [self::MODE_NONE, self::MODE_FIXED, self::MODE_REMEMBER];

	public const KEY_RECURRENCE = 'recurrence';
	public const KEY_STORES = 'stores';
	public const KEY_CATEGORY = 'category';
	public const KEY_LABELS = 'labels';
	public const KEY_QUANTITY = 'quantity';
	public const KEY_FIELDS = 'fields';

	/** Keys holding a single `{mode, value}` entry, in the order they serialize. */
	public const ENTRY_KEYS = [
		self::KEY_RECURRENCE,
		self::KEY_STORES,
		self::KEY_CATEGORY,
		self::KEY_LABELS,
		self::KEY_QUANTITY,
	];

	public const DEFAULT_RRULE = 'FREQ=WEEKLY;INTERVAL=1';

	/**
	 * Read the stored JSON, skipping anything that does not have the expected shape.
	 *
	 * @return ItemDefaultsData
	 */
	public static function decode(?string $json): array {
		$decoded = $json === null || $json === '' ? null : json_decode($json, true);
		if (!is_array($decoded)) {
			return [];
		}
		$out = [];
		foreach (self::ENTRY_KEYS as $key) {
			$entry = $decoded[$key] ?? null;
			if (is_array($entry) && is_string($entry['mode'] ?? null)) {
				$out[$key] = array_key_exists('value', $entry)
					? ['mode' => $entry['mode'], 'value' => $entry['value']]
					: ['mode' => $entry['mode']];
			}
		}
		$fields = [];
		foreach (is_array($decoded[self::KEY_FIELDS] ?? null) ? $decoded[self::KEY_FIELDS] : [] as $field) {
			if (!is_array($field) || !is_int($field['fieldId'] ?? null) || !is_string($field['mode'] ?? null)) {
				continue;
			}
			$entry = ['fieldId' => $field['fieldId'], 'mode' => $field['mode']];
			if (array_key_exists('value', $field)) {
				$entry['value'] = is_array($field['value']) ? $field['value'] : null;
			}
			$fields[] = $entry;
		}
		if ($fields !== []) {
			$out[self::KEY_FIELDS] = $fields;
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $defaults
	 */
	public static function encode(array $defaults): ?string {
		return $defaults === [] ? null : json_encode($defaults, JSON_THROW_ON_ERROR);
	}

	/**
	 * What a list starts with when nobody configured it: new items follow the
	 * recurrence of the last item added, and everything else starts empty.
	 *
	 * @return ItemDefaultsData
	 */
	public static function initial(): array {
		return [
			self::KEY_RECURRENCE => [
				'mode' => self::MODE_REMEMBER,
				'value' => ['kind' => Checklist::RECURRENCE_KIND_NONE, 'rrule' => null, 'repeatFromCompletion' => false],
			],
		];
	}

	/**
	 * Fill in every key so clients always receive the same shape.
	 *
	 * @param ItemDefaultsData $defaults
	 * @return array{recurrence: ItemDefaultsEntry, stores: ItemDefaultsEntry, category: ItemDefaultsEntry, labels: ItemDefaultsEntry, quantity: ItemDefaultsEntry, fields: list<ItemDefaultsField>}
	 */
	public static function complete(array $defaults): array {
		$none = ['mode' => self::MODE_NONE];
		return [
			self::KEY_RECURRENCE => $defaults[self::KEY_RECURRENCE] ?? $none,
			self::KEY_STORES => $defaults[self::KEY_STORES] ?? $none,
			self::KEY_CATEGORY => $defaults[self::KEY_CATEGORY] ?? $none,
			self::KEY_LABELS => $defaults[self::KEY_LABELS] ?? $none,
			self::KEY_QUANTITY => $defaults[self::KEY_QUANTITY] ?? $none,
			self::KEY_FIELDS => $defaults[self::KEY_FIELDS] ?? [],
		];
	}

	/**
	 * The recurrence default as the per-column fields older clients read.
	 *
	 * @param ItemDefaultsData $defaults
	 * @return array{mode: string, kind: string, rrule: string|null, repeatFromCompletion: bool}
	 */
	public static function legacyRecurrence(array $defaults): array {
		$entry = $defaults[self::KEY_RECURRENCE] ?? ['mode' => self::MODE_NONE];
		$raw = array_key_exists('value', $entry) ? $entry['value'] : null;
		$value = is_array($raw) ? $raw : [];
		$kind = $value['kind'] ?? null;
		if (!is_string($kind) || !in_array($kind, Checklist::RECURRENCE_KINDS, true)) {
			$kind = Checklist::RECURRENCE_KIND_NONE;
		}
		$recurring = $kind === Checklist::RECURRENCE_KIND_RECURRING;
		$rrule = $value['rrule'] ?? null;
		$rrule = $recurring && is_string($rrule) ? $rrule : null;
		$fromCompletion = $recurring && (bool)($value['repeatFromCompletion'] ?? false);

		return match ($entry['mode']) {
			self::MODE_REMEMBER => ['mode' => Checklist::RECURRENCE_MODE_REMEMBER, 'kind' => $kind, 'rrule' => $rrule, 'repeatFromCompletion' => $fromCompletion],
			self::MODE_FIXED => ['mode' => $kind, 'kind' => $kind, 'rrule' => $rrule, 'repeatFromCompletion' => $fromCompletion],
			default => ['mode' => Checklist::RECURRENCE_KIND_NONE, 'kind' => Checklist::RECURRENCE_KIND_NONE, 'rrule' => null, 'repeatFromCompletion' => false],
		};
	}

	/**
	 * Build the recurrence entry from the per-column policy older clients send.
	 * A pinned policy is the kind itself, so it overrides whatever kind was given.
	 *
	 * @param ItemDefaultsRecurrence $value
	 * @return array{mode: string, value?: ItemDefaultsRecurrence}
	 */
	public static function recurrenceFromLegacy(string $legacyMode, array $value, RecurrenceService $recurrence): array {
		if ($legacyMode === Checklist::RECURRENCE_MODE_REMEMBER) {
			return ['mode' => self::MODE_REMEMBER, 'value' => self::normalizeRecurrence($value, $recurrence)];
		}
		if ($legacyMode === Checklist::RECURRENCE_KIND_NONE) {
			return ['mode' => self::MODE_NONE];
		}
		$value['kind'] = $legacyMode;
		return ['mode' => self::MODE_FIXED, 'value' => self::normalizeRecurrence($value, $recurrence)];
	}

	/**
	 * Only a recurring default carries a rule; one without a rule repeats weekly.
	 *
	 * @param array<array-key, mixed> $value
	 * @return ItemDefaultsRecurrence
	 * @throws \InvalidArgumentException
	 */
	public static function normalizeRecurrence(array $value, RecurrenceService $recurrence): array {
		$kind = $value['kind'] ?? Checklist::RECURRENCE_KIND_NONE;
		if (!is_string($kind) || !in_array($kind, Checklist::RECURRENCE_KINDS, true)) {
			$kind = is_scalar($kind) ? (string)$kind : gettype($kind);
			throw new \InvalidArgumentException('Unknown recurrence: ' . $kind);
		}
		if ($kind !== Checklist::RECURRENCE_KIND_RECURRING) {
			return ['kind' => $kind, 'rrule' => null, 'repeatFromCompletion' => false];
		}
		$rrule = $value['rrule'] ?? null;
		$rrule = is_string($rrule) ? trim($rrule) : '';
		if ($rrule === '') {
			$rrule = self::DEFAULT_RRULE;
		} else {
			$recurrence->validate($rrule);
		}
		return ['kind' => $kind, 'rrule' => $rrule, 'repeatFromCompletion' => (bool)($value['repeatFromCompletion'] ?? false)];
	}

	/**
	 * Point a duplicated list's defaults at the copies of its list-scoped
	 * categories, labels, fields and options. House-wide ids stay as they are.
	 *
	 * @param ItemDefaultsData $defaults
	 * @param array{categories: array<int, int>, labels: array<int, int>, fields: array<int, int>, options: array<int, int>} $maps
	 * @return ItemDefaultsData
	 */
	public static function remap(array $defaults, array $maps): array {
		if (isset($defaults[self::KEY_CATEGORY])) {
			$category = $defaults[self::KEY_CATEGORY]['value'] ?? null;
			if (is_int($category) && isset($maps['categories'][$category])) {
				$defaults[self::KEY_CATEGORY]['value'] = $maps['categories'][$category];
			}
		}
		if (isset($defaults[self::KEY_LABELS])) {
			$labels = $defaults[self::KEY_LABELS]['value'] ?? null;
			if (is_array($labels)) {
				$defaults[self::KEY_LABELS]['value'] = array_map(
					static fn (mixed $id): int => $maps['labels'][(int)$id] ?? (int)$id,
					$labels,
				);
			}
		}
		if (isset($defaults[self::KEY_FIELDS])) {
			$fields = [];
			foreach ($defaults[self::KEY_FIELDS] as $field) {
				$field['fieldId'] = $maps['fields'][$field['fieldId']] ?? $field['fieldId'];
				$value = $field['value'] ?? null;
				$optionId = is_array($value) ? ($value['valueOptionId'] ?? null) : null;
				if (is_array($value) && is_int($optionId) && isset($maps['options'][$optionId])) {
					$value['valueOptionId'] = $maps['options'][$optionId];
					$field['value'] = $value;
				}
				$fields[] = $field;
			}
			$defaults[self::KEY_FIELDS] = $fields;
		}
		return $defaults;
	}
}
