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
 * Add deleted_at timestamps to checklists, notes, and photos for soft deletion,
 * matching the existing checklist-items trash pattern.
 */
class Version9Date20260615000000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->addDeletedAt($schema, Application::tableName('lists'), 'pantry_lists_deleted_idx');
		$this->addDeletedAt($schema, Application::tableName('notes'), 'pantry_notes_deleted_idx');
		$this->addDeletedAt($schema, Application::tableName('photos'), 'pantry_photos_deleted_idx');

		return $schema;
	}

	private function addDeletedAt(ISchemaWrapper $schema, string $tableName, string $indexName): void {
		if (!$schema->hasTable($tableName)) {
			return;
		}
		$table = $schema->getTable($tableName);
		if (!$table->hasColumn('deleted_at')) {
			$table->addColumn('deleted_at', Types::BIGINT, [
				'notnull' => false,
				'length' => 20,
			]);
			$table->addIndex(['deleted_at'], $indexName);
		}
	}
}
