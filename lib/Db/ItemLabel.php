<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getItemId()
 * @method void setItemId(int $itemId)
 * @method int getLabelId()
 * @method void setLabelId(int $labelId)
 */
class ItemLabel extends Entity {
	protected int $itemId = 0;
	protected int $labelId = 0;

	public function __construct() {
		$this->addType('itemId', 'integer');
		$this->addType('labelId', 'integer');
	}
}
