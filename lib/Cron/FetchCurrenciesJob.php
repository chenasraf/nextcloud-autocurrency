<?php

namespace OCA\AutoCurrency\Cron;

use OCA\AutoCurrency\Service\FetchCurrenciesService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

class FetchCurrenciesJob extends TimedJob {
	private FetchCurrenciesService $service;
	private LoggerInterface $logger;
	private IAppConfig $config;

	public function __construct(ITimeFactory $time, FetchCurrenciesService $service, LoggerInterface $logger, IAppConfig $config) {
		parent::__construct($time);
		$this->service = $service;
		$this->logger = $logger;
		$this->config = $config;

		// Run once a day
		$interval = $this->config->getValueInt('autocurrency', 'cron_interval', 24);
		$this->setInterval(3600 * $interval);
		$this->setTimeSensitivity(\OCP\BackgroundJob\IJob::TIME_INSENSITIVE);
		$this->logger->info('FetchCurrenciesJob initialized');
	}

	protected function run($arguments): void {
		$this->logger->info('Running cron job for FetchCurrenciesTask - args: ' . json_encode($arguments));
		$this->service->fetchCurrencyRates();
	}
}
