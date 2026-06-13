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
 * Add a nullable color column to checklists so each list can carry an accent
 * color used by the UI for its icon, label, and borders. Null means "default".
 */
class Version7Date20260613000000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$listsTable = Application::tableName('lists');
		if ($schema->hasTable($listsTable)) {
			$table = $schema->getTable($listsTable);
			if (!$table->hasColumn('color')) {
				$table->addColumn('color', Types::STRING, [
					'notnull' => false,
					'length' => 16,
				]);
			}
		}

		return $schema;
	}
}
