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
 * @method string|null getDescription()
 * @method void setDescription(?string $description)
 * @method string getIcon()
 * @method void setIcon(string $icon)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 * @method bool getDeleteOnDoneDefault()
 * @method void setDeleteOnDoneDefault(bool $deleteOnDoneDefault)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class Checklist extends Entity implements \JsonSerializable {
	protected int $houseId = 0;
	protected string $name = '';
	protected ?string $description = null;
	protected string $icon = 'clipboard-check';
	protected int $sortOrder = 0;
	protected bool $deleteOnDoneDefault = false;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('houseId', 'integer');
		$this->addType('sortOrder', 'integer');
		$this->addType('deleteOnDoneDefault', 'boolean');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
		// Force the bool field to be included in INSERTs even when its value
		// matches the PHP default — the magic setter wouldn't otherwise mark
		// it dirty. fromRow() resets updated fields after hydration, so reads
		// are unaffected.
		$this->markFieldUpdated('deleteOnDoneDefault');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'houseId' => $this->houseId,
			'name' => $this->name,
			'description' => $this->description,
			'icon' => $this->icon,
			'sortOrder' => $this->sortOrder,
			'deleteOnDoneDefault' => $this->deleteOnDoneDefault,
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
		];
	}
}
