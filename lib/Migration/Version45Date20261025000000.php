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
 * Stock on inventory items: the stock mode (count or level), the level of a
 * level item, and the free-text target and reorder point of a count item.
 */
class Version45Date20261025000000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$itemsTable = Application::tableName('list_items');
		if (!$schema->hasTable($itemsTable)) {
			return null;
		}
		$table = $schema->getTable($itemsTable);
		$changed = false;
		foreach ([
			'stock_mode' => 8,
			'stock_level' => 8,
			'target' => 64,
			'reorder_point' => 64,
		] as $column => $length) {
			if ($table->hasColumn($column)) {
				continue;
			}
			$table->addColumn($column, Types::STRING, [
				'notnull' => false,
				'length' => $length,
				'default' => null,
			]);
			$changed = true;
		}
		return $changed ? $schema : null;
	}
}
