<?php

declare(strict_types=1);
// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\AutoCurrency\Service;

use DateTimeImmutable;

use DateTimeZone;
use Exception;
use OCA\AutoCurrency\AppInfo;
use OCA\AutoCurrency\Db\AutocurrencyRateHistory;
use OCA\AutoCurrency\Db\AutocurrencyRateHistoryMapper;
use OCA\AutoCurrency\Db\CospendProjectMapper;
use OCA\AutoCurrency\Db\Currency;
use OCA\AutoCurrency\Db\CurrencyMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\IAppConfig;

use Psr\Log\LoggerInterface;

class FetchCurrenciesService {
	private static $EXCHANGE_URL = 'https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/{base}.json';
	private static $SYMBOLS_FILE = __DIR__ . '/symbols.json';

	/**
	 * @var array<string, mixed>
	 */
	public array $symbols;

	public function __construct(
		private IAppConfig $config,
		private CurrencyMapper $currencyMapper,
		private CospendProjectMapper $projectMapper,
		private AutocurrencyRateHistoryMapper $historyMapper,
		private LoggerInterface $logger,
	) {
		$this->config = $config;
		$this->currencyMapper = $currencyMapper;
		$this->projectMapper = $projectMapper;
		$this->historyMapper = $historyMapper; // ⬅️ NEW
		$this->logger = $logger;
		$this->loadSymbols();
	}

	public function fetchCurrencyRates(): void {
		$this->logger->info('Starting cron job to fetch currencies');
		$projects = $this->projectMapper->findAll();
		$currencyMap = [];

		$this->logger->info('Found ' . count($projects) . ' projects');

		foreach ($projects as $project) {
			$currencyName = $project->getCurrencyName();
			if (!$currencyName) {
				$this->logger->warning('Currency name not found for project ' . $project->id);
				continue;
			}
			$base = $this->getCurrencyName($currencyName);
			$lbase = strtolower($base);

			if (isset($currencyMap[$base])) {
				$json = $currencyMap[$base];
			} else {
				// request currency exchange rates from the API
				$this->logger->info('Fetching exchange rates for base currency ' . $base);
				$fp = fopen(str_replace('{base}', $lbase, FetchCurrenciesService::$EXCHANGE_URL), 'r');
				$data = stream_get_contents($fp);
				fclose($fp);
				$json = json_decode($data, true);
				$this->logger->info('Fetched exchange rates for base currency: ' . json_encode($json));
				if ($json[$lbase] == null) {
					$this->logger->error(new \Error('Failed to fetch exchange rates for base currency ' . $base));
					continue;
				}
				$currencyMap[$lbase] = $json;
			}

			$currencies = $this->findAllCurrencies($project->id);

			foreach ($currencies as $currency) {
				$cur = $this->getCurrencyName($currency->getName());
				if ($cur === null) {
					$this->logger->error('Currency not found: ' . $currency->getName());
					continue;
				}
				$lcur = strtolower($cur);
				$baseRate = $json[$lbase][$lcur];
				$newRate = 1.0 / $baseRate;

				$currency->setExchangeRate($newRate);
				$this->logger->info('Setting exchange rate for currency ' . $cur . ' to ' . $newRate);
				$this->currencyMapper->update($currency);

				$this->writeHistory(
					projectId: (string)$project->id,
					projectName: $this->safeProjectName($project),
					baseCurrency: $lbase,
					currencyName: $lcur,
					rate: $newRate,
					currencyId: (int)$currency->getId()
				);
			}
		}

		$lastUpdate = date('c');
		$this->config->setValueString(AppInfo\Application::APP_ID, 'last_update', $lastUpdate);
	}

	/**
	 * Insert a rate history row. If a duplicate occurs due to the UNIQUE constraint
	 * (project_id, currency_name, fetched_at), we quietly ignore it.
	 */
	private function writeHistory(
		string $projectId,
		string $projectName,
		string $baseCurrency,
		string $currencyName,
		float $rate,
		int $currencyId,
	): void {
		$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

		// 1) Check for an existing sample at the same timestamp
		$existing = $this->historyMapper->findByProjectAndBase(
			projectId: $projectId,
			baseCurrency: $baseCurrency,
			currencyName: $currencyName,
			from: $now,
			to: $now,
			limit: 1,
			offset: 0,
			order: 'ASC'
		);

		if (!empty($existing)) {
			$this->logger->debug(sprintf(
				'History sample already exists (duplicate): project=%s base=%s cur=%s at=%s',
				$projectId,
				$baseCurrency,
				$currencyName,
				$now->format(DATE_ATOM)
			));
			return;
		}

		// 2) Insert new history row
		try {
			$entity = new AutocurrencyRateHistory();

			// Keep rate as string to avoid float precision issues with DECIMAL in DB
			$rateStr = sprintf('%.10F', $rate);

			$entity->setProjectId($projectId);
			$entity->setProjectName($projectName);
			$entity->setBaseCurrency($baseCurrency);
			$entity->setCurrencyName($currencyName);
			$entity->setRate($rateStr);
			$entity->setFetchedAt($now->format(DATE_ATOM));
			$entity->setSource(str_replace('{base}', $baseCurrency, self::$EXCHANGE_URL));
			$entity->setCurrencyId($currencyId);

			$this->historyMapper->insert($entity);
			$this->logger->debug('Inserted rate history row: ' . json_encode($entity->jsonSerialize()));
		} catch (\Throwable $e) {
			$this->logger->warning('Failed to insert rate history row: ' . $e->getMessage());
		}
	}


	/**
	 * Try to derive a nice project display name safely without hard-coding Cospend internals.
	 */
	private function safeProjectName(object $project): string {
		return (string)($project->getName() ?? $project->id); // last resort
	}

	/** Match the currency name from the known currencies. **/
	private function getCurrencyName(string $name): ?string {
		$name = strtolower($name);
		foreach ($this->symbols as $cur => $currency) {
			// e.g. usd
			$id = strtolower($cur);
			if (($name) === $id) {
				return $id;
			}

			// e.g. $, $ USD
			$symbol = $currency['symbol'];
			if (str_contains($name, $symbol)) {
				return $id;
			}

			// e.g. US Dollar (USD)
			preg_match('/\b' . $id . '\b/', $name, $matches);
			if (count($matches) > 0) {
				return $id;
			}
		}

		return null;
	}

	/** Load symbols from the symbols.json file */
	private function loadSymbols(): void {
		$this->symbols = json_decode(file_get_contents(FetchCurrenciesService::$SYMBOLS_FILE), true);
		$this->logger->debug('Loaded symbols: ' . json_encode($this->symbols));
	}

	/**
	 * @return list<Currency>
	 */
	public function findAllCurrencies(string $projectId): array {
		return $this->currencyMapper->findAll($projectId);
	}

	/**
	 * @return never
	 */
	private function handleException(Exception $e): void {
		if ($e instanceof DoesNotExistException
		  || $e instanceof MultipleObjectsReturnedException) {
			throw new CurrencyNotFound($e->getMessage());
		} else {
			throw $e;
		}
	}
}
