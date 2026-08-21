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
 * Introduce user-defined labels and a many-to-many relation to checklist items.
 *
 * - pantry_labels: per-house tags (name + icon + color + custom sort), each
 *   optionally scoped to a single list (null list_id keeps the label global,
 *   offered on every list). Name uniqueness is per-scope (house_id + list_id).
 * - pantry_item_labels: many-to-many between checklist items and labels, so an
 *   item can carry several labels at once.
 */
class Version28Date20260821010000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$labelsTable = Application::tableName('labels');
		if (!$schema->hasTable($labelsTable)) {
			$table = $schema->createTable($labelsTable);
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('house_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('list_id', Types::BIGINT, ['notnull' => false, 'length' => 20]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 128]);
			$table->addColumn('icon', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('color', Types::STRING, ['notnull' => true, 'length' => 16]);
			$table->addColumn('sort_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['house_id'], 'pantry_label_house_idx');
			$table->addIndex(['list_id'], 'pantry_label_list_idx');
			$table->addUniqueIndex(['house_id', 'list_id', 'name'], 'pantry_label_scope_name_uq');
		}

		$itemLabelsTable = Application::tableName('item_labels');
		if (!$schema->hasTable($itemLabelsTable)) {
			$table = $schema->createTable($itemLabelsTable);
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('item_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('label_id', Types::BIGINT, ['notnull' => true]);
			// Explicit short index names: the auto-derived names for
			// "oc_pantry_item_labels" exceed Nextcloud's 30-char limit.
			$table->setPrimaryKey(['id'], 'pantry_ilabel_pkey');
			$table->addUniqueIndex(['item_id', 'label_id'], 'pantry_ilabel_uniq');
			$table->addIndex(['label_id'], 'pantry_ilabel_label_idx');
		}

		return $schema;
	}
}
