<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCP\AppFramework\Db\Entity;

/**
 * The per-trip check log: one row per item checked off during a session,
 * recording the store that was active at the time (null if none). The log
 * mirrors the session's current checked set — unchecking removes the row.
 *
 * On close, an item snapshot (name/quantity/price) is frozen onto the row so a
 * finished trip renders from the log alone — independent of the live checklist
 * item, which may later be edited, un-done, or hard-deleted (trash purge).
 * These columns are null on a live trip's rows until it closes.
 *
 * @method int getSessionId()
 * @method void setSessionId(int $sessionId)
 * @method int getItemId()
 * @method void setItemId(int $itemId)
 * @method int|null getStoreId()
 * @method void setStoreId(?int $storeId)
 * @method int getCheckedAt()
 * @method void setCheckedAt(int $checkedAt)
 * @method string|null getItemName()
 * @method void setItemName(?string $itemName)
 * @method string|null getQuantity()
 * @method void setQuantity(?string $quantity)
 * @method string|null getPriceType()
 * @method void setPriceType(?string $priceType)
 * @method float|null getPriceMin()
 * @method void setPriceMin(?float $priceMin)
 * @method float|null getPriceMax()
 * @method void setPriceMax(?float $priceMax)
 * @method string|null getPriceCurrency()
 * @method void setPriceCurrency(?string $priceCurrency)
 */
class ShoppingSessionItem extends Entity {
	protected int $sessionId = 0;
	protected int $itemId = 0;
	protected ?int $storeId = null;
	protected int $checkedAt = 0;
	protected ?string $itemName = null;
	protected ?string $quantity = null;
	protected ?string $priceType = null;
	protected ?float $priceMin = null;
	protected ?float $priceMax = null;
	protected ?string $priceCurrency = null;

	public function __construct() {
		$this->addType('sessionId', 'integer');
		$this->addType('itemId', 'integer');
		$this->addType('storeId', 'integer');
		$this->addType('checkedAt', 'integer');
		$this->addType('priceMin', 'float');
		$this->addType('priceMax', 'float');
	}
}
