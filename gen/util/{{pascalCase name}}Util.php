<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Your Name <your@email.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\NextcloudAppTemplate\Util;

use Psr\Log\LoggerInterface;

class {{pascalCase name}}Util {
	public function __construct(
		private LoggerInterface $logger,
	)	{
		//
	}

	// public function doSomething(): void {
	// 	// Do something
	// }
}
