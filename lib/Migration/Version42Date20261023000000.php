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
 * Add `kind` to lists: what a list is for (checklist, template), fixed at
 * creation. Nullable so the schema self-heal can re-add it; a null reads as a
 * checklist.
 */
class Version42Date20261023000000 extends SimpleMigrationStep {
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
		if ($table->hasColumn('kind')) {
			return null;
		}
		$table->addColumn('kind', Types::STRING, [
			'notnull' => false,
			'length' => 16,
			'default' => 'checklist',
		]);

		return $schema;
	}
}
