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
 * What a member's tick of a checklist item put back into stock, kept on the
 * item so unchecking it can take it out again.
 */
class Version49Date20261025040000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$tableName = Application::tableName('list_items');
		if (!$schema->hasTable($tableName)) {
			return null;
		}
		$table = $schema->getTable($tableName);
		if ($table->hasColumn('restock_record')) {
			return null;
		}
		$table->addColumn('restock_record', Types::TEXT, [
			'notnull' => false,
			'default' => null,
		]);
		return $schema;
	}
}
