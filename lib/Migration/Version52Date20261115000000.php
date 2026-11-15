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
 * Who checked each item on a shopping trip, so finishing the trip credits
 * them rather than whoever finishes it.
 */
class Version52Date20261115000000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$table = Application::tableName('shopsess_items');
		if (!$schema->hasTable($table)) {
			return null;
		}
		$items = $schema->getTable($table);
		if ($items->hasColumn('checked_by')) {
			return null;
		}
		$items->addColumn('checked_by', Types::STRING, ['notnull' => false, 'length' => 64, 'default' => null]);
		return $schema;
	}
}
