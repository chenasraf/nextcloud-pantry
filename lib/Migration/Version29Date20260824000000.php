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
 * Add archived_at to checklists so lists can be archived into a separate view,
 * matching the existing checklist-items archive pattern.
 */
class Version29Date20260824000000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$tableName = Application::tableName('lists');
		if (!$schema->hasTable($tableName)) {
			return null;
		}
		$table = $schema->getTable($tableName);
		if (!$table->hasColumn('archived_at')) {
			$table->addColumn('archived_at', Types::BIGINT, [
				'notnull' => false,
				'length' => 20,
			]);
			$table->addIndex(['archived_at'], 'pantry_lists_archived_idx');
		}

		return $schema;
	}
}
