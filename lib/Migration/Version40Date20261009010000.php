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
 * Drop the per-column recurrence default now that item defaults carry it
 * (moved over by {@see Version39Date20261009000000}). The API still reports
 * the old fields, derived from the item defaults.
 */
class Version40Date20261009010000 extends SimpleMigrationStep {
	private const COLUMNS = [
		'default_recurrence_mode',
		'default_recurrence_kind',
		'default_rrule',
		'default_repeat_from_completion',
	];

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
		$dropped = false;
		foreach (self::COLUMNS as $column) {
			if ($table->hasColumn($column)) {
				$table->dropColumn($column);
				$dropped = true;
			}
		}

		return $dropped ? $schema : null;
	}
}
