<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Your Name <your@email.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\NextcloudAppTemplate\Cron;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class {{pascalCase name}}Task extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);

		// Run once an hour
		$this->setInterval(3600);
	}

	protected function run($arguments): void {
		// $this->myService->doCron($arguments['uid']);
	}
}
