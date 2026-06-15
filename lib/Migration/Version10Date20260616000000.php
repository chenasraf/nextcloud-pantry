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
 * Add per-house trash retention. The PurgeExpiredTrashJob permanently deletes
 * any soft-deleted row whose deleted_at is older than now - retention_days.
 * Zero means "never auto-purge".
 */
class Version10Date20260616000000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$housesTable = Application::tableName('houses');
		if ($schema->hasTable($housesTable)) {
			$table = $schema->getTable($housesTable);
			if (!$table->hasColumn('trash_retention_days')) {
				$table->addColumn('trash_retention_days', Types::INTEGER, [
					'notnull' => true,
					'default' => 30,
				]);
			}
		}

		return $schema;
	}
}
