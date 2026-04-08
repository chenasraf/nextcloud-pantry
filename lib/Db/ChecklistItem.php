<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getListId()
 * @method void setListId(int $listId)
 * @method string getName()
 * @method void setName(string $name)
 * @method string|null getDescription()
 * @method void setDescription(?string $description)
 * @method int|null getCategoryId()
 * @method void setCategoryId(?int $categoryId)
 * @method string|null getQuantity()
 * @method void setQuantity(?string $quantity)
 * @method bool getDone()
 * @method void setDone(bool $done)
 * @method int|null getDoneAt()
 * @method void setDoneAt(?int $doneAt)
 * @method string|null getDoneBy()
 * @method void setDoneBy(?string $doneBy)
 * @method string|null getRrule()
 * @method void setRrule(?string $rrule)
 * @method bool getRepeatFromCompletion()
 * @method void setRepeatFromCompletion(bool $repeatFromCompletion)
 * @method int|null getNextDueAt()
 * @method void setNextDueAt(?int $nextDueAt)
 * @method int|null getImageFileId()
 * @method void setImageFileId(?int $imageFileId)
 * @method string|null getImageUploadedBy()
 * @method void setImageUploadedBy(?string $imageUploadedBy)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class ChecklistItem extends Entity implements \JsonSerializable {
	protected int $listId = 0;
	protected string $name = '';
	protected ?string $description = null;
	protected ?int $categoryId = null;
	protected ?string $quantity = null;
	protected bool $done = false;
	protected ?int $doneAt = null;
	protected ?string $doneBy = null;
	protected ?string $rrule = null;
	protected bool $repeatFromCompletion = false;
	protected ?int $nextDueAt = null;
	protected ?int $imageFileId = null;
	protected ?string $imageUploadedBy = null;
	protected int $sortOrder = 0;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('listId', 'integer');
		$this->addType('categoryId', 'integer');
		$this->addType('done', 'boolean');
		$this->addType('doneAt', 'integer');
		$this->addType('repeatFromCompletion', 'boolean');
		$this->addType('nextDueAt', 'integer');
		$this->addType('imageFileId', 'integer');
		$this->addType('sortOrder', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
		// Force these bool fields to be included in INSERTs. Their PHP defaults
		// match the initial value, so the magic setter would otherwise never
		// mark them dirty and the column would be omitted from the INSERT.
		// fromRow() resets updated fields after hydration, so reads are unaffected.
		$this->markFieldUpdated('done');
		$this->markFieldUpdated('repeatFromCompletion');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'listId' => $this->listId,
			'name' => $this->name,
			'description' => $this->description,
			'categoryId' => $this->categoryId,
			'quantity' => $this->quantity,
			'done' => $this->done,
			'doneAt' => $this->doneAt,
			'doneBy' => $this->doneBy,
			'rrule' => $this->rrule,
			'repeatFromCompletion' => $this->repeatFromCompletion,
			'nextDueAt' => $this->nextDueAt,
			'imageFileId' => $this->imageFileId,
			'imageUploadedBy' => $this->imageUploadedBy,
			'sortOrder' => $this->sortOrder,
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
		];
	}
}
