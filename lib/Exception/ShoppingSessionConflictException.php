<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Exception;

use OCA\Pantry\Db\ShoppingSession;

/**
 * Signals a shopping-session lifecycle conflict that maps to HTTP 409 and
 * carries the conflicting session so the controller can return its state in the
 * body (the existing live session on a blocked create; the already-closed
 * session on a re-close).
 */
class ShoppingSessionConflictException extends \RuntimeException {
	public function __construct(
		private ShoppingSession $session,
		string $message = 'Shopping session conflict',
	) {
		parent::__construct($message);
	}

	public function getSession(): ShoppingSession {
		return $this->session;
	}
}
