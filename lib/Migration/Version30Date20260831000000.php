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
 * Custom fields on checklist items.
 *
 * Three tables: per-house/per-list field definitions, the options of a `select`
 * field, and the per-item typed values. Plus a `can_edit_fields` capability
 * column on the roles table. Value rows are populated by later work; the table
 * is created here so no follow-up migration is needed.
 *
 * Booleans are declared `notnull => false` with a default: Doctrine binds PHP
 * false as NULL under NOT NULL, which the schema integrity check rejects.
 * Index/constraint names stay <= 30 chars for Oracle compatibility, and primary
 * keys carry explicit short names because the auto-derived pkey name would
 * exceed that limit.
 */
class Version30Date20260831000000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$defs = Application::tableName('field_defs');
		if (!$schema->hasTable($defs)) {
			$table = $schema->createTable($defs);
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('house_id', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			// null = house-wide (all lists), a set id scopes the field to one list.
			$table->addColumn('list_id', Types::BIGINT, ['notnull' => false, 'length' => 20]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 128]);
			// Enum: text | number | checkbox | date | select.
			$table->addColumn('type', Types::STRING, ['notnull' => true, 'length' => 16]);
			$table->addColumn('sort_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->addColumn('hint', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('multiline', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
			$table->addColumn('default_text', Types::STRING, ['notnull' => false, 'length' => 1024]);
			$table->addColumn('default_number', Types::FLOAT, ['notnull' => false]);
			$table->addColumn('default_bool', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
			$table->addColumn('default_option_id', Types::BIGINT, ['notnull' => false, 'length' => 20]);
			// Enum: absolute | relative (date fields only).
			$table->addColumn('date_mode', Types::STRING, ['notnull' => false, 'length' => 16]);
			$table->addColumn('default_offset_days', Types::INTEGER, ['notnull' => false]);
			$table->addColumn('notify_default', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
			$table->addColumn('lead_days', Types::INTEGER, ['notnull' => false, 'default' => 0]);
			// Enum: field-only | item-override.
			$table->addColumn('override_policy', Types::STRING, ['notnull' => false, 'length' => 16]);
			$table->addColumn('stop_when_done', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
			$table->addColumn('deleted_at', Types::BIGINT, ['notnull' => false, 'length' => 20]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id'], 'pantry_fdef_pkey');
			$table->addIndex(['house_id'], 'pantry_fdef_house_idx');
			$table->addIndex(['list_id'], 'pantry_fdef_list_idx');
			$table->addUniqueIndex(['house_id', 'list_id', 'name'], 'pantry_fdef_scope_name_uq');
		}

		$optionsTable = Application::tableName('field_options');
		if (!$schema->hasTable($optionsTable)) {
			$table = $schema->createTable($optionsTable);
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('field_id', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$table->addColumn('label', Types::STRING, ['notnull' => true, 'length' => 128]);
			$table->addColumn('sort_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->setPrimaryKey(['id'], 'pantry_fopt_pkey');
			$table->addIndex(['field_id'], 'pantry_fopt_field_idx');
		}

		$values = Application::tableName('field_values');
		if (!$schema->hasTable($values)) {
			$table = $schema->createTable($values);
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('item_id', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$table->addColumn('field_id', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$table->addColumn('value_text', Types::TEXT, ['notnull' => false]);
			$table->addColumn('value_number', Types::FLOAT, ['notnull' => false]);
			$table->addColumn('value_bool', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
			$table->addColumn('value_date', Types::BIGINT, ['notnull' => false, 'length' => 20]);
			$table->addColumn('value_option_id', Types::BIGINT, ['notnull' => false, 'length' => 20]);
			$table->addColumn('offset_days', Types::INTEGER, ['notnull' => false]);
			// This row overrides the field's reminder default when true.
			$table->addColumn('notify_override', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
			$table->addColumn('notify_enabled', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
			$table->addColumn('notify_lead_days', Types::INTEGER, ['notnull' => false]);
			$table->addColumn('notified_for_date', Types::BIGINT, ['notnull' => false, 'length' => 20]);
			$table->setPrimaryKey(['id'], 'pantry_fval_pkey');
			$table->addUniqueIndex(['item_id', 'field_id'], 'pantry_fval_uniq');
			$table->addIndex(['item_id'], 'pantry_fval_item_idx');
			$table->addIndex(['field_id'], 'pantry_fval_field_idx');
			$table->addIndex(['value_date'], 'pantry_fval_date_idx');
		}

		$roles = Application::tableName('roles');
		if ($schema->hasTable($roles)) {
			$table = $schema->getTable($roles);
			if (!$table->hasColumn('can_edit_fields')) {
				$table->addColumn('can_edit_fields', Types::BOOLEAN, [
					'notnull' => false,
					'default' => false,
				]);
			}
		}

		return $schema;
	}
}
