<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Migration;

use Closure;
use OCA\Pantry\AppInfo\Application;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Drop the legacy per-item price columns now that prices live in
 * pantry_item_prices (backfilled by {@see Version25Date20260819000000}).
 */
class Version26Date20260819100000 extends SimpleMigrationStep {
	private const COLUMNS = ['price_type', 'price_min', 'price_max', 'price_currency'];

	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$itemsTable = Application::tableName('list_items');
		if (!$schema->hasTable($itemsTable)) {
			return null;
		}
		$table = $schema->getTable($itemsTable);
		$dropped = false;
		foreach (self::COLUMNS as $column) {
			if ($table->hasColumn($column)) {
				$table->dropColumn($column);
				$dropped = true;
			}
		}

		return $dropped ? $schema : null;
	}
}
