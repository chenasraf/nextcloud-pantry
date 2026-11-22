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
 * Whether a photo references a file from the uploader's own Nextcloud files
 * rather than a copy Pantry owns, so deleting the photo leaves that file alone.
 */
class Version56Date20261122020000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$table = Application::tableName('photos');
		if (!$schema->hasTable($table)) {
			return null;
		}
		$photos = $schema->getTable($table);
		if ($photos->hasColumn('file_linked')) {
			return null;
		}
		$photos->addColumn('file_linked', Types::BOOLEAN, [
			'notnull' => false,
			'default' => false,
		]);
		return $schema;
	}
}
