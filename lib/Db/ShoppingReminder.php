<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCP\AppFramework\Db\Entity;

/**
 * A house-scoped, user-defined shopping reminder (ADR 0002). Shown to the
 * shopper at a single moment (`show_on`), ordered by `position`, when `enabled`.
 * Modeled like {@see Store} — one entity + mapper per table.
 *
 * @method int getHouseId()
 * @method void setHouseId(int $houseId)
 * @method string getText()
 * @method void setText(string $text)
 * @method string getShowOn()
 * @method void setShowOn(string $showOn)
 * @method int getPosition()
 * @method void setPosition(int $position)
 * @method bool getEnabled()
 * @method void setEnabled(bool $enabled)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class ShoppingReminder extends Entity implements \JsonSerializable {
	protected int $houseId = 0;
	protected string $text = '';
	protected string $showOn = '';
	protected int $position = 0;
	protected bool $enabled = true;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('houseId', 'integer');
		$this->addType('position', 'integer');
		$this->addType('enabled', 'boolean');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
		// Force the bool column into INSERTs: its PHP default matches the initial
		// value, so the magic setter would never mark it dirty and the column would
		// be omitted. fromRow() resets dirty state after hydration, so reads are
		// unaffected. (Same idiom as ShoppingSession.)
		$this->markFieldUpdated('enabled');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'houseId' => $this->houseId,
			'text' => $this->text,
			'showOn' => $this->showOn,
			'position' => $this->position,
			'enabled' => $this->enabled,
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
		];
	}
}
