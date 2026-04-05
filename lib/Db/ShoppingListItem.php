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
 * @method int|null getCategoryId()
 * @method void setCategoryId(?int $categoryId)
 * @method string|null getQuantity()
 * @method void setQuantity(?string $quantity)
 * @method bool getBought()
 * @method void setBought(bool $bought)
 * @method int|null getBoughtAt()
 * @method void setBoughtAt(?int $boughtAt)
 * @method string|null getBoughtBy()
 * @method void setBoughtBy(?string $boughtBy)
 * @method string|null getRrule()
 * @method void setRrule(?string $rrule)
 * @method bool getRepeatFromCompletion()
 * @method void setRepeatFromCompletion(bool $repeatFromCompletion)
 * @method int|null getNextDueAt()
 * @method void setNextDueAt(?int $nextDueAt)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class ShoppingListItem extends Entity implements \JsonSerializable {
	protected int $listId = 0;
	protected string $name = '';
	protected ?int $categoryId = null;
	protected ?string $quantity = null;
	protected bool $bought = false;
	protected ?int $boughtAt = null;
	protected ?string $boughtBy = null;
	protected ?string $rrule = null;
	protected bool $repeatFromCompletion = false;
	protected ?int $nextDueAt = null;
	protected int $sortOrder = 0;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('listId', 'integer');
		$this->addType('categoryId', 'integer');
		$this->addType('bought', 'boolean');
		$this->addType('boughtAt', 'integer');
		$this->addType('repeatFromCompletion', 'boolean');
		$this->addType('nextDueAt', 'integer');
		$this->addType('sortOrder', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'listId' => $this->listId,
			'name' => $this->name,
			'categoryId' => $this->categoryId,
			'quantity' => $this->quantity,
			'bought' => $this->bought,
			'boughtAt' => $this->boughtAt,
			'boughtBy' => $this->boughtBy,
			'rrule' => $this->rrule,
			'repeatFromCompletion' => $this->repeatFromCompletion,
			'nextDueAt' => $this->nextDueAt,
			'sortOrder' => $this->sortOrder,
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
		];
	}
}
