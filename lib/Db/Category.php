<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getHouseId()
 * @method void setHouseId(int $houseId)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getIcon()
 * @method void setIcon(string $icon)
 * @method string getColor()
 * @method void setColor(string $color)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class Category extends Entity implements \JsonSerializable {
	protected int $houseId = 0;
	protected string $name = '';
	protected string $icon = '';
	protected string $color = '';
	protected int $sortOrder = 0;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('houseId', 'integer');
		$this->addType('sortOrder', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'houseId' => $this->houseId,
			'name' => $this->name,
			'icon' => $this->icon,
			'color' => $this->color,
			'sortOrder' => $this->sortOrder,
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
		];
	}
}
