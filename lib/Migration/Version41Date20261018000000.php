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
 * Add `all_stores` to shopping sessions: a trip that shops every chosen store
 * from one combined list instead of one store at a time.
 */
class Version41Date20261018000000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		$sessions = Application::tableName('shopping_sessions');
		if (!$schema->hasTable($sessions)) {
			return null;
		}
		$table = $schema->getTable($sessions);
		if ($table->hasColumn('all_stores')) {
			return null;
		}
		$table->addColumn('all_stores', Types::BOOLEAN, ['notnull' => false, 'default' => false]);

		return $schema;
	}
}
