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
 * @method bool getIsPinned()
 * @method void setIsPinned(bool $isPinned)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 * @method int|null getDeletedAt()
 * @method void setDeletedAt(?int $deletedAt)
 * @method int|null getSyncFileId()
 * @method void setSyncFileId(?int $syncFileId)
 * @method string|null getSyncOwnerUid()
 * @method void setSyncOwnerUid(?string $syncOwnerUid)
 * @method string|null getSyncHash()
 * @method void setSyncHash(?string $syncHash)
 * @method int|null getSyncAt()
 * @method void setSyncAt(?int $syncAt)
 */
class Note extends Entity implements \JsonSerializable {
	protected int $houseId = 0;
	protected string $title = '';
	protected ?string $content = null;
	protected ?string $color = null;
	protected string $createdBy = '';
	protected int $sortOrder = 0;
	protected bool $isPinned = false;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;
	protected ?int $deletedAt = null;
	protected ?int $syncFileId = null;
	protected ?string $syncOwnerUid = null;
	protected ?string $syncHash = null;
	protected ?int $syncAt = null;

	public function __construct() {
		$this->addType('houseId', 'integer');
		$this->addType('sortOrder', 'integer');
		$this->addType('isPinned', 'boolean');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
		$this->addType('deletedAt', 'integer');
		$this->addType('syncFileId', 'integer');
		$this->addType('syncAt', 'integer');
	}

	/**
	 * Whether this note mirrors a file.
	 */
	public function isSynced(): bool {
		return $this->syncFileId !== null;
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
			'isPinned' => $this->isPinned,
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
			'deletedAt' => $this->deletedAt,
			'syncFileId' => $this->syncFileId,
			'syncOwnerUid' => $this->syncOwnerUid,
			'syncAt' => $this->syncAt,
		];
	}
}
