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
 * Add an is_pinned flag to notes. Pinned notes always sort to the top of the
 * list regardless of the active sort mode; manual reordering is constrained
 * to within the pinned and unpinned groups.
 */
class Version8Date20260614000000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$notesTable = Application::tableName('notes');
		if ($schema->hasTable($notesTable)) {
			$table = $schema->getTable($notesTable);
			if (!$table->hasColumn('is_pinned')) {
				// Doctrine + MySQL: BOOLEAN with notnull=true fails the schema
				// integrity check ("type is Bool and also NotNull, so it can not
				// store false"). Keep notnull=false with a default of false.
				$table->addColumn('is_pinned', Types::BOOLEAN, [
					'notnull' => false,
					'default' => false,
				]);
			}
		}

		return $schema;
	}
}
