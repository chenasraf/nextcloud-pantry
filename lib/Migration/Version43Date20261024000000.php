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
 * Attach templates to the checklists and inventories that draw suggestions
 * from them:
 *
 * - pantry_list_templates: which templates a list is attached to.
 * - pantry_lists.every_list: a template attached to every checklist and
 *   inventory in its house, including lists made later.
 */
class Version43Date20261024000000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$changed = false;

		$listsTable = Application::tableName('lists');
		if ($schema->hasTable($listsTable)) {
			$table = $schema->getTable($listsTable);
			if (!$table->hasColumn('every_list')) {
				$table->addColumn('every_list', Types::BOOLEAN, [
					'notnull' => false,
					'default' => false,
				]);
				$changed = true;
			}
		}

		$attachTable = Application::tableName('list_templates');
		if (!$schema->hasTable($attachTable)) {
			$table = $schema->createTable($attachTable);
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('house_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('list_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('template_id', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['list_id', 'template_id'], 'pantry_list_tpl_uniq');
			$table->addIndex(['template_id'], 'pantry_list_tpl_tpl_idx');
			$table->addIndex(['house_id'], 'pantry_list_tpl_house_idx');
			$changed = true;
		}

		return $changed ? $schema : null;
	}
}
