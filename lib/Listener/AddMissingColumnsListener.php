<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Listener;

use OCA\Pantry\Migration\ExpectedColumns;
use OCP\DB\Events\AddMissingColumnsEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * Declares columns that item queries depend on, so schema drift (a migration
 * recorded as executed without its column actually landing — see issues #188
 * and #212) shows up in the admin overview and can be repaired with
 * `occ db:add-missing-columns`.
 *
 * The declarations live in {@see ExpectedColumns} so the same list also drives
 * `occ pantry:repair-schema`.
 *
 * @template-implements IEventListener<Event|AddMissingColumnsEvent>
 */
class AddMissingColumnsListener implements IEventListener {
	public function handle(Event $event): void {
		if (!($event instanceof AddMissingColumnsEvent)) {
			return;
		}

		foreach (ExpectedColumns::all() as $column) {
			$event->addMissingColumn(
				$column['table'],
				$column['name'],
				$column['type'],
				$column['options'],
			);
		}
	}
}
