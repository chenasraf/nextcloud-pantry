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
 * When a finished shopping trip was undone, so its history can tell it apart
 * from one whose checks still stand.
 */
class Version55Date20261122010000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$table = Application::tableName('shopping_sessions');
		if (!$schema->hasTable($table)) {
			return null;
		}
		$sessions = $schema->getTable($table);
		if ($sessions->hasColumn('undone_at')) {
			return null;
		}
		$sessions->addColumn('undone_at', Types::BIGINT, ['notnull' => false, 'length' => 20]);
		return $schema;
	}
}
