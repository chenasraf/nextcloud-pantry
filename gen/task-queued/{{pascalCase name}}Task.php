<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Your Name <your@email.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\NextcloudAppTemplate\Cron;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use Psr\Log\LoggerInterface;

class {{pascalCase name}}Task extends QueuedJob {
	public function __construct(
		ITimeFactory $time,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
	}

	protected function run($arguments): void {
		// $this->myService->doCron($arguments['uid']);
	}
}
