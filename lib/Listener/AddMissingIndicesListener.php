<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Listener;

use OCA\Pantry\AppInfo\Application;
use OCP\DB\Events\AddMissingIndicesEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * Declares indices that should exist, so drift (an index a migration was
 * recorded as having created but that is actually missing)
 * surfaces in the admin overview and can be repaired with
 * `occ db:add-missing-indices`.
 *
 * @template-implements IEventListener<Event|AddMissingIndicesEvent>
 */
class AddMissingIndicesListener implements IEventListener {
	public function handle(Event $event): void {
		if (!($event instanceof AddMissingIndicesEvent)) {
			return;
		}

		// Companion to archived_at (Version13); keeps the archived-items filter
		// from doing a full scan once the column is restored.
		$event->addMissingIndex(
			Application::tableName('list_items'),
			'pantry_items_archived_idx',
			['archived_at'],
		);
	}
}
