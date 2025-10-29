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
use OCA\AutoCurrency\Db\CustomCurrency;
use OCA\AutoCurrency\Db\CustomCurrencyMapper;
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
		private CustomCurrencyMapper $customCurrencyMapper,
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
	 *	 retention_days: int,
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
		$retentionDays = $this->config->getValueInt(AppInfo\Application::APP_ID, 'retention_days', 30);

		return new DataResponse(
			['last_update' => $lastUpdate, 'interval' => $interval, 'retention_days' => $retentionDays]
		);
	}

	/**
	 * Get current user settings
	 *
	 * @return DataResponse<Http::STATUS_OK, array{
	 *	 supported_currencies: list<array{
	 *		 code: string,
	 *		 symbol: string,
	 *		 name: string
	 *	 }>
	 * }, array{}>
	 *
	 * 200: Data returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/user-settings')]
	public function getUserSettings(): DataResponse {
		// Get standard currencies
		$supported = array_map(
			fn ($sym): array
				=> ['name' => $sym['name'], 'code' => $sym['code'], 'symbol' => $sym['symbol']],
			array_values($this->service->symbols)
		);

		// Add custom currencies
		$customCurrencies = $this->customCurrencyMapper->findAll();
		foreach ($customCurrencies as $custom) {
			$supported[] = [
				'name' => $custom->getCode(),
				'code' => $custom->getCode(),
				'symbol' => $custom->getSymbol() ?: $custom->getCode(),
			];
		}

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
	 * @param array{interval: int, retention_days?: int} $data Data to update
	 * @return DataResponse<Http::STATUS_OK, array{status:non-empty-string}, array{}>
	 *
	 * 200: Data returned
	 */
	#[ApiRoute(verb: 'PUT', url: '/api/settings')]
	public function updateSettings(mixed $data): DataResponse {
		$interval = $data['interval'];
		$this->config->setValueInt(AppInfo\Application::APP_ID, 'cron_interval', $interval);

		if (isset($data['retention_days'])) {
			$retentionDays = (int)$data['retention_days'];
			// Ensure it's not negative (0 = no limit, >0 = days to keep)
			if ($retentionDays < 0) {
				$retentionDays = 0;
			}
			$this->config->setValueInt(AppInfo\Application::APP_ID, 'retention_days', $retentionDays);
		}

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

		// Build a map of custom currency codes for quick lookup
		$customCurrenciesMap = [];
		foreach ($this->customCurrencyMapper->findAll() as $cc) {
			$customCurrenciesMap[strtolower($cc->getCode())] = true;
		}

		$list = [];
		foreach ($projects as $p) {
			$name = (string)$p->getName();
			$id = (string)$p->getId();
			$currencyName = (string)$p->getCurrencyName();
			$currencies = $this->currencyMapper->findAll($id);
			$currencyNames = array_map(function ($c) use ($customCurrenciesMap) {
				$currencyCode = strtolower((string)$c->getName());

				// Check if it's a custom currency first
				if (isset($customCurrenciesMap[$currencyCode])) {
					return $currencyCode;
				}

				// Otherwise try to resolve as standard currency
				$resolved = $this->service->getCurrencyName((string)$c->getName());
				return $resolved ?? $currencyCode;
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

	/**
	 * Get all custom currencies
	 *
	 * @return DataResponse<Http::STATUS_OK, array{
	 *   currencies: list<array{
	 *     id: int,
	 *     code: string,
	 *     symbol: string,
	 *     api_endpoint: string,
	 *     api_key: string,
	 *     json_path: string
	 *   }>
	 * }, array{}>
	 *
	 * 200: Data returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/custom-currencies')]
	public function getCustomCurrencies(): DataResponse {
		$currencies = $this->customCurrencyMapper->findAll();
		$serialized = array_map(fn ($c) => $c->jsonSerialize(), $currencies);
		return new DataResponse(['currencies' => $serialized]);
	}

	/**
	 * Create a new custom currency
	 *
	 * @param array{
	 *		code: string,
	 *		symbol?: string,
	 *		api_endpoint: string,
	 *		json_path: string,
	 *		api_key?: string
	 * } $data Data to create
	 * @return DataResponse<Http::STATUS_CREATED, array{
	 *		id: int,
	 *		code: string,
	 *		symbol: string,
	 *		api_endpoint: string,
	 *		api_key: string,
	 *		json_path: string
	 * }, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_INTERNAL_SERVER_ERROR, array{error: string}, array{}>
	 *
	 * 201: Currency created
	 * 400: Bad request
	 * 500: Internal server error
	 */
	#[ApiRoute(verb: 'POST', url: '/api/custom-currencies')]
	public function createCustomCurrency(mixed $data): DataResponse {
		$requiredFields = ['code', 'api_endpoint', 'json_path'];
		foreach ($requiredFields as $field) {
			if (!isset($data[$field]) || !is_string($data[$field]) || trim($data[$field]) === '') {
				return new DataResponse(['error' => "Field '$field' is required"], Http::STATUS_BAD_REQUEST);
			}
		}
		$currency = new CustomCurrency();
		$currency->setCode(trim((string)$data['code']));
		if (isset($data['symbol']) && is_string($data['symbol'])) {
			$currency->setSymbol(trim((string)$data['symbol']));
		} else {
			$currency->setSymbol('');
		}
		$currency->setApiEndpoint(trim((string)$data['api_endpoint']));
		$currency->setJsonPath(trim((string)$data['json_path']));
		if (isset($data['api_key']) && is_string($data['api_key'])) {
			$currency->setApiKey(trim((string)$data['api_key']));
		} else {
			$currency->setApiKey('');
		}
		try {
			$this->customCurrencyMapper->insert($currency);
			return new DataResponse($currency, Http::STATUS_CREATED);
		} catch (\Exception $e) {
			$this->logger->error('Failed to create custom currency: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to create custom currency'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete a custom currency
	 *
	 * @param int $id Currency ID
	 * @return DataResponse<Http::STATUS_OK, array{status: non-empty-string}, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{error: string}, array{}>
	 *
	 * 200: Currency deleted
	 * 500: Internal server error
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/custom-currencies/{id}')]
	public function deleteCustomCurrency(int $id): DataResponse {
		try {
			$currency = $this->customCurrencyMapper->find((string)$id);
			$this->customCurrencyMapper->delete($currency);
			return new DataResponse(['status' => 'OK']);
		} catch (\Exception $e) {
			$this->logger->error('Failed to delete custom currency: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to delete custom currency'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Update a custom currency
	 *
	 * @param int $id Currency ID
	 * @param array{
	 *		code?: string,
	 *		symbol?: string,
	 *		api_endpoint?: string,
	 *		json_path?: string,
	 *		api_key?: string
	 * } $data Data to update
	 * @return DataResponse<Http::STATUS_OK, array{
	 *		id: int,
	 *		code: string,
	 *		symbol: string,
	 *		api_endpoint: string,
	 *		api_key: string,
	 *		json_path: string
	 * }, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{error: string}, array{}>
	 *
	 * 200: Currency updated
	 * 500: Internal server error
	 */
	#[ApiRoute(verb: 'PUT', url: '/api/custom-currencies/{id}')]
	public function updateCustomCurrency(int $id, mixed $data): DataResponse {
		try {
			$currency = $this->customCurrencyMapper->find((string)$id);
			if (isset($data['code']) && is_string($data['code']) && trim((string)$data['code']) !== '') {
				$currency->setCode(trim((string)$data['code']));
			}
			if (isset($data['symbol']) && is_string($data['symbol']) && trim((string)$data['symbol']) !== '') {
				$currency->setSymbol(trim((string)$data['symbol']));
			}
			if (isset($data['api_endpoint']) && is_string($data['api_endpoint']) && trim((string)$data['api_endpoint']) !== '') {
				$currency->setApiEndpoint(trim((string)$data['api_endpoint']));
			}
			if (isset($data['json_path']) && is_string($data['json_path']) && trim((string)$data['json_path']) !== '') {
				$currency->setJsonPath(trim((string)$data['json_path']));
			}
			if (array_key_exists('api_key', $data)) {
				if (is_string($data['api_key'])) {
					$currency->setApiKey(trim((string)$data['api_key']));
				} else {
					$currency->setApiKey('');
				}
			}
			$this->customCurrencyMapper->update($currency);
			return new DataResponse($currency);
		} catch (\Exception $e) {
			$this->logger->error('Failed to update custom currency: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to update custom currency'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
