<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\AutoCurrency\Cron;

use OCA\AutoCurrency\Service\RemoveOldHistoryService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class RemoveOldHistoryTask extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private LoggerInterface $logger,
		private RemoveOldHistoryService $removeOldHistoryService,
	) {
		parent::__construct($time);
		$this->setInterval(3600 * 24);
	}

	protected function run($arguments): void {
		$this->logger->debug('RemoveOldHistoryTask: Starting cleanup of old history records');

		try {
			$deletedCount = $this->removeOldHistoryService->removeOldHistory();
			$this->logger->info("RemoveOldHistoryTask: Successfully removed {$deletedCount} old history records");
		} catch (\Exception $e) {
			$this->logger->error('RemoveOldHistoryTask: Failed to remove old history records: ' . $e->getMessage(), ['exception' => $e]);
		}
	}
}
