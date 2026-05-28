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
 * Add delete_on_done_default to checklists. Remembers the user's last "Once"
 * choice on the add-item form so it prepopulates next time.
 */
class Version5Date20260528000000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$listsTable = Application::tableName('lists');
		if ($schema->hasTable($listsTable)) {
			$table = $schema->getTable($listsTable);
			if (!$table->hasColumn('delete_on_done_default')) {
				// Doctrine + MySQL: BOOLEAN with notnull=true + default=false can
				// fail on existing tables ("All declaration fragments that make
				// up the alteration must be provided"). Keep notnull=false and
				// rely on a default of false to give a sane initial value.
				$table->addColumn('delete_on_done_default', Types::BOOLEAN, [
					'notnull' => false,
					'default' => false,
				]);
			}
		}

		return $schema;
	}
}
