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
 * @method string|null getDescription()
 * @method void setDescription(?string $description)
 * @method string getIcon()
 * @method void setIcon(string $icon)
 * @method string|null getColor()
 * @method void setColor(?string $color)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 * @method string getDefaultRecurrenceMode()
 * @method void setDefaultRecurrenceMode(string $defaultRecurrenceMode)
 * @method string getDefaultRecurrenceKind()
 * @method void setDefaultRecurrenceKind(string $defaultRecurrenceKind)
 * @method string|null getDefaultRrule()
 * @method void setDefaultRrule(?string $defaultRrule)
 * @method bool getDefaultRepeatFromCompletion()
 * @method void setDefaultRepeatFromCompletion(bool $defaultRepeatFromCompletion)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 * @method int|null getDeletedAt()
 * @method void setDeletedAt(?int $deletedAt)
 * @method int|null getArchivedAt()
 * @method void setArchivedAt(?int $archivedAt)
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
	protected string $defaultRecurrenceMode = self::RECURRENCE_MODE_REMEMBER;
	protected string $defaultRecurrenceKind = self::RECURRENCE_KIND_NONE;
	protected ?string $defaultRrule = null;
	protected bool $defaultRepeatFromCompletion = false;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;
	protected ?int $deletedAt = null;
	protected ?int $archivedAt = null;

	public function __construct() {
		$this->addType('houseId', 'integer');
		$this->addType('sortOrder', 'integer');
		$this->addType('defaultRepeatFromCompletion', 'boolean');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
		$this->addType('deletedAt', 'integer');
		$this->addType('archivedAt', 'integer');
		// Force the defaulted fields to be included in INSERTs even when their
		// value matches the PHP default — the magic setter wouldn't otherwise
		// mark them dirty. fromRow() resets updated fields after hydration, so
		// reads are unaffected.
		$this->markFieldUpdated('defaultRecurrenceMode');
		$this->markFieldUpdated('defaultRecurrenceKind');
		$this->markFieldUpdated('defaultRepeatFromCompletion');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'houseId' => $this->houseId,
			'name' => $this->name,
			'description' => $this->description,
			'icon' => $this->icon,
			'color' => $this->color,
			'sortOrder' => $this->sortOrder,
			'deleteOnDoneDefault' => $this->defaultRecurrenceKind === self::RECURRENCE_KIND_ONCE,
			'defaultRecurrenceMode' => $this->defaultRecurrenceMode,
			'defaultRecurrenceKind' => $this->defaultRecurrenceKind,
			'defaultRrule' => $this->defaultRrule,
			'defaultRepeatFromCompletion' => $this->defaultRepeatFromCompletion,
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
			'deletedAt' => $this->deletedAt,
			'archivedAt' => $this->archivedAt,
		];
	}
}
