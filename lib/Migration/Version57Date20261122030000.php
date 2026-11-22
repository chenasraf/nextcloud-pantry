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
 * Whether a template adds the new items typed on its attached lists to
 * itself.
 */
class Version57Date20261122030000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$table = Application::tableName('lists');
		if (!$schema->hasTable($table)) {
			return null;
		}
		$lists = $schema->getTable($table);
		if ($lists->hasColumn('learn_items')) {
			return null;
		}
		$lists->addColumn('learn_items', Types::BOOLEAN, [
			'notnull' => false,
			'default' => false,
		]);
		return $schema;
	}
}
