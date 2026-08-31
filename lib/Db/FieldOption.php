<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCP\AppFramework\Db\Entity;

/**
 * One choice of a `select` field definition. Stored values reference an option
 * by id, so an option can be renamed or reordered without rewriting values.
 *
 * @method int getFieldId()
 * @method void setFieldId(int $fieldId)
 * @method string getLabel()
 * @method void setLabel(string $label)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 */
class FieldOption extends Entity implements \JsonSerializable {
	protected int $fieldId = 0;
	protected string $label = '';
	protected int $sortOrder = 0;

	public function __construct() {
		$this->addType('fieldId', 'integer');
		$this->addType('sortOrder', 'integer');
	}

	/**
	 * @return array{id: int, label: string, sortOrder: int}
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'label' => $this->label,
			'sortOrder' => $this->sortOrder,
		];
	}
}
