<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Db\ItemStoreMapper;
use OCA\Pantry\Db\Store;
use OCA\Pantry\Db\StoreMapper;
use OCA\Pantry\Exception\NotFoundException;
use OCP\AppFramework\Db\DoesNotExistException;

class StoreService {
	public function __construct(
		private StoreMapper $mapper,
		private ItemStoreMapper $itemStoreMapper,
	) {
	}

	/**
	 * @return Store[]
	 */
	public function listForHouse(int $houseId, string $sortBy = 'name_asc'): array {
		return $this->mapper->findByHouse($houseId, $sortBy);
	}

	/**
	 * Batch reorder stores in a house.
	 *
	 * @param list<array{id: int, sortOrder: int}> $items
	 */
	public function reorder(int $houseId, array $items): void {
		foreach ($items as $entry) {
			$id = (int)($entry['id'] ?? 0);
			$sortOrder = (int)($entry['sortOrder'] ?? 0);
			if ($id <= 0) {
				continue;
			}
			try {
				$store = $this->mapper->findById($id);
			} catch (DoesNotExistException) {
				continue;
			}
			if ($store->getHouseId() !== $houseId) {
				continue;
			}
			$store->setSortOrder($sortOrder);
			$store->setUpdatedAt(time());
			$this->mapper->update($store);
		}
	}

	public function get(int $storeId): Store {
		try {
			return $this->mapper->findById($storeId);
		} catch (DoesNotExistException) {
			throw new NotFoundException('Store not found');
		}
	}

	/**
	 * @param array<string, mixed> $info Optional info fields: location, openingHours, contact,
	 *                                   responsible, notes.
	 */
	public function create(int $houseId, string $name, string $icon, string $color, array $info = []): Store {
		$name = trim($name);
		if ($name === '') {
			throw new \InvalidArgumentException('Store name cannot be empty');
		}
		$icon = $this->normalizeIcon($icon);
		$color = $this->normalizeColor($color);

		if ($this->mapper->findByHouseAndName($houseId, $name) !== null) {
			throw new \InvalidArgumentException('A store with this name already exists');
		}

		$now = time();
		$store = new Store();
		$store->setHouseId($houseId);
		$store->setName($name);
		$store->setIcon($icon);
		$store->setColor($color);
		$this->applyInfoFields($store, $info);
		// Append at the end of the custom order so a custom-sorted list keeps a
		// stable order instead of tying every new store at 0 (see category #113).
		$store->setSortOrder($this->mapper->findMaxSortOrder($houseId) + 1);
		$store->setCreatedAt($now);
		$store->setUpdatedAt($now);
		/** @var Store $saved */
		$saved = $this->mapper->insert($store);
		return $saved;
	}

	public function update(int $storeId, array $patch): Store {
		$store = $this->get($storeId);
		if (isset($patch['name'])) {
			$name = trim((string)$patch['name']);
			if ($name === '') {
				throw new \InvalidArgumentException('Store name cannot be empty');
			}
			if ($name !== $store->getName()) {
				$existing = $this->mapper->findByHouseAndName($store->getHouseId(), $name);
				if ($existing !== null && (int)$existing->getId() !== $storeId) {
					throw new \InvalidArgumentException('A store with this name already exists');
				}
			}
			$store->setName($name);
		}
		if (isset($patch['icon'])) {
			$store->setIcon($this->normalizeIcon((string)$patch['icon']));
		}
		if (isset($patch['color'])) {
			$store->setColor($this->normalizeColor((string)$patch['color']));
		}
		if (isset($patch['sortOrder'])) {
			$store->setSortOrder((int)$patch['sortOrder']);
		}
		$this->applyInfoFields($store, $patch);
		$store->setUpdatedAt(time());
		$this->mapper->update($store);
		return $store;
	}

	public function delete(int $storeId): void {
		$store = $this->get($storeId);
		// Detach from any items first, then delete the row.
		$this->itemStoreMapper->deleteByStore((int)$store->getId());
		$this->mapper->delete($store);
	}

	/**
	 * Asserts that the given store belongs to the given house. Returns the loaded entity.
	 *
	 * @throws NotFoundException when missing or mismatched.
	 */
	public function assertInHouse(int $storeId, int $houseId): Store {
		$store = $this->get($storeId);
		if ($store->getHouseId() !== $houseId) {
			throw new NotFoundException('Store does not belong to this house');
		}
		return $store;
	}

