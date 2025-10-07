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
use OCA\AutoCurrency\Db\CustomCurrency;
use OCA\AutoCurrency\Db\CustomCurrencyMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\IAppConfig;

use Psr\Log\LoggerInterface;

class FetchCurrenciesService {
	private static $EXCHANGE_URL = 'https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/{base}.json';
	private static $SYMBOLS_FILE = __DIR__ . '/symbols.json';

	/** @var array<string, mixed> */
	public array $symbols = [];

	/** @var array<string, mixed> */
	private array $symbolPreference = [];

	/**
	 * Cache for API responses to avoid duplicate fetches
	 * @var array<string, array>
	 */
	private array $apiCache = [];

	/**
	 * Cache for custom currencies
	 * @var array<string, CustomCurrency>|null
	 */
	private ?array $customCurrenciesCache = null;

	public function __construct(
		private IAppConfig $config,
		private CurrencyMapper $currencyMapper,
		private CospendProjectMapper $projectMapper,
		private AutocurrencyRateHistoryMapper $historyMapper,
		private CustomCurrencyMapper $customCurrencyMapper,
		private LoggerInterface $logger,
	) {
		$this->config = $config;
		$this->currencyMapper = $currencyMapper;
		$this->projectMapper = $projectMapper;
		$this->historyMapper = $historyMapper;
		$this->customCurrencyMapper = $customCurrencyMapper;
		$this->logger = $logger;
		$this->loadSymbols();
	}

	public function fetchCurrencyRates(): void {
		$this->logger->info('Starting cron job to fetch currencies');
		$projects = $this->projectMapper->findAll();

		$this->logger->info('Found ' . count($projects) . ' projects');

		foreach ($projects as $project) {
			$this->processProject($project);
		}

		$lastUpdate = date('c');
		$this->config->setValueString(AppInfo\Application::APP_ID, 'last_update', $lastUpdate);
	}

	/**
	 * Process a single project - fetch and update all its currency rates
	 */
	private function processProject(object $project): void {
		$currencyName = $project->getCurrencyName();
		if (!$currencyName) {
			$this->logger->warning('Currency name not found for project ' . $project->id);
			return;
		}

		$base = $this->getCurrencyName($currencyName);
		$lbase = strtolower($base);

		$this->logger->info('Processing project ' . $project->id . ' with base currency ' . $base);

		$currencies = $this->findAllCurrencies($project->id);

		foreach ($currencies as $currency) {
			$this->processCurrency($currency, $project, $lbase);
		}
	}

	/**
	 * Process a single currency for a project
	 */
	private function processCurrency(Currency $currency, object $project, string $baseCurrency): void {
		$currencyCode = strtolower((string)$currency->getName());

		// Check if this currency has a custom configuration first
		$customCurrency = $this->findCustomCurrency($currencyCode);
		$source = null;

		if ($customCurrency !== null) {
			// Custom currency found - use custom logic
			$this->logger->info("Using custom currency configuration for $currencyCode");
			$result = $this->fetchCustomCurrencyRate($customCurrency, $baseCurrency);

			if ($result === null) {
				$this->logger->warning("Failed to fetch custom rate for $currencyCode, falling back to standard API");
				$newRate = $this->fetchStandardRate($baseCurrency, $currencyCode);
				$source = $this->replaceTokens(self::$EXCHANGE_URL, $baseCurrency);
			} else {
				$newRate = $result['rate'];
				$source = $result['source'];
			}
			$lcur = $currencyCode;
		} else {
			// Not a custom currency - validate against standard symbols
			$cur = $this->getCurrencyName($currency->getName());
			if ($cur === null) {
				$this->logger->error('Currency not found: ' . $currency->getName());
				return;
			}
			$lcur = strtolower($cur);

			// Use standard API
			$newRate = $this->fetchStandardRate($baseCurrency, $lcur);
			$source = $this->replaceTokens(self::$EXCHANGE_URL, $baseCurrency);
		}

		if ($newRate === null) {
			$this->logger->error("Failed to fetch rate for $currencyCode");
			return;
		}

		$this->updateCurrencyRate($currency, $project, $baseCurrency, $lcur, $newRate, $source);
	}

