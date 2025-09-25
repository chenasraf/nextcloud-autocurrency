<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\AutoCurrency\Cron;

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
