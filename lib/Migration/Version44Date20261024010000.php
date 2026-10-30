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
 * pantry_lists.fill_from_defaults: whether a template item drawn into the
 * list fills the fields it leaves empty from the list's item defaults.
 */
class Version44Date20261024010000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$listsTable = Application::tableName('lists');
		if (!$schema->hasTable($listsTable)) {
			return null;
		}
		$table = $schema->getTable($listsTable);
		if ($table->hasColumn('fill_from_defaults')) {
			return null;
		}
		$table->addColumn('fill_from_defaults', Types::BOOLEAN, [
			'notnull' => false,
			'default' => true,
		]);
		return $schema;
	}
}
