<?php

declare(strict_types=1);

namespace OCA\AutoCurrency\Controller;

use OCA\AutoCurrency\AppInfo;
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
	 * Get current cron information
	 *
	 * @return DataResponse<Http::STATUS_OK, array{
	 *	 last_update: non-empty-string|null,
	 *	 interval: int,
	 *	 supported_currencies: array{
	 *		 code: string,
	 *		 symbol: string,
	 *		 name: string
	 *	 }
	 * }, array{}>
	 *
	 * 200: Data returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/cron')]
	public function getCronInfo(): DataResponse {
		$lastUpdate = $this->config->getValueString(AppInfo\Application::APP_ID, 'last_update', '');
		if ($lastUpdate === '') {
			$lastUpdate = null;
		}

		$interval = $this->config->getValueInt(AppInfo\Application::APP_ID, 'cron_interval', 24);

		$supported = array_map(
			fn ($sym): array
				=> ['name' => $sym['name'], 'code' => $sym['code'], 'symbol' => $sym['symbol']],
			array_values($this->service->symbols)
		);

		return new DataResponse(
			['last_update' => $lastUpdate, 'interval' => $interval, 'supported_currencies' => $supported]
		);
	}

	/**
	 * Run cron immediately
	 *
	 * @return DataResponse<Http::STATUS_OK, array{status:non-empty-string}, array{}>
	 *
	 * 200: Data returned
	 */
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
	 * @param array{interval: int} $data Data to update
	 * @return DataResponse<Http::STATUS_OK, array{status:non-empty-string}, array{}>
	 *
	 * 200: Data returned
	 */
	// #[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/cron')]
	public function updateSettings(mixed $data): DataResponse {
		$interval = $data['interval'];
		$this->config->setValueInt(AppInfo\Application::APP_ID, 'cron_interval', $interval);
		return new DataResponse(
			['status' => 'OK']
		);
	}
}
