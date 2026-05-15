<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Latch;

/**
 * Bootstrap orchestrator for everything Latch-related in Pantry.
 *
 * Application.php instantiates this class once (via the DI container) during
 * `boot()`. The act of constructing it eagerly resolves PantrySource (which
 * declares the `pantry` source) and PantryProvider (which registers Pantry's
 * own handlers on its provider points).
 *
 * Future handler-side code — Pantry consuming hooks from other apps — gets
 * added as another constructor dependency here. Application.php never has to
 * change again.
 */
class PantryLatch {
	public function __construct(
		PantrySource $source,
		PantryProvider $provider,
	) {
		// PantrySource and PantryProvider both perform their work in their own
		// constructors. Receiving them here forces DI to instantiate both
		// during boot, in the right order: source first (declares the hook
		// points), then provider (registers handlers against them).
		unset($source, $provider);
	}
}