	/**
	 * Fetch exchange rate using the standard API
	 */
	private function fetchStandardRate(string $baseCurrency, string $targetCurrency): ?float {
		try {
			$json = $this->fetchStandardRates($baseCurrency);

			if (!isset($json[$baseCurrency][$targetCurrency])) {
				$this->logger->error("Rate not found for $targetCurrency in base $baseCurrency");
				return null;
			}

			$baseRate = $json[$baseCurrency][$targetCurrency];
			return 1.0 / $baseRate;
		} catch (\Throwable $e) {
			$this->logger->error('Error fetching standard rate: ' . $e->getMessage());
			return null;
		}
	}

	/**
	 * Fetch all exchange rates for a base currency from the standard API (with caching)
	 * @return array|mixed
	 */
	private function fetchStandardRates(string $baseCurrency): array {
		if (isset($this->apiCache[$baseCurrency])) {
			return $this->apiCache[$baseCurrency];
		}

		$this->logger->info('Fetching exchange rates for base currency ' . $baseCurrency);
		$url = $this->replaceTokens(self::$EXCHANGE_URL, $baseCurrency);

		$fp = fopen($url, 'r');
		if (!$fp) {
			throw new \RuntimeException("Failed to open URL: $url");
		}

		$data = stream_get_contents($fp);
		fclose($fp);

		$json = json_decode($data, true);

		if (!isset($json[$baseCurrency])) {
			throw new \RuntimeException("Failed to fetch exchange rates for base currency $baseCurrency");
		}

		$this->apiCache[$baseCurrency] = $json;
		return $json;
	}

	/**
	 * Update a currency's exchange rate in the database and history
	 */
	private function updateCurrencyRate(
		Currency $currency,
		object $project,
		string $baseCurrency,
		string $currencyName,
		float $rate,
		?string $source,
	): void {
		$currency->setExchangeRate($rate);
		$this->logger->info("Setting exchange rate for currency $currencyName to $rate");
		$this->currencyMapper->update($currency);

		$this->writeHistory(
			projectId: (string)$project->id,
			projectName: $this->safeProjectName($project),
			baseCurrency: $baseCurrency,
			currencyName: $currencyName,
			rate: $rate,
			currencyId: (int)$currency->getId(),
			source: $source
		);
	}

	/**
	 * Find a custom currency by its code
	 */
	private function findCustomCurrency(string $currencyCode): ?CustomCurrency {
		if ($this->customCurrenciesCache === null) {
			$this->customCurrenciesCache = [];
			$customCurrencies = $this->customCurrencyMapper->findAll();
			foreach ($customCurrencies as $cc) {
				$code = strtolower($cc->getCode());
				$this->customCurrenciesCache[$code] = $cc;
			}
			$this->logger->debug('Loaded ' . count($this->customCurrenciesCache) . ' custom currencies');
		}

		return $this->customCurrenciesCache[strtolower($currencyCode)] ?? null;
	}

