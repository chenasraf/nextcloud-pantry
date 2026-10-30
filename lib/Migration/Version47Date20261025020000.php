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
 * Stock links: each inventory's restock checklist, and on each inventory item
 * the checklist item that restocks it, when it was linked, and whether name
 * matching may link it.
 */
class Version47Date20261025020000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$changed = false;

		$itemsTable = Application::tableName('list_items');
		if ($schema->hasTable($itemsTable)) {
			$table = $schema->getTable($itemsTable);
			if (!$table->hasColumn('linked_item_id')) {
				$table->addColumn('linked_item_id', Types::BIGINT, [
					'notnull' => false,
					'length' => 20,
				]);
				$changed = true;
			}
			if (!$table->hasColumn('linked_at')) {
				$table->addColumn('linked_at', Types::BIGINT, [
					'notnull' => false,
					'length' => 20,
				]);
				$changed = true;
			}
			if (!$table->hasColumn('auto_link')) {
				$table->addColumn('auto_link', Types::BOOLEAN, [
					'notnull' => false,
					'default' => true,
				]);
				$changed = true;
			}
			if (!$table->hasIndex('pantry_items_linked_idx')) {
				$table->addIndex(['linked_item_id'], 'pantry_items_linked_idx');
				$changed = true;
			}
		}

		$listsTable = Application::tableName('lists');
		if ($schema->hasTable($listsTable)) {
			$table = $schema->getTable($listsTable);
			if (!$table->hasColumn('restock_checklist_id')) {
				$table->addColumn('restock_checklist_id', Types::BIGINT, [
					'notnull' => false,
					'length' => 20,
				]);
				$changed = true;
			}
		}

		return $changed ? $schema : null;
	}
}
