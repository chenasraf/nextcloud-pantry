<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Db;

use OCA\Pantry\Db\Share;
use PHPUnit\Framework\TestCase;

class ShareTest extends TestCase {
	/**
	 * Regression: `permission` is NOT NULL with no DB default. A view share's
	 * value equals the PHP default ('view'), so without forcing the field dirty
	 * the INSERT omits it and Postgres rejects the null.
	 */
	public function testPermissionIsAlwaysMarkedUpdated(): void {
		$share = new Share();
		$this->assertArrayHasKey('permission', $share->getUpdatedFields());

		$share->setPermission(Share::PERM_VIEW);
		$this->assertArrayHasKey('permission', $share->getUpdatedFields());
		$this->assertSame('view', $share->getPermission());
	}
}
