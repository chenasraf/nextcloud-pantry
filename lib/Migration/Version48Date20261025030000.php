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
 * Restock sync state on checklist items: a quantity a member set by hand, and
 * a reopen a member asked for, both held until the item is next done.
 */
class Version48Date20261025030000 extends SimpleMigrationStep {
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
		$changed = false;
		foreach (['quantity_pinned', 'member_reopened'] as $column) {
			if (!$table->hasColumn($column)) {
				$table->addColumn($column, Types::BOOLEAN, [
					'notnull' => false,
					'default' => false,
				]);
				$changed = true;
			}
		}
		return $changed ? $schema : null;
	}
}
