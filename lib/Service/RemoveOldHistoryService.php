<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\AutoCurrency\Service;

use DateTimeImmutable;
use OCA\AutoCurrency\AppInfo;
use OCA\AutoCurrency\Db\AutocurrencyRateHistoryMapper;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

class RemoveOldHistoryService {
	public function __construct(
		private LoggerInterface $logger,
		private IAppConfig $config,
		private AutocurrencyRateHistoryMapper $historyMapper,
	) {
		//
	}

	/**
	 * Remove old history records based on the retention_days setting
	 * If retention_days is 0, no records are deleted (no limit)
	 * If retention_days is > 0, records older than that many days are deleted
	 *
	 * @return int Number of records deleted
	 */
	public function removeOldHistory(): int {
		$retentionDays = $this->config->getValueInt(AppInfo\Application::APP_ID, 'retention_days', 30);

		// If retention is 0, don't delete anything (no limit)
		if ($retentionDays === 0) {
			$this->logger->debug('History retention is set to 0 (no limit), skipping cleanup');
			return 0;
		}

		// Calculate the cutoff date
		$cutoffDate = new DateTimeImmutable("-{$retentionDays} days");
		$this->logger->info("Removing history records older than {$retentionDays} days (before {$cutoffDate->format('Y-m-d H:i:s')})");

		// Delete old records
		$deletedCount = $this->historyMapper->deleteOlderThan($cutoffDate);
		$this->logger->info("Removed {$deletedCount} old history records");

		return $deletedCount;
	}
}
