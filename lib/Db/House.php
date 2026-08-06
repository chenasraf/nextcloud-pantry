<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getName()
 * @method void setName(string $name)
 * @method string|null getDescription()
 * @method void setDescription(?string $description)
 * @method string getOwnerUid()
 * @method void setOwnerUid(string $ownerUid)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 * @method int getTrashRetentionDays()
 * @method void setTrashRetentionDays(int $trashRetentionDays)
 * @method int getRecurrenceTime()
 * @method void setRecurrenceTime(int $recurrenceTime)
 */
class House extends Entity implements \JsonSerializable {
	public const DEFAULT_TRASH_RETENTION_DAYS = 30;
	public const MAX_TRASH_RETENTION_DAYS = 3650;

	/** Minutes since midnight (server timezone) at which recurring items reopen. Default 08:00. */
	public const DEFAULT_RECURRENCE_TIME = 480;
	public const MAX_RECURRENCE_TIME = 1439;

	protected string $name = '';
	protected ?string $description = null;
	protected string $ownerUid = '';
	protected int $createdAt = 0;
	protected int $updatedAt = 0;
	protected int $trashRetentionDays = self::DEFAULT_TRASH_RETENTION_DAYS;
	protected int $recurrenceTime = self::DEFAULT_RECURRENCE_TIME;

	public function __construct() {
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
		$this->addType('trashRetentionDays', 'integer');
		$this->addType('recurrenceTime', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'name' => $this->name,
			'description' => $this->description,
			'ownerUid' => $this->ownerUid,
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
			'trashRetentionDays' => $this->trashRetentionDays,
			'recurrenceTime' => $this->recurrenceTime,
		];
	}
}
