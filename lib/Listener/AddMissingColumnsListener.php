<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Listener;

use OCA\Pantry\AppInfo\Application;
use OCP\DB\Events\AddMissingColumnsEvent;
use OCP\DB\Types;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * Declares columns that item queries depend on, so schema drift (a migration
 * recorded as executed without its column actually landing — see issue #188)
 * shows up in the admin overview and can be repaired with
 * `occ db:add-missing-columns`.
 *
 * @template-implements IEventListener<Event|AddMissingColumnsEvent>
 */
class AddMissingColumnsListener implements IEventListener {
	public function handle(Event $event): void {
		if (!($event instanceof AddMissingColumnsEvent)) {
			return;
		}

		// Item-list queries filter on archived_at unconditionally; without the
		// column every checklist errors out. Mirror Version13's definition.
		$event->addMissingColumn(
			Application::tableName('list_items'),
			'archived_at',
			Types::BIGINT,
			['notnull' => false, 'length' => 20],
		);
	}
}
