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
 * The daily stock summary: an inventory's switch and the day it was last
 * summarised, and each item's stock and expiry status as last reported, so
 * the next summary tells only what is new.
 */
class Version51Date20261026010000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$changed = false;

		$lists = Application::tableName('lists');
		if ($schema->hasTable($lists)) {
			$table = $schema->getTable($lists);
			if (!$table->hasColumn('stock_summary')) {
				$table->addColumn('stock_summary', Types::BOOLEAN, [
					'notnull' => false,
					'default' => true,
				]);
				$changed = true;
			}
			if (!$table->hasColumn('summarized_on')) {
				$table->addColumn('summarized_on', Types::BIGINT, ['notnull' => false, 'length' => 20]);
				$changed = true;
			}
		}

		$items = Application::tableName('list_items');
		if ($schema->hasTable($items)) {
			$table = $schema->getTable($items);
			foreach (['summarized_stock_status', 'summarized_expiry_status'] as $column) {
				if (!$table->hasColumn($column)) {
					$table->addColumn($column, Types::STRING, ['notnull' => false, 'length' => 10, 'default' => null]);
					$changed = true;
				}
			}
		}

		return $changed ? $schema : null;
	}
}
