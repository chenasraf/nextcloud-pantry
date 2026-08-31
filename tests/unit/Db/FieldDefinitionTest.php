<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Db;

use OCA\Pantry\Db\FieldDefinition;
use PHPUnit\Framework\TestCase;

class FieldDefinitionTest extends TestCase {
	/**
	 * Regression: `type` is NOT NULL with no DB default. A text field's value
	 * equals the PHP default ('text'), so without forcing the field dirty the
	 * INSERT omits it and Postgres rejects the null. A fresh entity must always
	 * carry `type` in its updated fields.
	 */
	public function testTypeIsAlwaysMarkedUpdated(): void {
		$def = new FieldDefinition();
		$this->assertArrayHasKey('type', $def->getUpdatedFields());

		$def->setType(FieldDefinition::TYPE_TEXT);
		$this->assertArrayHasKey('type', $def->getUpdatedFields());
		$this->assertSame('text', $def->getType());
	}
}
