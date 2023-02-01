<?php
namespace OCA\AutoCurrency\Cron;

use OCA\AutoCurrency\Service\FetchCurrenciesService;
use OCP\BackgroundJob\TimedJob;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\ILogger;

class FetchCurrenciesJob extends TimedJob {
  private FetchCurrenciesService $service;
  private ILogger $logger;

  public function __construct(ITimeFactory $time, FetchCurrenciesService $service, ILogger $logger) {
    parent::__construct($time);
    $this->service = $service;
    $this->logger = $logger;

    // Run once a day
    $this->setInterval(3600 * 24);
    $this->setTimeSensitivity(\OCP\BackgroundJob\IJob::TIME_INSENSITIVE);
  }

  protected function run($arguments) {
    $this->logger->info('Running cron job for FetchCurrenciesTask - args: ' . json_encode($arguments));
    $this->service->doCron();
  }
}
