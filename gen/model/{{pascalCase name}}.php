<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Your Name <your@email.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\NextcloudAppTemplate\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getFieldName()
 * @method void setFieldName($value)
 */
class {{pascalCase name}} extends Entity implements JsonSerializable {
	// protected $fieldName;

	public function __construct() {
		// $this->addType('fieldName', 'type');
	}

	public function jsonSerialize(): array {
		return [
			// 'field_name' => $this->getFieldName(),
		];
	}
}
