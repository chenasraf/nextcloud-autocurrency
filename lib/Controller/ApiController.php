<?php

declare(strict_types=1);

namespace OCA\AutoCurrency\Controller;

use OCA\AutoCurrency\Service\FetchCurrenciesService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IAppConfig;
use OCP\IDateTimeFormatter;
use OCP\IL10N;
use OCP\IRequest;

/**
 * @psalm-suppress UnusedClass
 */
class ApiController extends OCSController {
	/** @var IAppConfig */
	private $config;

	/** @var IL10N */
	private $l;

	/** @var FetchCurrenciesService */
	private $service;

	/**
	 * Admin constructor.
	 *
	 * @param Collector $collector
	 * @param IAppConfig $config
	 * @param IL10N $l
	 * @param IDateTimeFormatter $dateTimeFormatter
	 * @param IJobList $jobList
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		IAppConfig $config,
		IL10N $l,
		FetchCurrenciesService $service,
	) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->l = $l;
		$this->service = $service;
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
	 * @return DataResponse<Http::STATUS_OK, array{last_update:non-empty-string|null,interval:int}, array{}>
	 *
	 * 200: Data returned
	 */
	// #[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/cron')]
	public function getCronInfo(): DataResponse {
		$lastUpdate = $this->config->getValueString('autocurrency', 'last_update', '');
		if ($lastUpdate === '') {
			$lastUpdate = null;
		}

		$interval = $this->config->getValueInt('autocurrency', 'cron_interval', 24);

		return new DataResponse(
			['last_update' => $lastUpdate, 'interval' => $interval]
		);
	}

	/**
	 * Run cron immediately
	 *
	 * @return DataResponse<Http::STATUS_OK, array{status:non-empty-string}, array{}>
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
	 * @return DataResponse<Http::STATUS_OK, array{status:non-empty-string}, array{}>
	 *
	 * 200: Data returned
	 */
	// #[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/cron')]
	public function updateSettings(): DataResponse {
		$interval = $this->request->getParam('data')['interval'];
		$this->config->setValueInt('autocurrency', 'cron_interval', $interval);
		return new DataResponse(
			['status' => 'OK']
		);
	}
}
