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
 * Bind a note to a file it mirrors.
 *
 * `sync_hash` holds the content both sides last agreed on. Each direction
 * compares against it before writing, which is what stops a write from echoing
 * back as a second write in the opposite direction.
 *
 * `sync_owner_uid` is needed because notes are house-wide but the file lives in
 * one account's storage: any member's edit has to be written as that account.
 *
 * Short `nsync_` index name stays inside Oracle's 30-char identifier limit.
 */
class Version37Date20260923000000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		$notesTable = Application::tableName('notes');
		if (!$schema->hasTable($notesTable)) {
			return null;
		}
		$table = $schema->getTable($notesTable);

		if (!$table->hasColumn('sync_file_id')) {
			$table->addColumn('sync_file_id', Types::BIGINT, [
				'notnull' => false,
				'length' => 20,
				'default' => null,
			]);
		}
		if (!$table->hasColumn('sync_owner_uid')) {
			$table->addColumn('sync_owner_uid', Types::STRING, [
				'notnull' => false,
				'length' => 64,
				'default' => null,
			]);
		}
		if (!$table->hasColumn('sync_hash')) {
			$table->addColumn('sync_hash', Types::STRING, [
				'notnull' => false,
				'length' => 64,
				'default' => null,
			]);
		}
		if (!$table->hasColumn('sync_at')) {
			$table->addColumn('sync_at', Types::BIGINT, [
				'notnull' => false,
				'length' => 20,
				'default' => null,
			]);
		}

		// Every file write on the instance looks a note up by this column.
		if (!$table->hasIndex('pantry_nsync_file_idx')) {
			$table->addIndex(['sync_file_id'], 'pantry_nsync_file_idx');
		}

		return $schema;
	}
}
