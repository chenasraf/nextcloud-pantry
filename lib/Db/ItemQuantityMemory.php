<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCP\AppFramework\Db\Entity;

/**
 * The amount last chosen for an item name on a list. A null quantity is a
 * forgotten one, kept so clients that synced it learn to drop it.
 *
 * @method int getHouseId()
 * @method void setHouseId(int $houseId)
 * @method int getListId()
 * @method void setListId(int $listId)
 * @method string getNameKey()
 * @method void setNameKey(string $nameKey)
 * @method string|null getQuantity()
 * @method void setQuantity(?string $quantity)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class ItemQuantityMemory extends Entity implements \JsonSerializable {
	protected int $houseId = 0;
	protected int $listId = 0;
	protected string $nameKey = '';
	protected ?string $quantity = null;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('houseId', 'integer');
		$this->addType('listId', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	/**
	 * @return array{listId: int, nameKey: string, quantity: string|null, updatedAt: int}
	 */
	public function jsonSerialize(): array {
		return [
			'listId' => $this->listId,
			'nameKey' => $this->nameKey,
			'quantity' => $this->quantity,
			'updatedAt' => $this->updatedAt,
		];
	}
}
