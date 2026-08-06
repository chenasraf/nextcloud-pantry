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
 * Add per-house recurrence time. Recurring checklist items reopen at this time
 * of day (minutes since midnight, server timezone). Defaults to 08:00 (480).
 */
class Version23Date20260806100000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$housesTable = Application::tableName('houses');
		if ($schema->hasTable($housesTable)) {
			$table = $schema->getTable($housesTable);
			if (!$table->hasColumn('recurrence_time')) {
				$table->addColumn('recurrence_time', Types::INTEGER, [
					'notnull' => true,
					'default' => 480,
				]);
			}
		}

		return $schema;
	}
}
