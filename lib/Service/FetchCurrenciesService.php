<?php

declare(strict_types=1);
// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\AutoCurrency\Service;

use Exception;

use OCA\AutoCurrency\AppInfo;
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

	private IAppConfig $config;

	private CurrencyMapper $currencyMapper;

	private CospendProjectMapper $projectMapper;

	private LoggerInterface $logger;

	/**
	 * @var array<string, mixed>
	 */
	public array $symbols;

	public function __construct(
		IAppConfig $config,
		CurrencyMapper $currencyMapper,
		CospendProjectMapper $projectMapper,
		LoggerInterface $logger,
	) {
		$this->config = $config;
		$this->currencyMapper = $currencyMapper;
		$this->projectMapper = $projectMapper;
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

			$currencies = $this->findAll($project->id);

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
			}
		}

		$lastUpdate = date('c');
		$this->config->setValueString(AppInfo\Application::APP_ID, 'last_update', $lastUpdate);
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
	public function findAll(string $projectId): array {
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
