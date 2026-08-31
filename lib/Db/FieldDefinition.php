<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCP\AppFramework\Db\Entity;

/**
 * A custom-field definition, scoped to a house and optionally to a single list
 * (null list_id = house-wide). The five types carry type-specific config in the
 * `default_*`, `multiline`, `hint`, `date_mode` and reminder columns; a `select`
 * field's choices live in {@see FieldOption} rows.
 *
 * @method int getHouseId()
 * @method void setHouseId(int $houseId)
 * @method ?int getListId()
 * @method void setListId(?int $listId)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getType()
 * @method void setType(string $type)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 * @method ?string getHint()
 * @method void setHint(?string $hint)
 * @method bool getMultiline()
 * @method void setMultiline(bool $multiline)
 * @method ?string getDefaultText()
 * @method void setDefaultText(?string $defaultText)
 * @method ?float getDefaultNumber()
 * @method void setDefaultNumber(?float $defaultNumber)
 * @method bool getDefaultBool()
 * @method void setDefaultBool(bool $defaultBool)
 * @method ?int getDefaultOptionId()
 * @method void setDefaultOptionId(?int $defaultOptionId)
 * @method ?string getDateMode()
 * @method void setDateMode(?string $dateMode)
 * @method ?int getDefaultOffsetDays()
 * @method void setDefaultOffsetDays(?int $defaultOffsetDays)
 * @method bool getNotifyDefault()
 * @method void setNotifyDefault(bool $notifyDefault)
 * @method int getLeadDays()
 * @method void setLeadDays(int $leadDays)
 * @method ?string getOverridePolicy()
 * @method void setOverridePolicy(?string $overridePolicy)
 * @method bool getStopWhenDone()
 * @method void setStopWhenDone(bool $stopWhenDone)
 * @method ?int getDeletedAt()
 * @method void setDeletedAt(?int $deletedAt)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class FieldDefinition extends Entity implements \JsonSerializable {
	public const TYPE_TEXT = 'text';
	public const TYPE_NUMBER = 'number';
	public const TYPE_CHECKBOX = 'checkbox';
	public const TYPE_DATE = 'date';
	public const TYPE_SELECT = 'select';

	public const TYPES = [
		self::TYPE_TEXT,
		self::TYPE_NUMBER,
		self::TYPE_CHECKBOX,
		self::TYPE_DATE,
		self::TYPE_SELECT,
	];

	public const DATE_ABSOLUTE = 'absolute';
	public const DATE_RELATIVE = 'relative';
	public const DATE_MODES = [self::DATE_ABSOLUTE, self::DATE_RELATIVE];

	public const OVERRIDE_FIELD_ONLY = 'field-only';
	public const OVERRIDE_ITEM = 'item-override';
	public const OVERRIDE_POLICIES = [self::OVERRIDE_FIELD_ONLY, self::OVERRIDE_ITEM];

	protected int $houseId = 0;
	protected ?int $listId = null;
	protected string $name = '';
	protected string $type = self::TYPE_TEXT;
	protected int $sortOrder = 0;
	protected ?string $hint = null;
	protected bool $multiline = false;
	protected ?string $defaultText = null;
	protected ?float $defaultNumber = null;
	protected bool $defaultBool = false;
	protected ?int $defaultOptionId = null;
	protected ?string $dateMode = null;
	protected ?int $defaultOffsetDays = null;
	protected bool $notifyDefault = false;
	protected int $leadDays = 0;
	protected ?string $overridePolicy = null;
	protected bool $stopWhenDone = false;
	protected ?int $deletedAt = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('houseId', 'integer');
		$this->addType('listId', 'integer');
		$this->addType('sortOrder', 'integer');
		$this->addType('multiline', 'boolean');
		$this->addType('defaultNumber', 'float');
		$this->addType('defaultBool', 'boolean');
		$this->addType('defaultOptionId', 'integer');
		$this->addType('defaultOffsetDays', 'integer');
		$this->addType('notifyDefault', 'boolean');
		$this->addType('leadDays', 'integer');
		$this->addType('stopWhenDone', 'boolean');
		$this->addType('deletedAt', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	/**
	 * The field's `select` options are attached by the service (empty for
	 * non-select fields) rather than loaded by the entity.
	 *
	 * @param list<array{id: int, label: string, sortOrder: int}> $options
	 *
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(array $options = []): array {
		return [
			'id' => $this->id,
			'houseId' => $this->houseId,
			'listId' => $this->listId,
			'name' => $this->name,
			'type' => $this->type,
			'sortOrder' => $this->sortOrder,
			'hint' => $this->hint,
			'multiline' => $this->multiline,
			'defaultText' => $this->defaultText,
			'defaultNumber' => $this->defaultNumber,
			'defaultBool' => $this->defaultBool,
			'defaultOptionId' => $this->defaultOptionId,
			'dateMode' => $this->dateMode,
			'defaultOffsetDays' => $this->defaultOffsetDays,
			'notifyDefault' => $this->notifyDefault,
			'leadDays' => $this->leadDays,
			'overridePolicy' => $this->overridePolicy,
			'stopWhenDone' => $this->stopWhenDone,
			'options' => $options,
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
		];
	}
}
