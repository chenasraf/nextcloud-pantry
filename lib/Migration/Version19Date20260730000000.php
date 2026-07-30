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
 * Price support for checklist items.
 *
 * - price_type: null (no price), 'set' (a single amount in price_min) or
 *   'range' (price_min..price_max).
 * - price_min / price_max: the amount(s), verbatim in the chosen currency.
 * - price_currency: ISO 4217 code (e.g. USD, ILS) the amounts are expressed in.
 */
class Version19Date20260730000000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$itemsTable = Application::tableName('list_items');
		if ($schema->hasTable($itemsTable)) {
			$table = $schema->getTable($itemsTable);
			if (!$table->hasColumn('price_type')) {
				$table->addColumn('price_type', Types::STRING, [
					'notnull' => false,
					'length' => 8,
					'default' => null,
				]);
			}
			if (!$table->hasColumn('price_min')) {
				$table->addColumn('price_min', Types::FLOAT, [
					'notnull' => false,
					'default' => null,
				]);
			}
			if (!$table->hasColumn('price_max')) {
				$table->addColumn('price_max', Types::FLOAT, [
					'notnull' => false,
					'default' => null,
				]);
			}
			if (!$table->hasColumn('price_currency')) {
				$table->addColumn('price_currency', Types::STRING, [
					'notnull' => false,
					'length' => 8,
					'default' => null,
				]);
			}
		}

		return $schema;
	}
}
