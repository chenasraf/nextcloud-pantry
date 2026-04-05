<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Db\Category;
use OCA\Pantry\Db\CategoryMapper;
use OCA\Pantry\Exception\NotFoundException;
use OCP\AppFramework\Db\DoesNotExistException;

class CategoryService {
	/** Palette of supported icon keys, mirrored on the frontend. */
	private const ICON_KEYS = [
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

	public function __construct(
		private CategoryMapper $mapper,
	) {
	}

	/**
	 * @return Category[]
	 */
	public function listForHouse(int $houseId): array {
		return $this->mapper->findByHouse($houseId);
	}

	public function get(int $categoryId): Category {
		try {
			return $this->mapper->findById($categoryId);
		} catch (DoesNotExistException) {
			throw new NotFoundException('Category not found');
		}
	}

	public function create(int $houseId, string $name, string $icon, string $color): Category {
		$name = trim($name);
		if ($name === '') {
			throw new \InvalidArgumentException('Category name cannot be empty');
		}
		$icon = $this->normalizeIcon($icon);
		$color = $this->normalizeColor($color);

		if ($this->mapper->findByHouseAndName($houseId, $name) !== null) {
			throw new \InvalidArgumentException('A category with this name already exists');
		}

		$now = time();
		$cat = new Category();
		$cat->setHouseId($houseId);
		$cat->setName($name);
		$cat->setIcon($icon);
		$cat->setColor($color);
		$cat->setSortOrder(0);
		$cat->setCreatedAt($now);
		$cat->setUpdatedAt($now);
		/** @var Category $saved */
		$saved = $this->mapper->insert($cat);
		return $saved;
	}

	public function update(int $categoryId, array $patch): Category {
		$cat = $this->get($categoryId);
		if (isset($patch['name'])) {
			$name = trim((string)$patch['name']);
			if ($name === '') {
				throw new \InvalidArgumentException('Category name cannot be empty');
			}
			if ($name !== $cat->getName()) {
				$existing = $this->mapper->findByHouseAndName($cat->getHouseId(), $name);
				if ($existing !== null && (int)$existing->getId() !== $categoryId) {
					throw new \InvalidArgumentException('A category with this name already exists');
				}
			}
			$cat->setName($name);
		}
		if (isset($patch['icon'])) {
			$cat->setIcon($this->normalizeIcon((string)$patch['icon']));
		}
		if (isset($patch['color'])) {
			$cat->setColor($this->normalizeColor((string)$patch['color']));
		}
		if (isset($patch['sortOrder'])) {
			$cat->setSortOrder((int)$patch['sortOrder']);
		}
		$cat->setUpdatedAt(time());
		$this->mapper->update($cat);
		return $cat;
	}

	public function delete(int $categoryId): void {
		$cat = $this->get($categoryId);
		// Detach from any items first, then delete the row.
		$this->mapper->detachFromItems((int)$cat->getId());
		$this->mapper->delete($cat);
	}

	/**
	 * Asserts that the given category belongs to the given house. Returns the loaded entity.
	 *
	 * @throws NotFoundException when missing or mismatched.
	 */
	public function assertInHouse(int $categoryId, int $houseId): Category {
		$cat = $this->get($categoryId);
		if ($cat->getHouseId() !== $houseId) {
			throw new NotFoundException('Category does not belong to this house');
		}
		return $cat;
	}

	private function normalizeIcon(string $icon): string {
		$icon = strtolower(trim($icon));
		if (!in_array($icon, self::ICON_KEYS, true)) {
			throw new \InvalidArgumentException('Unsupported category icon: ' . $icon);
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
