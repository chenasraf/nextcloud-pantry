<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Migration;

use Closure;
use OCA\Pantry\AppInfo\Application;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * The expiry status the restock sync last acted on for an inventory item, so
 * the daily job syncs only items whose status moved since.
 */
class Version50Date20261026000000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$tableName = Application::tableName('list_items');
		if (!$schema->hasTable($tableName)) {
			return null;
		}
		$table = $schema->getTable($tableName);
		if ($table->hasColumn('expiry_synced_status')) {
			return null;
		}
		$table->addColumn('expiry_synced_status', Types::STRING, [
			'notnull' => false,
			'length' => 8,
			'default' => null,
		]);
		return $schema;
	}
}
