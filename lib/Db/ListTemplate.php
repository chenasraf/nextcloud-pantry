<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCP\AppFramework\Db\Entity;

/**
 * A template attached to a checklist or inventory.
 *
 * @method int getHouseId()
 * @method void setHouseId(int $houseId)
 * @method int getListId()
 * @method void setListId(int $listId)
 * @method int getTemplateId()
 * @method void setTemplateId(int $templateId)
 */
class ListTemplate extends Entity {
	protected int $houseId = 0;
	protected int $listId = 0;
	protected int $templateId = 0;

	public function __construct() {
		$this->addType('houseId', 'integer');
		$this->addType('listId', 'integer');
		$this->addType('templateId', 'integer');
	}
}