	/**
	 * Fetch exchange rate from a custom currency endpoint
	 * @return array{rate: float, source: string}|null
	 */
	private function fetchCustomCurrencyRate(CustomCurrency $customCurrency, string $baseCurrency): ?array {
		try {
			$endpoint = $this->replaceTokens($customCurrency->getApiEndpoint(), $baseCurrency);
			$this->logger->debug("Fetching custom rate from: $endpoint");

			$hasBaseToken = strpos($customCurrency->getApiEndpoint(), '{base}') !== false
				|| strpos($customCurrency->getJsonPath(), '{base}') !== false;

			$apiKey = $customCurrency->getApiKey();
			$response = $this->fetchApiResponse($endpoint, $apiKey);
			$jsonPath = $this->replaceTokens($customCurrency->getJsonPath(), $baseCurrency);
			$rawRate = $this->extractJsonPath($response, $jsonPath);

			if ($rawRate === null) {
				$this->logger->error("Failed to extract rate from JSON path: $jsonPath");
				return null;
			}

			$rate = (float)$rawRate;

			// If {base} token wasn't used, we assume USD and may need to convert
			if (!$hasBaseToken && strtolower($baseCurrency) !== 'usd') {
				$this->logger->info("Custom currency endpoint doesn't use {base} token, assuming USD rate");
				$rate = $this->convertFromUsdToBase($rate, $baseCurrency);
			}

			return [
				'rate' => $rate,
				'source' => $endpoint,
			];
		} catch (\Throwable $e) {
			$this->logger->error('Error fetching custom currency rate: ' . $e->getMessage());
			return null;
		}
	}

	/**
	 * Replace tokens in a string (e.g., {base} with actual base currency)
	 */
	private function replaceTokens(string $text, string $baseCurrency): string {
		return str_replace('{base}', strtolower($baseCurrency), $text);
	}

	/**
	 * Fetch API response with caching
	 * @return array|mixed
	 */
	private function fetchApiResponse(string $url, ?string $apiKey = null): array {
		// Create a cache key that includes the API key (hashed for security)
		$cacheKey = $apiKey ? $url . ':' . md5($apiKey) : $url;

		if (isset($this->apiCache[$cacheKey])) {
			$this->logger->debug("Using cached response for: $url");
			return $this->apiCache[$cacheKey];
		}

		$this->logger->debug("Fetching API response from: $url");

		// Set up stream context with headers if API key is provided
		$opts = [
			'http' => [
				'method' => 'GET',
				'header' => '',
			],
		];

		if ($apiKey && $apiKey !== '') {
			$opts['http']['header'] = "Authorization: Bearer $apiKey\r\n";
			$this->logger->debug('Using API key for authentication');
		}

		$context = stream_context_create($opts);
		$fp = fopen($url, 'r', false, $context);
		if (!$fp) {
			throw new \RuntimeException("Failed to open URL: $url");
		}

		$data = stream_get_contents($fp);
		fclose($fp);

		$json = json_decode($data, true);

		if ($json === null) {
			throw new \RuntimeException("Failed to decode JSON from: $url");
		}

		$this->apiCache[$cacheKey] = $json;
		return $json;
	}

	/**
	 * Extract value from JSON using a simple JSON path notation
	 * Supports both formats:
	 *   - With $. prefix: $.key, $.key.subkey, $.key[0]
	 *   - Without prefix: key, key.subkey, key[0], rates.{base}.btc
	 * @param array<int,mixed> $data
	 */
	private function extractJsonPath(array $data, string $path): mixed {
		$path = preg_replace('/^\$\.?/', '', $path);

		if ($path === '') {
			return $data;
		}

		// Split path by dots and brackets
		$parts = preg_split('/\.|\[|\]/', $path, -1, PREG_SPLIT_NO_EMPTY);

		$current = $data;
		foreach ($parts as $part) {
			if (is_array($current)) {
				if (is_numeric($part)) {
					// Array index
					$index = (int)$part;
					if (!isset($current[$index])) {
						return null;
					}
					$current = $current[$index];
				} else {
					// Object key
					if (!isset($current[$part])) {
						return null;
					}
					$current = $current[$part];
				}
			} else {
				return null;
			}
		}

		return $current;
	}

