<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCP\AppFramework\Db\Entity;

/**
 * A shopper's persisted shopping trip. Lifecycle is timestamp-driven:
 * `closed_at` null = live. At most one live session exists per user, globally.
 *
 * jsonSerialize() emits this row's own columns plus a derived `live` flag; the
 * full session payload (scope listIds, ordered stores) is composed by
 * ShoppingSessionService.
 *
 * @method int getHouseId()
 * @method void setHouseId(int $houseId)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int|null getActiveStoreId()
 * @method void setActiveStoreId(?int $activeStoreId)
 * @method int getLastSeenAt()
 * @method void setLastSeenAt(int $lastSeenAt)
 * @method int|null getClosedAt()
 * @method void setClosedAt(?int $closedAt)
 * @method bool getIncludeUnassigned()
 * @method void setIncludeUnassigned(bool $includeUnassigned)
 * @method bool getIsPrivate()
 * @method void setIsPrivate(bool $isPrivate)
 * @method float|null getBilledTotal()
 * @method void setBilledTotal(?float $billedTotal)
 * @method string|null getBilledCurrency()
 * @method void setBilledCurrency(?string $billedCurrency)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class ShoppingSession extends Entity implements \JsonSerializable {
	protected int $houseId = 0;
	protected string $userId = '';
	protected ?int $activeStoreId = null;
	protected int $lastSeenAt = 0;
	protected ?int $closedAt = null;
	protected bool $includeUnassigned = true;
	protected bool $isPrivate = false;
	protected ?float $billedTotal = null;
	protected ?string $billedCurrency = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('houseId', 'integer');
		$this->addType('activeStoreId', 'integer');
		$this->addType('lastSeenAt', 'integer');
		$this->addType('closedAt', 'integer');
		$this->addType('includeUnassigned', 'boolean');
		$this->addType('isPrivate', 'boolean');
		$this->addType('billedTotal', 'float');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
		// Force the bool columns into INSERTs: their PHP defaults match the
		// initial value, so the magic setter would never mark them dirty and the
		// column would be omitted. fromRow() resets dirty state after hydration,
		// so reads are unaffected. (Same idiom as ChecklistItem.)
		$this->markFieldUpdated('includeUnassigned');
		$this->markFieldUpdated('isPrivate');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'houseId' => $this->houseId,
			'userId' => $this->userId,
			'activeStoreId' => $this->activeStoreId,
			'lastSeenAt' => $this->lastSeenAt,
			'closedAt' => $this->closedAt,
			'includeUnassigned' => $this->includeUnassigned,
			'isPrivate' => $this->isPrivate,
			'billedTotal' => $this->billedTotal,
			'billedCurrency' => $this->billedCurrency,
			'live' => $this->closedAt === null,
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
		];
	}
}
