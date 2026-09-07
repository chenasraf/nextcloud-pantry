<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Migration;

use Closure;
use OCA\Pantry\AppInfo\Application;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Drop delete_on_done_default now that the recurrence default carries the same
 * choice (backfilled by {@see Version32Date20260908000000}). The API still
 * reports deleteOnDoneDefault, derived from the recurrence default.
 */
class Version33Date20260908010000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$listsTable = Application::tableName('lists');
		if (!$schema->hasTable($listsTable)) {
			return null;
		}
		$table = $schema->getTable($listsTable);
		if (!$table->hasColumn('delete_on_done_default')) {
			return null;
		}
		$table->dropColumn('delete_on_done_default');

		return $schema;
	}
}
