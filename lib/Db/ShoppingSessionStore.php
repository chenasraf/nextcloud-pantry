<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCP\AppFramework\Db\Entity;

/**
 * One store in a shopping session's ordered store sequence. `billed_total` /
 * `billed_currency` hold the per-store amount actually paid (not read yet).
 *
 * @method int getSessionId()
 * @method void setSessionId(int $sessionId)
 * @method int getStoreId()
 * @method void setStoreId(int $storeId)
 * @method int getPosition()
 * @method void setPosition(int $position)
 * @method float|null getBilledTotal()
 * @method void setBilledTotal(?float $billedTotal)
 * @method string|null getBilledCurrency()
 * @method void setBilledCurrency(?string $billedCurrency)
 */
class ShoppingSessionStore extends Entity implements \JsonSerializable {
	protected int $sessionId = 0;
	protected int $storeId = 0;
	protected int $position = 0;
	protected ?float $billedTotal = null;
	protected ?string $billedCurrency = null;

	public function __construct() {
		$this->addType('sessionId', 'integer');
		$this->addType('storeId', 'integer');
		$this->addType('position', 'integer');
		$this->addType('billedTotal', 'float');
	}

	public function jsonSerialize(): array {
		return [
			'storeId' => $this->storeId,
			'position' => $this->position,
			'billedTotal' => $this->billedTotal,
			'billedCurrency' => $this->billedCurrency,
		];
	}
}