	/**
	 * Convert a rate from USD to the actual base currency
	 * If we have a rate in USD but need it in EUR, we need to convert it
	 */
	private function convertFromUsdToBase(float $usdRate, string $baseCurrency): float {
		try {
			$this->logger->info("Converting rate from USD to $baseCurrency");

			// Fetch USD exchange rates
			$usdRates = $this->fetchStandardRates('usd');

			if (!isset($usdRates['usd'][$baseCurrency])) {
				$this->logger->error("Cannot convert: USD to $baseCurrency rate not found");
				return $usdRate; // Return original rate as fallback
			}

			// Get the USD -> baseCurrency rate
			$usdToBase = $usdRates['usd'][$baseCurrency];

			// Check for zero to avoid division by zero
			if ($usdToBase == 0) {
				$this->logger->error("Cannot convert: USD to $baseCurrency rate is zero");
				return $usdRate; // Return original rate as fallback
			}

			// Convert: if 1 CustomCurrency = X USD, and 1 USD = Y BaseCurrency
			// then 1 CustomCurrency = X * Y BaseCurrency
			// Example: 1 XMR = 320 USD, and 1 USD = 3.5 ILS → 1 XMR = 320 * 3.5 = 1120 ILS
			$convertedRate = $usdRate * $usdToBase;

			$this->logger->info("Converted rate from USD: $usdRate to $baseCurrency: $convertedRate");

			return $convertedRate;
		} catch (\Throwable $e) {
			$this->logger->error('Error converting from USD to base: ' . $e->getMessage());
			return $usdRate; // Return original rate as fallback
		}
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
		?string $source = null,
	): void {
		$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

		// Check for an existing sample at the same timestamp
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

		// Insert new history row
		try {
			$entity = new AutocurrencyRateHistory();

			// NOTE Keep rate as string to avoid float precision issues with DECIMAL in DB
			$rateStr = sprintf('%.10F', $rate);

			$entity->setProjectId($projectId);
			$entity->setProjectName($projectName);
			$entity->setBaseCurrency($baseCurrency);
			$entity->setCurrencyName($currencyName);
			$entity->setRate($rateStr);
			$entity->setFetchedAt($now->format(DATE_ATOM));
			$entity->setSource($source ?? $this->replaceTokens(self::$EXCHANGE_URL, $baseCurrency));
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
	public function getCurrencyName(string $name): ?string {
		$original = trim($name);
		$lower = mb_strtolower($original, 'UTF-8');

		// Prefer explicit 3-letter code tokens (e.g., "US Dollar (USD)")
		foreach ($this->symbols as $code => $currency) {
			$id = mb_strtolower((string)$code, 'UTF-8');
			if (preg_match('/\b' . preg_quote($id, '/') . '\b/u', $lower)) {
				return $id;
			}
		}

		// Symbol-based detection (avoid matching symbols attached to letters unless
		// those letters are part of the *symbol itself*, e.g., "R$")
		// Collect all codes hit by symbols, then resolve with preference.
		$hitsBySymbol = [];

		foreach ($this->symbols as $code => $currency) {
			if (empty($currency['symbol'])) {
				continue;
			}

			$sym = (string)$currency['symbol'];
			$symQuoted = preg_quote($sym, '/');
			$hasLetters = preg_match('/\p{L}/u', $sym) === 1;

			if ($hasLetters) {
				// Multi-char symbol that includes letters (e.g., "R$", "C$", "zł")
				$pattern = '/' . $symQuoted . '/u';
			} else {
				// Pure symbol (e.g., "$", "€", "₪") — require no letters adjacent
				// so "MN$" or "$U" won't match.
				$pattern = '/(?<!\p{L})' . $symQuoted . '(?!\p{L})/u';
			}

			if (preg_match($pattern, $original)) {
				$hitsBySymbol[$sym] ??= [];
				$hitsBySymbol[$sym][] = mb_strtolower((string)$code, 'UTF-8');
			}
		}

		if (!empty($hitsBySymbol)) {
			// Resolve ambiguity by symbol preference, else first hit
			foreach ($hitsBySymbol as $sym => $codes) {
				$preferred = $this->symbolPreference[$sym] ?? null;
				if ($preferred) {
					$preferredLower = mb_strtolower($preferred, 'UTF-8');
					if (in_array($preferredLower, $codes, true)) {
						return $preferredLower;
					}
				}
				// Fall back to first detected code for that symbol
				return $codes[0];
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
