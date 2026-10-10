<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCA\Pantry\Service\ItemDefaults;
use OCP\AppFramework\Db\Entity;

/**
 * @method int getHouseId()
 * @method void setHouseId(int $houseId)
 * @method string getName()
 * @method void setName(string $name)
 * @method string|null getDescription()
 * @method void setDescription(?string $description)
 * @method string getIcon()
 * @method void setIcon(string $icon)
 * @method string|null getColor()
 * @method void setColor(?string $color)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 * @method string|null getItemDefaults()
 * @method void setItemDefaults(?string $itemDefaults)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 * @method int|null getDeletedAt()
 * @method void setDeletedAt(?int $deletedAt)
 * @method int|null getArchivedAt()
 * @method void setArchivedAt(?int $archivedAt)
 * @method int|null getLastCompletedAt()
 * @method void setLastCompletedAt(?int $lastCompletedAt)
 */
class Checklist extends Entity implements \JsonSerializable {
	public const RECURRENCE_MODE_REMEMBER = 'remember';
	public const RECURRENCE_KIND_NONE = 'none';
	public const RECURRENCE_KIND_ONCE = 'once';
	public const RECURRENCE_KIND_RECURRING = 'recurring';

	/** Policies an editor can pick for the list's default recurrence. */
	public const RECURRENCE_MODES = [
		self::RECURRENCE_MODE_REMEMBER,
		self::RECURRENCE_KIND_NONE,
		self::RECURRENCE_KIND_ONCE,
		self::RECURRENCE_KIND_RECURRING,
	];

	/** Recurrences a new item can start with. */
	public const RECURRENCE_KINDS = [
		self::RECURRENCE_KIND_NONE,
		self::RECURRENCE_KIND_ONCE,
		self::RECURRENCE_KIND_RECURRING,
	];

	protected int $houseId = 0;
	protected string $name = '';
	protected ?string $description = null;
	protected string $icon = 'clipboard-check';
	protected ?string $color = null;
	protected int $sortOrder = 0;
	/** JSON, see {@see ItemDefaults}. */
	protected ?string $itemDefaults = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;
	protected ?int $deletedAt = null;
	protected ?int $archivedAt = null;
	protected ?int $lastCompletedAt = null;

	public function __construct() {
		$this->addType('houseId', 'integer');
		$this->addType('sortOrder', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
		$this->addType('deletedAt', 'integer');
		$this->addType('archivedAt', 'integer');
		$this->addType('lastCompletedAt', 'integer');
	}

	public function jsonSerialize(): array {
		$defaults = ItemDefaults::decode($this->itemDefaults);
		$recurrence = ItemDefaults::legacyRecurrence($defaults);
		return [
			'id' => $this->id,
			'houseId' => $this->houseId,
			'name' => $this->name,
			'description' => $this->description,
			'icon' => $this->icon,
			'color' => $this->color,
			'sortOrder' => $this->sortOrder,
			'itemDefaults' => ItemDefaults::complete($defaults),
			'deleteOnDoneDefault' => $recurrence['kind'] === self::RECURRENCE_KIND_ONCE,
			'defaultRecurrenceMode' => $recurrence['mode'],
			'defaultRecurrenceKind' => $recurrence['kind'],
			'defaultRrule' => $recurrence['rrule'],
			'defaultRepeatFromCompletion' => $recurrence['repeatFromCompletion'],
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
			'deletedAt' => $this->deletedAt,
			'archivedAt' => $this->archivedAt,
			'lastCompletedAt' => $this->lastCompletedAt,
		];
	}
}
