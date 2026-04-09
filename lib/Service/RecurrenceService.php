<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use Sabre\VObject\InvalidDataException;
use Sabre\VObject\Recur\RRuleIterator;

/**
 * Thin wrapper around sabre/vobject's RRuleIterator.
 */
class RecurrenceService {
	/**
	 * Validate an RRULE string (RFC 5545). Accepts either a bare rule ("FREQ=WEEKLY;INTERVAL=1")
	 * or a full "RRULE:..." line.
	 *
	 * @throws \InvalidArgumentException if the rule is malformed.
	 */
	public function validate(string $rrule): void {
		$normalized = $this->normalize($rrule);
		$this->preflight($normalized);
		try {
			new RRuleIterator($normalized, new \DateTimeImmutable('2000-01-01T00:00:00Z'));
		} catch (InvalidDataException $e) {
			throw new \InvalidArgumentException('Invalid RRULE: ' . $e->getMessage(), 0, $e);
		}
	}

	/**
	 * Compute the next occurrence strictly after $from.
	 *
	 * Used for "repeat from completion" mode.
	 *
	 * When the rule contains BYDAY/BYMONTHDAY constraints, the simple approach
	 * of seeding DTSTART=$from and calling next() once can skip the very next
	 * matching day (e.g. BYDAY=TH seeded on Wednesday yields next week's
	 * Thursday instead of tomorrow). In that case we anchor the series in the
	 * past and scan forward to find the first occurrence after $from.
	 */
	public function computeNextOccurrence(string $rrule, \DateTimeImmutable $from): ?\DateTimeImmutable {
		$normalized = $this->normalize($rrule);
		$this->preflight($normalized);

		$hasByDay = preg_match('/(?:^|;)(?:BYDAY|BYMONTHDAY)=/i', $normalized);
		if ($hasByDay) {
			// Anchor far enough in the past that the weekly/monthly cycle
			// covers every possible target day. Using $from minus the
			// interval period ensures the first matching day after $from
			// is found without cycle-alignment issues.
			$anchor = $from->modify('-1 year');
			return $this->nextOccurrenceAfter($normalized, $anchor, $from);
		}

		// Simple interval rule (no day constraints): seed at $from, advance once.
		try {
			$iter = new RRuleIterator($normalized, $from);
		} catch (InvalidDataException $e) {
			throw new \InvalidArgumentException('Invalid RRULE: ' . $e->getMessage(), 0, $e);
		}
		$iter->next();
		if (!$iter->valid()) {
			return null;
		}
		$current = $iter->current();
		if (!$current instanceof \DateTimeInterface) {
			return null;
		}
		return \DateTimeImmutable::createFromInterface($current);
	}

	/**
	 * Compute the next occurrence of a rule strictly after $after, using $dtStart as the
	 * schedule anchor.
	 *
	 * This is the "fixed schedule" semantics: the series of occurrences is determined by
	 * $dtStart (e.g. the item creation time), and we skip forward until we find the first
	 * one that is still in the future. Caps iteration to avoid runaway loops on malformed
	 * rules.
	 */
	public function nextOccurrenceAfter(
		string $rrule,
		\DateTimeImmutable $dtStart,
		\DateTimeImmutable $after,
	): ?\DateTimeImmutable {
		$normalized = $this->normalize($rrule);
		$this->preflight($normalized);
		try {
			$iter = new RRuleIterator($normalized, $dtStart);
		} catch (InvalidDataException $e) {
			throw new \InvalidArgumentException('Invalid RRULE: ' . $e->getMessage(), 0, $e);
		}

		$guard = 0;
		while ($iter->valid() && $guard < 10_000) {
			$current = $iter->current();
			if ($current instanceof \DateTimeInterface) {
				if ($current->getTimestamp() > $after->getTimestamp()) {
					return \DateTimeImmutable::createFromInterface($current);
				}
			}
			$iter->next();
			$guard++;
		}
		return null;
	}

	private function normalize(string $rrule): string {
		$trim = trim($rrule);
		if (stripos($trim, 'RRULE:') === 0) {
			return substr($trim, 6);
		}
		return $trim;
	}

	/**
	 * Shallow structural check before the string ever reaches sabre/vobject.
	 *
	 * Why this exists: sabre/vobject 4.5.x on some PHP 8.2 / doctrine combinations raises a
	 * PHP deprecation while parsing malformed input, which bubbles up to PHPUnit as a
	 * "deprecation" and fails CI under `--fail-on-warning`. Rejecting obvious garbage here
	 * keeps the error path within our own code.
	 *
	 * @throws \InvalidArgumentException if the rule is not a well-formed list of KEY=VALUE
	 *                                   parts or lacks a supported FREQ= clause.
	 */
	private function preflight(string $rrule): void {
		if ($rrule === '') {
			throw new \InvalidArgumentException('Invalid RRULE: empty rule');
		}
		// Whole rule must be KEY=VALUE(;KEY=VALUE)*
		if (!preg_match('/^[A-Z][A-Z0-9-]*=[^;]*(?:;[A-Z][A-Z0-9-]*=[^;]*)*$/i', $rrule)) {
			throw new \InvalidArgumentException('Invalid RRULE: expected KEY=VALUE parts separated by ";"');
		}
		// Must contain a supported FREQ clause.
		if (!preg_match('/(?:^|;)FREQ=(SECONDLY|MINUTELY|HOURLY|DAILY|WEEKLY|MONTHLY|YEARLY)(?:;|$)/i', $rrule)) {
			throw new \InvalidArgumentException('Invalid RRULE: missing or unsupported FREQ clause');
		}
	}
}
