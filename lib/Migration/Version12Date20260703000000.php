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
 * Polymorphic per-user sharing.
 *
 * pantry_shares grants a specific house member view/edit access to a single
 * entity (checklist, note, photo or photo folder). Shares are additive on top
 * of the role system: a user's effective level on an item is the max of their
 * role-derived level and any share.
 */
class Version12Date20260703000000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$sharesTable = Application::tableName('shares');
		if (!$schema->hasTable($sharesTable)) {
			$table = $schema->createTable($sharesTable);
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('house_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('entity_type', Types::STRING, ['notnull' => true, 'length' => 16]);
			$table->addColumn('entity_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('shared_with_uid', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('permission', Types::STRING, ['notnull' => true, 'length' => 16]);
			$table->addColumn('created_by', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['entity_type', 'entity_id', 'shared_with_uid'], 'pantry_share_ent_uid_uq');
			$table->addIndex(['house_id', 'shared_with_uid'], 'pantry_share_house_uid_idx');
			$table->addIndex(['entity_type', 'entity_id'], 'pantry_share_entity_idx');
		}

		return $schema;
	}
}
