<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\AutoCurrency\Controller;

use DateTimeImmutable;
use OCA\AutoCurrency\AppInfo;
use OCA\AutoCurrency\Db\AutocurrencyRateHistoryMapper;
use OCA\AutoCurrency\Db\CospendProjectMapper;
use OCA\AutoCurrency\Db\CurrencyMapper;
use OCA\AutoCurrency\Service\FetchCurrenciesService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IAppConfig;
use OCP\IDateTimeFormatter;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @psalm-suppress UnusedClass
 */
class ApiController extends OCSController {
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
		private LoggerInterface $logger,
		private IAppConfig $config,
		private IL10N $l,
		private IUserSession $userSession,
		private FetchCurrenciesService $service,
		private CurrencyMapper $currencyMapper,
		private CospendProjectMapper $projectMapper,
		private AutocurrencyRateHistoryMapper $historyMapper,
	) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->l = $l;
		$this->service = $service;
	}

	/**
	 * Get current settings
	 *
	 * @return DataResponse<Http::STATUS_OK, array{
	 *	 last_update: non-empty-string|null,
	 *	 interval: int,
	 * }, array{}>
	 *
	 * 200: Data returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/settings')]
	public function getSettings(): DataResponse {
		$lastUpdate = $this->config->getValueString(AppInfo\Application::APP_ID, 'last_update', '');
		if ($lastUpdate === '') {
			$lastUpdate = null;
		}

		$interval = $this->config->getValueInt(AppInfo\Application::APP_ID, 'cron_interval', 24);

		return new DataResponse(
			['last_update' => $lastUpdate, 'interval' => $interval]
		);
	}

	/**
	 * Get current user settings
	 *
	 * @return DataResponse<Http::STATUS_OK, array{
	 *	 supported_currencies: array{
	 *		 code: string,
	 *		 symbol: string,
	 *		 name: string
	 *	 }
	 * }, array{}>
	 *
	 * 200: Data returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/user-settings')]
	public function getUserSettings(): DataResponse {
		$supported = array_map(
			fn ($sym): array
				=> ['name' => $sym['name'], 'code' => $sym['code'], 'symbol' => $sym['symbol']],
			array_values($this->service->symbols)
		);

		return new DataResponse(
			['supported_currencies' => $supported]
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
	#[ApiRoute(verb: 'PUT', url: '/api/settings')]
	public function updateSettings(mixed $data): DataResponse {
		$interval = $data['interval'];
		$this->config->setValueInt(AppInfo\Application::APP_ID, 'cron_interval', $interval);
		return new DataResponse(
			['status' => 'OK']
		);
	}

	/**
	 * List Cospend projects owned by calling user
	 *
	 * @return DataResponse<Http::STATUS_OK, array{
	 *   projects: list<array{id:string,name:string,currencyName:string|null}>
	 * }, array{}>
	 *
	 * 200: Data returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/projects')]
	public function getProjects(): DataResponse {
		$userId = $this->userSession->getUser()->getUID();
		$projects = $this->projectMapper->findAllByUser($userId);

		$list = [];
		foreach ($projects as $p) {
			$name = (string)$p->getName();
			$id = (string)$p->getId();
			$currencyName = (string)$p->getCurrencyName();
			$currencies = $this->currencyMapper->findAll($id);
			$currencyNames = array_map(function ($c) {
				$resolved = $this->service->getCurrencyName((string)$c->getName());
				return $resolved ?? strtolower((string)$c->getName());
			}, $currencies);

			$list[] = [
				'id' => $id,
				'name' => $name !== '' ? $name : $id,
				'baseCurrency' => $currencyName,
				'currencies' => $currencyNames,
			];
		}

		return new DataResponse(['projects' => $list]);
	}

	/**
	 * Get rate history for a project (uses the project's base currency)
	 *
	 * @param string $projectId Project ID (required)
	 * @param string|null $currency Quoted currency code to filter (e.g. "eur")
	 * @param string|null $from ISO-8601 datetime (inclusive)
	 * @param string|null $to ISO-8601 datetime (inclusive)
	 * @param int|null $limit Max rows to return (optional)
	 * @param int|null $offset Offset for pagination (optional)
	 *
	 * @return DataResponse<Http::STATUS_OK, array{
	 *   projectId: string,
	 *   baseCurrency: string,
	 *   points: list<array{
	 *     fetchedAt: string,
	 *     rate: string,
	 *     currencyName: string,
	 *     source: string|null
	 *   }>
	 * }, array{}>
	 *
	 * 200: Data returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/history')]
	public function getHistory(
		string $projectId,
		?string $currency = null,
		?string $from = null,
		?string $to = null,
		?int $limit = null,
		?int $offset = null,
	): DataResponse {
		if ($projectId === '') {
			return new DataResponse(['error' => 'projectId is required'], Http::STATUS_BAD_REQUEST);
		}

		// Parse dates if provided (ISO-8601). If invalid, treat as null.
		// If "to" is a DATE ONLY (no time), shift it to end-of-day 23:59:59.
		$fromDt = null;
		$toDt = null;
		try {
			if (is_string($from) && $from !== '') {
				$fromDt = new DateTimeImmutable($from);
			}
			if (is_string($to) && $to !== '') {
				// Date-only? e.g. "2025-09-25"
				if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) === 1) {
					$toDt = new DateTimeImmutable($to . ' 23:59:59');
				} else {
					$toDt = new DateTimeImmutable($to);
				}
			}
		} catch (\Throwable $e) {
			// ignore parsing errors; nulls mean "no bound"
		}

		// Resolve project and its base currency
		$this->logger->debug('Fetching history for project ' . $projectId . ' from ' . ($fromDt?->format(DATE_ATOM) ?? 'null') . ' to ' . ($toDt?->format(DATE_ATOM) ?? 'null'));
		$project = $this->projectMapper->find($projectId);
		$projectBase = $this->service->getCurrencyName($project->getCurrencyName());
		$lbase = strtolower((string)$projectBase);

		$rows = $this->historyMapper->findByProjectAndBase(
			projectId: $projectId,
			baseCurrency: $lbase,
			currencyName: is_string($currency) && $currency !== '' ? strtolower($currency) : null,
			from: $fromDt,
			to: $toDt,
			limit: (int)($limit ?? 0),
			offset: (int)($offset ?? 0),
			order: 'ASC'
		);

		$points = array_map(static function ($row) {
			/** @var \OCA\AutoCurrency\Db\AutocurrencyRateHistory $row */
			return [
				'fetchedAt' => $row->getFetchedAt() ? $row->getFetchedAt()->format(DATE_ATOM) : null,
				'rate' => $row->getRate(),
				'currencyName' => $row->getCurrencyName(),
				'source' => $row->getSource(),
			];
		}, $rows);

		return new DataResponse([
			'projectId' => $projectId,
			'baseCurrency' => $lbase,
			'points' => $points,
		]);
	}
}
