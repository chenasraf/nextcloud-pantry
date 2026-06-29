<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Permission;

use Attribute;

/**
 * Declares the permissions required to call a controller action. Enforced by
 * {@see \OCA\Pantry\Middleware\PermissionMiddleware}, which resolves the
 * {houseId} (and, when present, {listId}) route parameters.
 *
 * - `caps`: every capability key must be granted by the union of the caller's
 *   roles (admins short-circuit to all-granted).
 * - `admin`: the action requires an admin role (house / role / member
 *   management). Implies no per-capability check.
 *
 * When the route carries a {listId}, list access is additionally enforced.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Permission {
	/**
	 * @param list<string> $caps Capability keys (see \OCA\Pantry\Db\Role::CAPABILITIES).
	 * @param bool $admin Require an admin role.
	 */
	public function __construct(
		public array $caps = [],
		public bool $admin = false,
	) {
	}
}
