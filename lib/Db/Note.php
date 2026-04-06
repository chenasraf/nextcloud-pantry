<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getHouseId()
 * @method void setHouseId(int $houseId)
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method string|null getContent()
 * @method void setContent(?string $content)
 * @method string|null getColor()
 * @method void setColor(?string $color)
 * @method string getCreatedBy()
 * @method void setCreatedBy(string $createdBy)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class Note extends Entity implements \JsonSerializable {
	protected int $houseId = 0;
	protected string $title = '';
	protected ?string $content = null;
	protected ?string $color = null;
	protected string $createdBy = '';
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
			'title' => $this->title,
			'content' => $this->content,
			'color' => $this->color,
			'createdBy' => $this->createdBy,
			'sortOrder' => $this->sortOrder,
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
		];
	}
}
