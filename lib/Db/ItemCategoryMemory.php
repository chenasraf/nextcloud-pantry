<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCP\AppFramework\Db\Entity;

/**
 * How often a household chose, and turned down, a category for an item name.
 *
 * @method int getHouseId()
 * @method void setHouseId(int $houseId)
 * @method string getNameKey()
 * @method void setNameKey(string $nameKey)
 * @method int getCategoryId()
 * @method void setCategoryId(int $categoryId)
 * @method int getUses()
 * @method void setUses(int $uses)
 * @method int getRejections()
 * @method void setRejections(int $rejections)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class ItemCategoryMemory extends Entity implements \JsonSerializable {
	protected int $houseId = 0;
	protected string $nameKey = '';
	protected int $categoryId = 0;
	protected int $uses = 0;
	protected int $rejections = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('houseId', 'integer');
		$this->addType('categoryId', 'integer');
		$this->addType('uses', 'integer');
		$this->addType('rejections', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	/**
	 * @return array{nameKey: string, categoryId: int, uses: int, rejections: int, updatedAt: int}
	 */
	public function jsonSerialize(): array {
		return [
			'nameKey' => $this->nameKey,
			'categoryId' => $this->categoryId,
			'uses' => $this->uses,
			'rejections' => $this->rejections,
			'updatedAt' => $this->updatedAt,
		];
	}
}
