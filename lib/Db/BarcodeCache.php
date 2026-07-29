<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getEan()
 * @method void setEan(string $ean)
 * @method string getName()
 * @method void setName(string $name)
 * @method string|null getBrand()
 * @method void setBrand(?string $brand)
 * @method string|null getCategory()
 * @method void setCategory(?string $category)
 * @method string|null getImageUrl()
 * @method void setImageUrl(?string $imageUrl)
 * @method string getProvider()
 * @method void setProvider(string $provider)
 * @method string|null getRaw()
 * @method void setRaw(?string $raw)
 * @method int getResolvedAt()
 * @method void setResolvedAt(int $resolvedAt)
 */
class BarcodeCache extends Entity implements \JsonSerializable {
	protected string $ean = '';
	protected string $name = '';
	protected ?string $brand = null;
	protected ?string $category = null;
	protected ?string $imageUrl = null;
	protected string $provider = '';
	protected ?string $raw = null;
	protected int $resolvedAt = 0;

	public function __construct() {
		$this->addType('resolvedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'ean' => $this->ean,
			'name' => $this->name,
			'brand' => $this->brand,
			'category' => $this->category,
			'imageUrl' => $this->imageUrl,
			'provider' => $this->provider,
			'resolvedAt' => $this->resolvedAt,
		];
	}
}
