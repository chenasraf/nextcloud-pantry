<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getHouseId()
 * @method void setHouseId(int $houseId)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getIcon()
 * @method void setIcon(string $icon)
 * @method string getColor()
 * @method void setColor(string $color)
 * @method string|null getBrand()
 * @method void setBrand(?string $brand)
 * @method string|null getLocation()
 * @method void setLocation(?string $location)
 * @method string|null getOpeningHours()
 * @method void setOpeningHours(?string $openingHours)
 * @method string|null getContact()
 * @method void setContact(?string $contact)
 * @method string|null getResponsible()
 * @method void setResponsible(?string $responsible)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class Store extends Entity implements \JsonSerializable {
	protected int $houseId = 0;
	protected string $name = '';
	protected string $icon = '';
	protected string $color = '';
	protected ?string $brand = null;
	protected ?string $location = null;
	protected ?string $openingHours = null;
	protected ?string $contact = null;
	protected ?string $responsible = null;
	protected ?string $notes = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('houseId', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'houseId' => $this->houseId,
			'name' => $this->name,
			'icon' => $this->icon,
			'color' => $this->color,
			'brand' => $this->brand,
			'location' => $this->location,
			'openingHours' => $this->openingHours !== null && $this->openingHours !== ''
				? json_decode($this->openingHours, true)
				: null,
			'contact' => $this->contact,
			'responsible' => $this->responsible,
			'notes' => $this->notes,
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
		];
	}
}
