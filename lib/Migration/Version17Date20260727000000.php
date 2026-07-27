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
 * Add an optional brand/chain field to stores, so multiple stores of the same
 * brand (e.g. "Walmart") can be grouped.
 */
class Version17Date20260727000000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$storesTable = Application::tableName('stores');
		if (!$schema->hasTable($storesTable)) {
			return null;
		}
		$table = $schema->getTable($storesTable);

		if (!$table->hasColumn('brand')) {
			$table->addColumn('brand', Types::STRING, [
				'notnull' => false,
				'length' => 255,
				'default' => null,
			]);
		}

		return $schema;
	}
}
