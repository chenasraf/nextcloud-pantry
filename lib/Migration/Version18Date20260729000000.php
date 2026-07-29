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
 * Barcode (EAN/UPC) lookup support.
 *
 * - pantry_barcode_cache: global, shared cache of resolved barcodes. Not
 *   house-scoped — barcodes are universal, so one resolution serves every house.
 * - pantry_list_items.barcode: the barcode a checklist item was created from.
 */
class Version18Date20260729000000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$cacheTable = Application::tableName('barcode_cache');
		if (!$schema->hasTable($cacheTable)) {
			$table = $schema->createTable($cacheTable);
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('ean', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 512]);
			$table->addColumn('brand', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => null]);
			$table->addColumn('category', Types::STRING, ['notnull' => false, 'length' => 512, 'default' => null]);
			$table->addColumn('image_url', Types::STRING, ['notnull' => false, 'length' => 1024, 'default' => null]);
			$table->addColumn('provider', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('raw', Types::TEXT, ['notnull' => false, 'default' => null]);
			$table->addColumn('resolved_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['ean'], 'pantry_barcode_ean_uq');
		}

		$itemsTable = Application::tableName('list_items');
		if ($schema->hasTable($itemsTable)) {
			$table = $schema->getTable($itemsTable);
			if (!$table->hasColumn('barcode')) {
				$table->addColumn('barcode', Types::STRING, [
					'notnull' => false,
					'length' => 64,
					'default' => null,
				]);
			}
		}

		return $schema;
	}
}
