<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCP\AppFramework\Db\Entity;

/**
 * A price for an item, optionally attached to a store. A null store id is the
 * item's store-less (default) price; there is at most one per item, and at most
 * one price per (item, store).
 *
 * @method int getItemId()
 * @method void setItemId(int $itemId)
 * @method int|null getStoreId()
 * @method void setStoreId(?int $storeId)
 * @method string|null getPriceType()
 * @method void setPriceType(?string $priceType)
 * @method float|null getPriceMin()
 * @method void setPriceMin(?float $priceMin)
 * @method float|null getPriceMax()
 * @method void setPriceMax(?float $priceMax)
 * @method string|null getPriceCurrency()
 * @method void setPriceCurrency(?string $priceCurrency)
 */
class ItemPrice extends Entity implements \JsonSerializable {
	protected int $itemId = 0;
	protected ?int $storeId = null;
	protected ?string $priceType = null;
	protected ?float $priceMin = null;
	protected ?float $priceMax = null;
	protected ?string $priceCurrency = null;

	public function __construct() {
		$this->addType('itemId', 'integer');
		$this->addType('storeId', 'integer');
		$this->addType('priceMin', 'float');
		$this->addType('priceMax', 'float');
	}

	public function jsonSerialize(): array {
		return [
			'storeId' => $this->storeId,
			'priceType' => $this->priceType,
			'priceMin' => $this->priceMin,
			'priceMax' => $this->priceMax,
			'priceCurrency' => $this->priceCurrency,
		];
	}
}
