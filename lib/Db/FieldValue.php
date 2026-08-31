<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCP\AppFramework\Db\Entity;

/**
 * A custom-field value for one item. At most one row per (item, field); the
 * typed value lives in the column matching the field's type. The reminder
 * columns carry a date field's per-item override and the idempotency stamp for
 * the reminder scan.
 *
 * @method int getItemId()
 * @method void setItemId(int $itemId)
 * @method int getFieldId()
 * @method void setFieldId(int $fieldId)
 * @method ?string getValueText()
 * @method void setValueText(?string $valueText)
 * @method ?float getValueNumber()
 * @method void setValueNumber(?float $valueNumber)
 * @method bool getValueBool()
 * @method void setValueBool(bool $valueBool)
 * @method ?int getValueDate()
 * @method void setValueDate(?int $valueDate)
 * @method ?int getValueOptionId()
 * @method void setValueOptionId(?int $valueOptionId)
 * @method ?int getOffsetDays()
 * @method void setOffsetDays(?int $offsetDays)
 * @method bool getNotifyOverride()
 * @method void setNotifyOverride(bool $notifyOverride)
 * @method bool getNotifyEnabled()
 * @method void setNotifyEnabled(bool $notifyEnabled)
 * @method ?int getNotifyLeadDays()
 * @method void setNotifyLeadDays(?int $notifyLeadDays)
 * @method ?int getNotifiedForDate()
 * @method void setNotifiedForDate(?int $notifiedForDate)
 */
class FieldValue extends Entity implements \JsonSerializable {
	protected int $itemId = 0;
	protected int $fieldId = 0;
	protected ?string $valueText = null;
	protected ?float $valueNumber = null;
	protected bool $valueBool = false;
	protected ?int $valueDate = null;
	protected ?int $valueOptionId = null;
	protected ?int $offsetDays = null;
	protected bool $notifyOverride = false;
	protected bool $notifyEnabled = false;
	protected ?int $notifyLeadDays = null;
	protected ?int $notifiedForDate = null;

	public function __construct() {
		$this->addType('itemId', 'integer');
		$this->addType('fieldId', 'integer');
		$this->addType('valueNumber', 'float');
		$this->addType('valueBool', 'boolean');
		$this->addType('valueDate', 'integer');
		$this->addType('valueOptionId', 'integer');
		$this->addType('offsetDays', 'integer');
		$this->addType('notifyOverride', 'boolean');
		$this->addType('notifyEnabled', 'boolean');
		$this->addType('notifyLeadDays', 'integer');
		$this->addType('notifiedForDate', 'integer');
	}

	/**
	 * @return array{fieldId: int, valueText: ?string, valueNumber: ?float, valueBool: bool, valueDate: ?int, valueOptionId: ?int, offsetDays: ?int, notifyOverride: bool, notifyEnabled: bool, notifyLeadDays: ?int}
	 */
	public function jsonSerialize(): array {
		return [
			'fieldId' => $this->fieldId,
			'valueText' => $this->valueText,
			'valueNumber' => $this->valueNumber,
			'valueBool' => $this->valueBool,
			'valueDate' => $this->valueDate,
			'valueOptionId' => $this->valueOptionId,
			'offsetDays' => $this->offsetDays,
			'notifyOverride' => $this->notifyOverride,
			'notifyEnabled' => $this->notifyEnabled,
			'notifyLeadDays' => $this->notifyLeadDays,
		];
	}
}
