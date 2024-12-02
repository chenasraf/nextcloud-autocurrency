<?php

declare(strict_types=1);

namespace OCA\AutoCurrency\Controller;

use OCA\AutoCurrency\Service\FetchCurrenciesService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IConfig;
use OCP\IDateTimeFormatter;
use OCP\IL10N;
use OCP\IRequest;

/**
 * @psalm-suppress UnusedClass
 */
class ApiController extends OCSController {
	/** @var IConfig */
	private $config;

	/** @var IL10N */
	private $l;

	/** @var FetchCurrenciesService */
	private $service;

	/** @var IDateTimeFormatter */
	// private $dateTimeFormatter;

	/** @var IJobList */
	// private $jobList;

	/**
	 * Admin constructor.
	 *
	 * @param Collector $collector
	 * @param IConfig $config
	 * @param IL10N $l
	 * @param IDateTimeFormatter $dateTimeFormatter
	 * @param IJobList $jobList
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		IConfig $config,
		IL10N $l,
		FetchCurrenciesService $service,
		// IDateTimeFormatter $dateTimeFormatter,
		// IJobList $jobList,
	) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->l = $l;
		$this->service = $service;
		// $this->dateTimeFormatter = $dateTimeFormatter;
		// $this->jobList = $jobList;
	}

	/**
	 * An example API endpoint
	 *
	 * @return DataResponse<Http::STATUS_OK, array{message: string}, array{}>
	 *
	 * 200: Data returned
	 */
	// #[NoAdminRequired]
	// #[ApiRoute(verb: 'GET', url: '/api')]
	// public function index(): DataResponse {
	// 	return new DataResponse(
	// 		['message' => 'Hello world!']
	// 	);
	// }

	/**
	 * Get current cron information
	 *
	 * @return DataResponse<Http::STATUS_OK, array{last_update:string,interval:int}, array{}>
	 *
	 * 200: Data returned
	 */
	// #[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/cron')]
	public function getCronInfo(): DataResponse {
		$lastUpdate = $this->config->getAppValue('autocurrency', 'last_update', '');
		if ($lastUpdate === '') {
			$lastUpdate = null;
		}

		$interval = intval($this->config->getAppValue('autocurrency', 'cron_interval', '24'));
		// if ($lastUpdate !== null) {
		//   $lastUpdate = $this->dateTimeFormatter->formatDate($lastUpdate)
		// }

		return new DataResponse(
			['last_update' => $lastUpdate, 'interval' => $interval]
		);
	}

	/**
	 * Run cron immediately
	 *
	 * @return DataResponse<Http::STATUS_OK, array{status:string}, array{}>
	 *
	 * 200: Data returned
	 */
	// #[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/cron/run')]
	public function runCron(): DataResponse {
		$this->service->fetchCurrencyRates();

		return new DataResponse(
			['status' => 'OK']
		);
	}

	/**
	 * Update auto currency settings
	 *
	 * @return DataResponse<Http::STATUS_OK, array{status:string}, array{}>
	 *
	 * 200: Data returned
	 */
	// #[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/cron')]
	public function updateSettings(): DataResponse {
		$interval = $this->request->getParam('data')['interval'];
		$this->config->setAppValue('autocurrency', 'cron_interval', "$interval");
		return new DataResponse(
			['status' => 'OK']
		);
	}
}
