<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Db\ShoppingReminder;
use OCA\Pantry\Db\ShoppingReminderMapper;
use OCA\Pantry\Exception\NotFoundException;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * House-scoped, user-defined shopping reminders (ADR 0002). A single moment per
 * row (`show_on`); to show the same text at two moments, create two rows. New
 * houses start empty — nothing is seeded (seeding would reintroduce the rejected
 * hardcoded prompts as data).
 */
class ShoppingReminderService {
	/** Surfacing moments — the `show_on` enum. */
	public const SHOW_ON_START = 'on_start';
	public const SHOW_ON_STORE_ADVANCE = 'on_store_advance';
	public const SHOW_ON_CLOSE = 'on_close';

	public const SHOW_ON_VALUES = [
		self::SHOW_ON_START,
		self::SHOW_ON_STORE_ADVANCE,
		self::SHOW_ON_CLOSE,
	];

	public function __construct(
		private ShoppingReminderMapper $mapper,
	) {
	}

	/**
	 * All of a house's reminders (manager order: position, then id).
	 *
	 * @return ShoppingReminder[]
	 */
	public function listForHouse(int $houseId): array {
		return $this->mapper->findByHouse($houseId);
	}

	public function get(int $reminderId): ShoppingReminder {
		try {
			return $this->mapper->findById($reminderId);
		} catch (DoesNotExistException) {
			throw new NotFoundException('Reminder not found');
		}
	}

	/**
	 * Create a reminder, appended to the end of the house's order.
	 */
	public function create(int $houseId, string $text, string $showOn, bool $enabled = true): ShoppingReminder {
		$text = $this->normalizeText($text);
		$showOn = $this->normalizeShowOn($showOn);

		$now = time();
		$reminder = new ShoppingReminder();
		$reminder->setHouseId($houseId);
		$reminder->setText($text);
		$reminder->setShowOn($showOn);
		$reminder->setPosition($this->mapper->maxPositionForHouse($houseId) + 1);
		$reminder->setEnabled($enabled);
		$reminder->setCreatedAt($now);
		$reminder->setUpdatedAt($now);
		/** @var ShoppingReminder $saved */
		$saved = $this->mapper->insert($reminder);
		return $saved;
	}

	/**
	 * Partial update: only the keys present in $patch are touched (`text`,
	 * `showOn`, `enabled`, `position`).
	 *
	 * @param array<string, mixed> $patch
	 */
	public function update(int $reminderId, array $patch): ShoppingReminder {
		$reminder = $this->get($reminderId);
		if (array_key_exists('text', $patch)) {
			$reminder->setText($this->normalizeText((string)$patch['text']));
		}
		if (array_key_exists('showOn', $patch)) {
			$reminder->setShowOn($this->normalizeShowOn((string)$patch['showOn']));
		}
		if (array_key_exists('enabled', $patch)) {
			$reminder->setEnabled((bool)$patch['enabled']);
		}
		if (array_key_exists('position', $patch)) {
			$reminder->setPosition(max(0, (int)$patch['position']));
		}
		$reminder->setUpdatedAt(time());
		$this->mapper->update($reminder);
		return $reminder;
	}

	public function delete(int $reminderId): void {
		$reminder = $this->get($reminderId);
		$this->mapper->delete($reminder);
	}

	/**
	 * Batch reorder: assign positions 0..n-1 from the given ordered id list. Only
	 * ids that belong to the house are touched; unknown ids are ignored and any
	 * house reminder missing from the list keeps its position (the client sends
	 * the full set, but a concurrent add shouldn't be clobbered to 0).
	 *
	 * @param int[] $orderedIds
	 * @return ShoppingReminder[] The house's reminders in the new order.
	 */
	public function reorder(int $houseId, array $orderedIds): array {
		$byId = [];
		foreach ($this->mapper->findByHouse($houseId) as $reminder) {
			$byId[(int)$reminder->getId()] = $reminder;
		}
		$now = time();
		$position = 0;
		foreach ($orderedIds as $id) {
			$reminder = $byId[(int)$id] ?? null;
			if ($reminder === null) {
				continue;
			}
			if ((int)$reminder->getPosition() !== $position) {
				$reminder->setPosition($position);
				$reminder->setUpdatedAt($now);
				$this->mapper->update($reminder);
			}
			$position++;
		}
		return $this->mapper->findByHouse($houseId);
	}

	/**
	 * Assert the reminder belongs to the house; returns the loaded entity.
	 *
	 * @throws NotFoundException when missing or mismatched.
	 */
	public function assertInHouse(int $reminderId, int $houseId): ShoppingReminder {
		$reminder = $this->get($reminderId);
		if ($reminder->getHouseId() !== $houseId) {
			throw new NotFoundException('Reminder does not belong to this house');
		}
		return $reminder;
	}

	private function normalizeText(string $text): string {
		$text = trim($text);
		if ($text === '') {
			throw new \InvalidArgumentException('Reminder text cannot be empty');
		}
		return $text;
	}

	private function normalizeShowOn(string $showOn): string {
		$showOn = trim($showOn);
		if (!in_array($showOn, self::SHOW_ON_VALUES, true)) {
			throw new \InvalidArgumentException('Unsupported reminder moment: ' . $showOn);
		}
		return $showOn;
	}
}
