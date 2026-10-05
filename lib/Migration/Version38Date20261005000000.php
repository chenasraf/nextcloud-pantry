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
 * Add last_completed_at to checklists: when the list last had no open items
 * left, stamped by the check that closed the final one.
 */
class Version38Date20261005000000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		$tableName = Application::tableName('lists');
		if (!$schema->hasTable($tableName)) {
			return null;
		}
		$table = $schema->getTable($tableName);
		if (!$table->hasColumn('last_completed_at')) {
			$table->addColumn('last_completed_at', Types::BIGINT, [
				'notnull' => false,
				'length' => 20,
			]);
		}

		return $schema;
	}
}
