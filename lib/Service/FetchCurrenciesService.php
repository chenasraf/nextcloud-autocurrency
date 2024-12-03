<?php

declare(strict_types=1);
// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\AutoCurrency\Service;

use Exception;

use OCA\AutoCurrency\Db\CospendProjectMapper;
use OCA\AutoCurrency\Db\Currency;
use OCA\AutoCurrency\Db\CurrencyMapper;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

class FetchCurrenciesService {
	private static $EXCHANGE_URL = 'https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/{base}.json';
	private IAppConfig $config;
	private CurrencyMapper $currencyMapper;
	private CospendProjectMapper $projectMapper;
	private LoggerInterface $logger;

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
	}

	public function fetchCurrencyRates(): void {
		$this->logger->info('Starting cron job to fetch currencies');
		$projects = $this->projectMapper->findAll();
		$currencyMap = [];

		$this->logger->info('Found ' . count($projects) . ' projects');

		foreach ($projects as $project) {
			$base = $this->getCurrencyName($project->getCurrencyname());
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
				$lcur = strtolower($cur);
				$newRate = floatval(number_format(1 / $json[$lbase][$lcur], 2));
				$currency->setExchangeRate($newRate);
				$this->logger->info('Setting exchange rate for currency ' . $cur . ' to ' . $newRate);
				$this->currencyMapper->update($currency);
			}
		}

		$lastUpdate = date('c');
		$this->config->setValueString('autocurrency', 'last_update', $lastUpdate);
	}

	private function getCurrencyName(string $name): string {
		// find 3-letter currency code for the base currency
		preg_match('/([A-Z]{3})/', $name, $matches);

		$this->logger->info('Matches: ' . json_encode($matches));

		if (count($matches) === 2) {
			$name = $matches[1];
		}

		return $name;
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
		if ($e instanceof DoesNotExistException ||
		  $e instanceof MultipleObjectsReturnedException) {
			throw new CurrencyNotFound($e->getMessage());
		} else {
			throw $e;
		}
	}
}
