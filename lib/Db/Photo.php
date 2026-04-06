<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getHouseId()
 * @method void setHouseId(int $houseId)
 * @method int|null getFolderId()
 * @method void setFolderId(?int $folderId)
 * @method int getFileId()
 * @method void setFileId(int $fileId)
 * @method string|null getCaption()
 * @method void setCaption(?string $caption)
 * @method string getUploadedBy()
 * @method void setUploadedBy(string $uploadedBy)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class Photo extends Entity implements \JsonSerializable {
	protected int $houseId = 0;
	protected ?int $folderId = null;
	protected int $fileId = 0;
	protected ?string $caption = null;
	protected string $uploadedBy = '';
	protected int $sortOrder = 0;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('houseId', 'integer');
		$this->addType('folderId', 'integer');
		$this->addType('fileId', 'integer');
		$this->addType('sortOrder', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'houseId' => $this->houseId,
			'folderId' => $this->folderId,
			'fileId' => $this->fileId,
			'caption' => $this->caption,
			'uploadedBy' => $this->uploadedBy,
			'sortOrder' => $this->sortOrder,
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
		];
	}
}
