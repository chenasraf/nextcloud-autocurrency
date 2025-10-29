<?php

declare(strict_types=1);

namespace Service;

use DateTimeImmutable;
use OCA\AutoCurrency\AppInfo\Application as App;
use OCA\AutoCurrency\Db\AutocurrencyRateHistoryMapper;
use OCA\AutoCurrency\Service\RemoveOldHistoryService;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class RemoveOldHistoryServiceTest extends TestCase {
	/** @var IAppConfig&MockObject */
	private $config;
	/** @var AutocurrencyRateHistoryMapper&MockObject */
	private $historyMapper;
	/** @var LoggerInterface&MockObject */
	private $logger;

	private function buildService(): RemoveOldHistoryService {
		$this->config = $this->createMock(IAppConfig::class);
		$this->historyMapper = $this->createMock(AutocurrencyRateHistoryMapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		return new RemoveOldHistoryService(
			$this->logger,
			$this->config,
			$this->historyMapper
		);
	}

	public function testRemoveOldHistory_WithRetentionDays30_DeletesOldRecords(): void {
		$service = $this->buildService();

		// Config returns retention_days = 30
		$this->config->expects($this->once())
			->method('getValueInt')
			->with(App::APP_ID, 'retention_days', 30)
			->willReturn(30);

		// Expect deleteOlderThan to be called with a date ~30 days ago
		$this->historyMapper->expects($this->once())
			->method('deleteOlderThan')
			->willReturnCallback(function ($cutoffDate) {
				$this->assertInstanceOf(DateTimeImmutable::class, $cutoffDate);
				// Verify the cutoff date is approximately 30 days ago
				$expectedDate = new DateTimeImmutable('-30 days');
				$diff = abs($cutoffDate->getTimestamp() - $expectedDate->getTimestamp());
				// Allow 1 second tolerance for test execution time
				$this->assertLessThanOrEqual(1, $diff);
				return 42; // Simulate 42 records deleted
			});

		$deletedCount = $service->removeOldHistory();
		$this->assertSame(42, $deletedCount);
	}

	public function testRemoveOldHistory_WithRetentionDaysZero_SkipsCleanup(): void {
		$service = $this->buildService();

		// Config returns retention_days = 0 (no limit)
		$this->config->expects($this->once())
			->method('getValueInt')
			->with(App::APP_ID, 'retention_days', 30)
			->willReturn(0);

		// deleteOlderThan should NOT be called
		$this->historyMapper->expects($this->never())
			->method('deleteOlderThan');

		$this->logger->expects($this->once())
			->method('debug')
			->with($this->stringContains('no limit'));

		$deletedCount = $service->removeOldHistory();
		$this->assertSame(0, $deletedCount);
	}

	public function testRemoveOldHistory_WithRetentionDays7_DeletesOldRecords(): void {
		$service = $this->buildService();

		// Config returns retention_days = 7
		$this->config->expects($this->once())
			->method('getValueInt')
			->with(App::APP_ID, 'retention_days', 30)
			->willReturn(7);

		$this->historyMapper->expects($this->once())
			->method('deleteOlderThan')
			->willReturnCallback(function ($cutoffDate) {
				$this->assertInstanceOf(DateTimeImmutable::class, $cutoffDate);
				// Verify the cutoff date is approximately 7 days ago
				$expectedDate = new DateTimeImmutable('-7 days');
				$diff = abs($cutoffDate->getTimestamp() - $expectedDate->getTimestamp());
				$this->assertLessThanOrEqual(1, $diff);
				return 15; // Simulate 15 records deleted
			});

		$deletedCount = $service->removeOldHistory();
		$this->assertSame(15, $deletedCount);
	}

	public function testRemoveOldHistory_WithRetentionDays90_DeletesOldRecords(): void {
		$service = $this->buildService();

		// Config returns retention_days = 90
		$this->config->expects($this->once())
			->method('getValueInt')
			->with(App::APP_ID, 'retention_days', 30)
			->willReturn(90);

		$this->historyMapper->expects($this->once())
			->method('deleteOlderThan')
			->willReturnCallback(function ($cutoffDate) {
				$this->assertInstanceOf(DateTimeImmutable::class, $cutoffDate);
				// Verify the cutoff date is approximately 90 days ago
				$expectedDate = new DateTimeImmutable('-90 days');
				$diff = abs($cutoffDate->getTimestamp() - $expectedDate->getTimestamp());
				$this->assertLessThanOrEqual(1, $diff);
				return 100; // Simulate 100 records deleted
			});

		$deletedCount = $service->removeOldHistory();
		$this->assertSame(100, $deletedCount);
	}

	public function testRemoveOldHistory_LogsInfoMessages(): void {
		$service = $this->buildService();

		$this->config->expects($this->once())
			->method('getValueInt')
			->willReturn(30);

		$this->historyMapper->expects($this->once())
			->method('deleteOlderThan')
			->willReturn(25);

		// Verify that info messages are logged
		$this->logger->expects($this->exactly(2))
			->method('info')
			->willReturnCallback(function ($message) {
				static $callCount = 0;
				$callCount++;
				if ($callCount === 1) {
					$this->assertStringContainsString('Removing history records older than 30 days', $message);
				} elseif ($callCount === 2) {
					$this->assertStringContainsString('Removed 25 old history records', $message);
				}
			});

		$service->removeOldHistory();
	}

	public function testRemoveOldHistory_ReturnsZeroWhenNoRecordsDeleted(): void {
		$service = $this->buildService();

		$this->config->expects($this->once())
			->method('getValueInt')
			->willReturn(30);

		$this->historyMapper->expects($this->once())
			->method('deleteOlderThan')
			->willReturn(0); // No records to delete

		$deletedCount = $service->removeOldHistory();
		$this->assertSame(0, $deletedCount);
	}
}
