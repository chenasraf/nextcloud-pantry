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
 * Expiry on inventory items: the expiry date (midnight timestamp, as a date
 * field's value) and shelf life in days, and each inventory's expiry warning,
 * in days before the date.
 */
class Version46Date20261025010000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$changed = false;

		$itemsTable = Application::tableName('list_items');
		if ($schema->hasTable($itemsTable)) {
			$table = $schema->getTable($itemsTable);
			if (!$table->hasColumn('expires_at')) {
				$table->addColumn('expires_at', Types::BIGINT, [
					'notnull' => false,
					'length' => 20,
				]);
				$changed = true;
			}
			if (!$table->hasColumn('shelf_life_days')) {
				$table->addColumn('shelf_life_days', Types::INTEGER, [
					'notnull' => false,
					'default' => null,
				]);
				$changed = true;
			}
		}

		$listsTable = Application::tableName('lists');
		if ($schema->hasTable($listsTable)) {
			$table = $schema->getTable($listsTable);
			if (!$table->hasColumn('expiry_lead_days')) {
				$table->addColumn('expiry_lead_days', Types::INTEGER, [
					'notnull' => false,
					'default' => null,
				]);
				$changed = true;
			}
		}

		return $changed ? $schema : null;
	}
}
