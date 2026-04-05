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
		try {
			new RRuleIterator($this->normalize($rrule), new \DateTimeImmutable('2000-01-01T00:00:00Z'));
		} catch (InvalidDataException $e) {
			throw new \InvalidArgumentException('Invalid RRULE: ' . $e->getMessage(), 0, $e);
		}
	}

	/**
	 * Compute the next occurrence strictly after $from.
	 *
	 * The iterator's semantics are: the first item equals DTSTART; subsequent items are
	 * successive occurrences per the rule. We seed with DTSTART = $from and advance once.
	 */
	public function computeNextOccurrence(string $rrule, \DateTimeImmutable $from): ?\DateTimeImmutable {
		try {
			$iter = new RRuleIterator($this->normalize($rrule), $from);
		} catch (InvalidDataException $e) {
			throw new \InvalidArgumentException('Invalid RRULE: ' . $e->getMessage(), 0, $e);
		}

		// First call yields DTSTART itself. Advance to the next one.
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

	private function normalize(string $rrule): string {
		$trim = trim($rrule);
		if (stripos($trim, 'RRULE:') === 0) {
			return substr($trim, 6);
		}
		return $trim;
	}
}
