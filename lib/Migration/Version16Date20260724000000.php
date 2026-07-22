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
 * Add optional info fields to stores: location, opening hours, contact details,
 * responsible person and free-form notes.
 */
class Version16Date20260724000000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$storesTable = Application::tableName('stores');
		if (!$schema->hasTable($storesTable)) {
			return null;
		}
		$table = $schema->getTable($storesTable);

		if (!$table->hasColumn('location')) {
			$table->addColumn('location', Types::STRING, [
				'notnull' => false,
				'length' => 512,
				'default' => null,
			]);
		}
		if (!$table->hasColumn('opening_hours')) {
			$table->addColumn('opening_hours', Types::TEXT, [
				'notnull' => false,
				'default' => null,
			]);
		}
		if (!$table->hasColumn('contact')) {
			$table->addColumn('contact', Types::TEXT, [
				'notnull' => false,
				'default' => null,
			]);
		}
		if (!$table->hasColumn('responsible')) {
			$table->addColumn('responsible', Types::STRING, [
				'notnull' => false,
				'length' => 255,
				'default' => null,
			]);
		}
		if (!$table->hasColumn('notes')) {
			$table->addColumn('notes', Types::TEXT, [
				'notnull' => false,
				'default' => null,
			]);
		}

		return $schema;
	}
}
