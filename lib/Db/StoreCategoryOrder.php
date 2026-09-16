<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCP\AppFramework\Db\Entity;

/**
 * One category's position within a single store's order.
 *
 * @method int getStoreId()
 * @method void setStoreId(int $storeId)
 * @method int getCategoryId()
 * @method void setCategoryId(int $categoryId)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 */
class StoreCategoryOrder extends Entity {
	protected int $storeId = 0;
	protected int $categoryId = 0;
	protected int $sortOrder = 0;

	public function __construct() {
		$this->addType('storeId', 'integer');
		$this->addType('categoryId', 'integer');
		$this->addType('sortOrder', 'integer');
	}
}