	/**
	 * Asserts every id in the list is a store belonging to the given house.
	 *
	 * @param int[] $storeIds
	 *
	 * @throws NotFoundException when any id is missing or mismatched.
	 */
	public function assertStoresInHouse(int $houseId, array $storeIds): void {
		if ($storeIds === []) {
			return;
		}
		$valid = [];
		foreach ($this->mapper->findByHouse($houseId) as $store) {
			$valid[(int)$store->getId()] = true;
		}
		foreach ($storeIds as $id) {
			if (!isset($valid[(int)$id])) {
				throw new NotFoundException('Store does not belong to this house: ' . $id);
			}
		}
	}

	/**
	 * Applies the optional info fields present in the given data to the store.
	 * A field is only touched when its key is present, so callers can send partial patches.
	 * An empty string (or empty array for opening hours) clears the field.
	 *
	 * @param array<string, mixed> $data
	 */
	private function applyInfoFields(Store $store, array $data): void {
		if (array_key_exists('brand', $data)) {
			$store->setBrand($this->normalizeText($data['brand']));
		}
		if (array_key_exists('location', $data)) {
			$store->setLocation($this->normalizeText($data['location']));
		}
		if (array_key_exists('openingHours', $data)) {
			$store->setOpeningHours($this->normalizeOpeningHours($data['openingHours']));
		}
		if (array_key_exists('contact', $data)) {
			$store->setContact($this->normalizeText($data['contact']));
		}
		if (array_key_exists('responsible', $data)) {
			$store->setResponsible($this->normalizeText($data['responsible']));
		}
		if (array_key_exists('notes', $data)) {
			$store->setNotes($this->normalizeText($data['notes']));
		}
	}

	private function normalizeText(mixed $value): ?string {
		if ($value === null) {
			return null;
		}
		$value = trim((string)$value);
		return $value === '' ? null : $value;
	}

	/**
	 * Validates a list of opening-hours intervals and encodes them as a JSON string for storage.
	 * Each interval is {day: 1-7 (ISO-8601: 1 = Monday, 7 = Sunday), start: "HH:MM", end: "HH:MM"}.
	 * Returns null when there are no intervals.
	 */
	private function normalizeOpeningHours(mixed $intervals): ?string {
		if ($intervals === null || $intervals === []) {
			return null;
		}
		if (!is_array($intervals)) {
			throw new \InvalidArgumentException('Opening hours must be a list of intervals');
		}
		$clean = [];
		foreach ($intervals as $interval) {
			if (!is_array($interval)) {
				throw new \InvalidArgumentException('Invalid opening hours interval');
			}
			$day = filter_var($interval['day'] ?? null, FILTER_VALIDATE_INT);
			if ($day === false || $day < 1 || $day > 7) {
				throw new \InvalidArgumentException('Opening hours day must be between 1 and 7');
			}
			$start = $this->normalizeTime($interval['start'] ?? null);
			$end = $this->normalizeTime($interval['end'] ?? null);
			if ($start >= $end) {
				throw new \InvalidArgumentException('Opening hours start must be before end');
			}
			$clean[] = ['day' => $day, 'start' => $start, 'end' => $end];
		}
		usort($clean, static fn (array $a, array $b): int => [$a['day'], $a['start']] <=> [$b['day'], $b['start']]);
		return json_encode($clean);
	}

	private function normalizeTime(mixed $value): string {
		if (!is_string($value) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value)) {
			throw new \InvalidArgumentException('Opening hours time must be in HH:MM format');
		}
		return $value;
	}

	private function normalizeIcon(string $icon): string {
		$icon = strtolower(trim($icon));
		if (!in_array($icon, ConstantsService::STORE_ICON_KEYS, true)) {
			throw new \InvalidArgumentException('Unsupported store icon: ' . $icon);
		}
		return $icon;
	}

	private function normalizeColor(string $color): string {
		$color = trim($color);
		if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
			throw new \InvalidArgumentException('Color must be a 6-digit hex string like "#4caf50"');
		}
		return strtolower($color);
	}
}
