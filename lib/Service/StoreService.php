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
	public function listForHouse(int $houseId): array {
		return $this->mapper->findByHouse($houseId);
	}

	public function get(int $storeId): Store {
		try {
			return $this->mapper->findById($storeId);
		} catch (DoesNotExistException) {
			throw new NotFoundException('Store not found');
		}
	}

	public function create(int $houseId, string $name, string $icon, string $color): Store {
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
