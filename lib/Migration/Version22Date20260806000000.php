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
 * Add a custom sort order to stores, so they can be manually reordered and used
 * to group checklist items the same way categories are.
 */
class Version22Date20260806000000 extends SimpleMigrationStep {
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

		if (!$table->hasColumn('sort_order')) {
			$table->addColumn('sort_order', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);
		}

		return $schema;
	}
}
