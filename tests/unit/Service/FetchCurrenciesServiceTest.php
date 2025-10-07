<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\AutoCurrency\Tests\Service;

use OCA\AutoCurrency\Db\AutocurrencyRateHistoryMapper;
use OCA\AutoCurrency\Db\CospendProjectMapper;
use OCA\AutoCurrency\Db\CurrencyMapper;
use OCA\AutoCurrency\Db\CustomCurrency;
use OCA\AutoCurrency\Db\CustomCurrencyMapper;
use OCA\AutoCurrency\Service\FetchCurrenciesService;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;

final class CurrencyResolverTest extends TestCase {
	private FetchCurrenciesService $resolver;

	protected function setUp(): void {
		$config = $this->createMock(IAppConfig::class);
		$currencyMapper = $this->createMock(CurrencyMapper::class);
		$projectMapper = $this->createMock(CospendProjectMapper::class);
		$historyMapper = $this->createMock(AutocurrencyRateHistoryMapper::class);
		$customCurrencyMapper = $this->createMock(CustomCurrencyMapper::class);
		$logger = $this->createMock(LoggerInterface::class);

		$customCurrencyMapper->method('findAll')->willReturn([]);

		$this->resolver = new FetchCurrenciesService(
			$config,
			$currencyMapper,
			$projectMapper,
			$historyMapper,
			$customCurrencyMapper,
			$logger
		);

		$symbols = [
			'USD' => ['symbol' => '$'],
			'CAD' => ['symbol' => 'C$'],
			'AUD' => ['symbol' => 'A$'],
			'EUR' => ['symbol' => '€'],
			'ILS' => ['symbol' => '₪'],
			'BRL' => ['symbol' => 'R$'],
			// NOTE: Intentionally NO "MN$" in map — we want to ensure it won't resolve to USD
			// Demonstrate that "$U" is *another* currency, not USD:
			'UYU' => ['symbol' => '$U'],
		];

		// Inject into private props via reflection (adjust names if yours differ)
		$ref = new ReflectionClass($this->resolver);

		$propSymbols = $ref->getProperty('symbols');
		$propSymbols->setAccessible(true);
		$propSymbols->setValue($this->resolver, $symbols);
	}

	/**
	 * @dataProvider provideCases
	 */
	public function testGetCurrencyName(string $input, ?string $expected): void {
		$ref = new ReflectionClass($this->resolver);
		$method = $ref->getMethod('getCurrencyName');
		$method->setAccessible(true);

		$got = $method->invoke($this->resolver, $input);
		$this->assertSame(
			$expected,
			$got,
			sprintf('Input: %s — expected %s, got %s', json_encode($input), json_encode($expected), json_encode($got))
		);
	}

	/**
	 * @return array<int,mixed>
	 */
	public static function provideCases(): array {
		return [
			// --- code token matches ---
			['USD', 'usd'],
			['us dollar (USD)', 'usd'],
			['Price in usd only', 'usd'],
			['EUR', 'eur'],
			[' ILS ', 'ils'],

			// --- bare symbol with preference (no adjacent letters) ---
			['$', 'usd'],
			['Price is $ 100', 'usd'],
			['€', 'eur'],
			['₪', 'ils'],

			// --- symbol + code / code + symbol (spacing ok) ---
			['$ USD', 'usd'],
			['USD $', 'usd'],
			['R$ 25,00', 'brl'],         // letter-including symbol
			['Preço R$25,00', 'brl'],    // attached to digits is fine

			// --- DO NOT turn *other* currencies into USD ---
			['MN$', null],               // not in our symbol map → should NOT become usd
			['$U 200', 'uyu'],           // is another currency → should NOT become usd
			['C$ 20', 'cad'],            // letter-including symbol maps to CAD

			// --- letters adjacent to a pure symbol block USD match ---
			['MN$ 100', null],           // letters touching $ should not match the bare-$ rule
			['The price is $U', 'uyu'],  // exact "$U" symbol maps to UYU (not usd)
			['$U$', 'uyu'],              // still contains "$U" → prefer that over bare "$"
		];
	}

	/**
	 * Test JSON path extraction with various formats
	 * @dataProvider provideJsonPathCases
	 */
	public function testExtractJsonPath(array $data, string $path, mixed $expected): void {
		$ref = new ReflectionClass($this->resolver);
		$method = $ref->getMethod('extractJsonPath');
		$method->setAccessible(true);

		$result = $method->invoke($this->resolver, $data, $path);
		$this->assertSame($expected, $result);
	}

	/**
	 * @return array<int,mixed>
	 */
	public static function provideJsonPathCases(): array {
		return [
			// Basic paths with $. prefix
			[['key' => 'value'], '$.key', 'value'],
			[['key' => 'value'], '$key', 'value'],

			// Basic paths without prefix
			[['key' => 'value'], 'key', 'value'],
			[['data' => ['rate' => 123.45]], 'data.rate', 123.45],

			// Nested paths
			[['data' => ['rates' => ['btc' => 50000]]], '$.data.rates.btc', 50000],
			[['data' => ['rates' => ['btc' => 50000]]], 'data.rates.btc', 50000],

			// Array index
			[['items' => [10, 20, 30]], '$.items[0]', 10],
			[['items' => [10, 20, 30]], 'items[1]', 20],

			// Complex nested
			[['result' => ['currencies' => ['usd' => 1.0, 'eur' => 0.85]]], '$.result.currencies.usd', 1.0],
			[['result' => ['currencies' => ['usd' => 1.0, 'eur' => 0.85]]], 'result.currencies.eur', 0.85],

			// Non-existent paths
			[['key' => 'value'], '$.missing', null],
			[['key' => 'value'], 'missing.nested', null],

			// Empty path returns whole data
			[['key' => 'value'], '$', ['key' => 'value']],
			[['key' => 'value'], '$.', ['key' => 'value']],
		];
	}

	/**
	 * Test token replacement
	 * @dataProvider provideTokenReplacementCases
	 */
	public function testReplaceTokens(string $text, string $base, string $expected): void {
		$ref = new ReflectionClass($this->resolver);
		$method = $ref->getMethod('replaceTokens');
		$method->setAccessible(true);

		$result = $method->invoke($this->resolver, $text, $base);
		$this->assertSame($expected, $result);
	}

	/**
	 * @return array<int,mixed>
	 */
	public static function provideTokenReplacementCases(): array {
		return [
			// Basic replacement
			['https://api.example.com/{base}', 'USD', 'https://api.example.com/usd'],
			['https://api.example.com/{base}', 'EUR', 'https://api.example.com/eur'],

			// Multiple occurrences
			['/{base}/rates/{base}', 'GBP', '/gbp/rates/gbp'],

			// JSON path with token
			['$.rates.{base}.btc', 'USD', '$.rates.usd.btc'],
			['data.{base}.price', 'EUR', 'data.eur.price'],

			// No token
			['https://api.example.com/price', 'USD', 'https://api.example.com/price'],
			['$.rate', 'EUR', '$.rate'],

			// Case conversion (base is lowercased)
			['https://api.example.com/{base}', 'usd', 'https://api.example.com/usd'],
		];
	}

	/**
	 * Test that {base} token in endpoint is detected
	 */
	public function testHasBaseTokenInEndpoint(): void {
		$customCurrency = new CustomCurrency();
		$customCurrency->setCode('BTC');
		$customCurrency->setApiEndpoint('https://api.example.com/rate?base={base}');
		$customCurrency->setJsonPath('$.rate');

		$ref = new ReflectionClass($this->resolver);
		$method = $ref->getMethod('fetchCustomCurrencyRate');
		$method->setAccessible(true);

		// Mock the API response
		$apiCache = $ref->getProperty('apiCache');
		$apiCache->setAccessible(true);
		$apiCache->setValue($this->resolver, [
			'https://api.example.com/rate?base=eur' => ['rate' => 50000],
		]);

		$result = $method->invoke($this->resolver, $customCurrency, 'eur');

		// Should not attempt USD conversion because {base} was found
		$this->assertIsArray($result);
		$this->assertArrayHasKey('rate', $result);
		$this->assertSame(50000.0, $result['rate']);
	}

	/**
	 * Test that {base} token in json_path is detected
	 */
	public function testHasBaseTokenInJsonPath(): void {
		$customCurrency = new CustomCurrency();
		$customCurrency->setCode('BTC');
		$customCurrency->setApiEndpoint('https://api.example.com/rates');
		$customCurrency->setJsonPath('$.{base}');

		$ref = new ReflectionClass($this->resolver);
		$method = $ref->getMethod('fetchCustomCurrencyRate');
		$method->setAccessible(true);

		// Mock the API response
		$apiCache = $ref->getProperty('apiCache');
		$apiCache->setAccessible(true);
		$apiCache->setValue($this->resolver, [
			'https://api.example.com/rates' => [
				'usd' => 50000,
				'eur' => 45000,
				'gbp' => 40000,
			],
		]);

		$result = $method->invoke($this->resolver, $customCurrency, 'eur');

		// Should extract from eur key without USD conversion
		$this->assertIsArray($result);
		$this->assertArrayHasKey('rate', $result);
		$this->assertSame(45000.0, $result['rate']);
	}

	/**
	 * Test USD conversion when no {base} token is present
	 */
	public function testUsdConversionWhenNoBaseToken(): void {
		$customCurrency = new CustomCurrency();
		$customCurrency->setCode('BTC');
		$customCurrency->setApiEndpoint('https://api.example.com/btc/price');
		$customCurrency->setJsonPath('$.usd');

		$ref = new ReflectionClass($this->resolver);
		$method = $ref->getMethod('fetchCustomCurrencyRate');
		$method->setAccessible(true);

		// Mock the API responses - fetchStandardRates caches by baseCurrency, not URL
		$apiCache = $ref->getProperty('apiCache');
		$apiCache->setAccessible(true);
		$apiCache->setValue($this->resolver, [
			'https://api.example.com/btc/price' => ['usd' => 50000],
			// Cache key is the baseCurrency string 'usd', not the URL
			'usd' => [
				'usd' => [
					'eur' => 0.85,
					'gbp' => 0.75,
				],
			],
		]);

		// Test with EUR base (should convert from USD)
		$result = $method->invoke($this->resolver, $customCurrency, 'eur');

		$this->assertIsArray($result);
		$this->assertArrayHasKey('rate', $result);

		// 50000 USD * 0.85 EUR/USD = 42500 EUR
		$expectedRate = 50000 * 0.85;
		$this->assertEqualsWithDelta($expectedRate, $result['rate'], 0.01);
	}

	/**
	 * Test no USD conversion when base is already USD
	 */
	public function testNoUsdConversionWhenBaseIsUsd(): void {
		$customCurrency = new CustomCurrency();
		$customCurrency->setCode('BTC');
		$customCurrency->setApiEndpoint('https://api.example.com/btc/price');
		$customCurrency->setJsonPath('$.usd');

		$ref = new ReflectionClass($this->resolver);
		$method = $ref->getMethod('fetchCustomCurrencyRate');
		$method->setAccessible(true);

		// Mock the API response
		$apiCache = $ref->getProperty('apiCache');
		$apiCache->setAccessible(true);
		$apiCache->setValue($this->resolver, [
			'https://api.example.com/btc/price' => ['usd' => 50000],
		]);

		// Test with USD base (should NOT convert)
		$result = $method->invoke($this->resolver, $customCurrency, 'usd');

		$this->assertIsArray($result);
		$this->assertArrayHasKey('rate', $result);
		$this->assertSame(50000.0, $result['rate']);
	}

	/**
	 * Test JSON path extraction with complex nested structure
	 */
	public function testComplexJsonPathExtraction(): void {
		$customCurrency = new CustomCurrency();
		$customCurrency->setCode('BTC');
		$customCurrency->setApiEndpoint('https://api.example.com/rates');
		$customCurrency->setJsonPath('data.rates.{base}.btc');

		$ref = new ReflectionClass($this->resolver);
		$method = $ref->getMethod('fetchCustomCurrencyRate');
		$method->setAccessible(true);

		// Mock the API response with nested structure
		$apiCache = $ref->getProperty('apiCache');
		$apiCache->setAccessible(true);
		$apiCache->setValue($this->resolver, [
			'https://api.example.com/rates' => [
				'data' => [
					'rates' => [
						'usd' => ['btc' => 0.00002],
						'eur' => ['btc' => 0.000022],
						'gbp' => ['btc' => 0.000025],
					],
				],
			],
		]);

		$result = $method->invoke($this->resolver, $customCurrency, 'eur');

		$this->assertIsArray($result);
		$this->assertArrayHasKey('rate', $result);
		$this->assertSame(0.000022, $result['rate']);
	}

	/**
	 * Test that extraction returns null for missing path
	 */
	public function testJsonPathExtractionReturnsNullForMissingPath(): void {
		$customCurrency = new CustomCurrency();
		$customCurrency->setCode('BTC');
		$customCurrency->setApiEndpoint('https://api.example.com/price');
		$customCurrency->setJsonPath('$.nonexistent.path');

		$ref = new ReflectionClass($this->resolver);
		$method = $ref->getMethod('fetchCustomCurrencyRate');
		$method->setAccessible(true);

		// Mock the API response
		$apiCache = $ref->getProperty('apiCache');
		$apiCache->setAccessible(true);
		$apiCache->setValue($this->resolver, [
			'https://api.example.com/price' => ['rate' => 50000],
		]);

		$result = $method->invoke($this->resolver, $customCurrency, 'usd');

		// Should return null when path doesn't exist
		$this->assertNull($result);
	}

	// ========== API Response Format Variations ==========

	/**
	 * Test that rate as string is converted to float
	 */
	public function testRateAsStringIsConvertedToFloat(): void {
		$customCurrency = new CustomCurrency();
		$customCurrency->setCode('BTC');
		$customCurrency->setApiEndpoint('https://api.example.com/price');
		$customCurrency->setJsonPath('$.rate');

		$ref = new ReflectionClass($this->resolver);
		$method = $ref->getMethod('fetchCustomCurrencyRate');
		$method->setAccessible(true);

		// Mock the API response with string rate
		$apiCache = $ref->getProperty('apiCache');
		$apiCache->setAccessible(true);
		$apiCache->setValue($this->resolver, [
			'https://api.example.com/price' => ['rate' => '50000.5'],
		]);

		$result = $method->invoke($this->resolver, $customCurrency, 'usd');

		$this->assertIsArray($result);
		$this->assertIsFloat($result['rate']);
		$this->assertSame(50000.5, $result['rate']);
	}

	/**
	 * Test handling of rate in scientific notation
	 */
	public function testScientificNotationRate(): void {
		$customCurrency = new CustomCurrency();
		$customCurrency->setCode('SHIB');
		$customCurrency->setApiEndpoint('https://api.example.com/shib');
		$customCurrency->setJsonPath('$.usd');

		$ref = new ReflectionClass($this->resolver);
		$method = $ref->getMethod('fetchCustomCurrencyRate');
		$method->setAccessible(true);

		// Mock response with very small number in scientific notation
		$apiCache = $ref->getProperty('apiCache');
		$apiCache->setAccessible(true);
		$apiCache->setValue($this->resolver, [
			'https://api.example.com/shib' => ['usd' => 1.23e-5],
		]);

		$result = $method->invoke($this->resolver, $customCurrency, 'usd');

		$this->assertIsArray($result);
		$this->assertSame(1.23e-5, $result['rate']);
	}

	/**
	 * Test that null rate returns null result
	 */
	public function testNullRateReturnsNull(): void {
		$customCurrency = new CustomCurrency();
		$customCurrency->setCode('BTC');
		$customCurrency->setApiEndpoint('https://api.example.com/price');
		$customCurrency->setJsonPath('$.rate');

		$ref = new ReflectionClass($this->resolver);
		$method = $ref->getMethod('fetchCustomCurrencyRate');
		$method->setAccessible(true);

		// Mock response with null rate
		$apiCache = $ref->getProperty('apiCache');
		$apiCache->setAccessible(true);
		$apiCache->setValue($this->resolver, [
			'https://api.example.com/price' => ['rate' => null],
		]);

		$result = $method->invoke($this->resolver, $customCurrency, 'usd');

		// extractJsonPath will return null, which should cause fetchCustomCurrencyRate to return null
		$this->assertNull($result);
	}

	/**
	 * Test empty JSON response
	 */
	public function testEmptyJsonObjectReturnsNull(): void {
		$customCurrency = new CustomCurrency();
		$customCurrency->setCode('BTC');
		$customCurrency->setApiEndpoint('https://api.example.com/empty');
		$customCurrency->setJsonPath('$.rate');

		$ref = new ReflectionClass($this->resolver);
		$method = $ref->getMethod('fetchCustomCurrencyRate');
		$method->setAccessible(true);

		// Mock empty response
		$apiCache = $ref->getProperty('apiCache');
		$apiCache->setAccessible(true);
		$apiCache->setValue($this->resolver, [
			'https://api.example.com/empty' => [],
		]);

		$result = $method->invoke($this->resolver, $customCurrency, 'usd');

		$this->assertNull($result);
	}

	/**
	 * Test nested object with null value
	 */
	public function testNestedNullValueReturnsNull(): void {
		$customCurrency = new CustomCurrency();
		$customCurrency->setCode('BTC');
		$customCurrency->setApiEndpoint('https://api.example.com/price');
		$customCurrency->setJsonPath('$.data.rate');

		$ref = new ReflectionClass($this->resolver);
		$method = $ref->getMethod('fetchCustomCurrencyRate');
		$method->setAccessible(true);

		// Mock response with nested null
		$apiCache = $ref->getProperty('apiCache');
		$apiCache->setAccessible(true);
		$apiCache->setValue($this->resolver, [
			'https://api.example.com/price' => ['data' => ['rate' => null]],
		]);

		$result = $method->invoke($this->resolver, $customCurrency, 'usd');

		$this->assertNull($result);
	}

	// ========== Conversion Edge Cases ==========

	/**
	 * Test USD conversion fails gracefully when conversion rate is missing
	 */
	public function testUsdConversionFailsGracefullyWhenRateMissing(): void {
		$customCurrency = new CustomCurrency();
		$customCurrency->setCode('BTC');
		$customCurrency->setApiEndpoint('https://api.example.com/btc');
		$customCurrency->setJsonPath('$.usd');

		$ref = new ReflectionClass($this->resolver);
		$method = $ref->getMethod('fetchCustomCurrencyRate');
		$method->setAccessible(true);

		// Mock the API responses - standard API missing the target currency
		$apiCache = $ref->getProperty('apiCache');
		$apiCache->setAccessible(true);
		$apiCache->setValue($this->resolver, [
			'https://api.example.com/btc' => ['usd' => 50000],
			// Cache key is baseCurrency 'usd', WITHOUT JPY rate
			'usd' => [
				'usd' => [
					'eur' => 0.85,
					'gbp' => 0.75,
					// JPY missing!
				],
			],
		]);

		// Test with JPY base (conversion should fail, return original rate)
		$result = $method->invoke($this->resolver, $customCurrency, 'jpy');

		$this->assertIsArray($result);
		// Should fallback to original USD rate
		$this->assertSame(50000.0, $result['rate']);
	}

	/**
	 * Test USD conversion handles zero rate gracefully
	 */
	public function testUsdConversionHandlesZeroRate(): void {
		$customCurrency = new CustomCurrency();
		$customCurrency->setCode('BTC');
		$customCurrency->setApiEndpoint('https://api.example.com/btc');
		$customCurrency->setJsonPath('$.usd');

		$ref = new ReflectionClass($this->resolver);
		$method = $ref->getMethod('fetchCustomCurrencyRate');
		$method->setAccessible(true);

		// Mock the API responses with zero conversion rate
		$apiCache = $ref->getProperty('apiCache');
		$apiCache->setAccessible(true);
		$apiCache->setValue($this->resolver, [
			'https://api.example.com/btc' => ['usd' => 50000],
			'usd' => [
				'usd' => [
					'xyz' => 0, // Zero rate!
				],
			],
		]);

		// Test with XYZ base - should handle division by zero
		$result = $method->invoke($this->resolver, $customCurrency, 'xyz');

		$this->assertIsArray($result);
		// Should return the original USD rate as fallback when conversion rate is zero
		$this->assertSame(50000.0, $result['rate']);
	}

	/**
	 * Test handling of very small decimal rates (crypto-like)
	 */
	public function testHandlesVerySmallDecimalRates(): void {
		$customCurrency = new CustomCurrency();
		$customCurrency->setCode('SHIB');
		$customCurrency->setApiEndpoint('https://api.example.com/shib');
		$customCurrency->setJsonPath('$.usd');

		$ref = new ReflectionClass($this->resolver);
		$method = $ref->getMethod('fetchCustomCurrencyRate');
		$method->setAccessible(true);

		// Very small rate
		$verySmallRate = 0.00000123456789;
		$apiCache = $ref->getProperty('apiCache');
		$apiCache->setAccessible(true);
		$apiCache->setValue($this->resolver, [
			'https://api.example.com/shib' => ['usd' => $verySmallRate],
		]);

		$result = $method->invoke($this->resolver, $customCurrency, 'usd');

		$this->assertIsArray($result);
		$this->assertEqualsWithDelta($verySmallRate, $result['rate'], 1e-15);
	}

	/**
	 * Test handling of very large rates
	 */
	public function testHandlesVeryLargeRates(): void {
		$customCurrency = new CustomCurrency();
		$customCurrency->setCode('BTC');
		$customCurrency->setApiEndpoint('https://api.example.com/btc/{base}'); // Add {base} to prevent USD conversion
		$customCurrency->setJsonPath('$.price');

		$ref = new ReflectionClass($this->resolver);
		$method = $ref->getMethod('fetchCustomCurrencyRate');
		$method->setAccessible(true);

		// Very large rate
		$veryLargeRate = 1234567890.123456;
		$apiCache = $ref->getProperty('apiCache');
		$apiCache->setAccessible(true);
		$apiCache->setValue($this->resolver, [
			'https://api.example.com/btc/vnd' => ['price' => $veryLargeRate],
		]);

		$result = $method->invoke($this->resolver, $customCurrency, 'vnd');

		$this->assertIsArray($result);
		$this->assertEqualsWithDelta($veryLargeRate, $result['rate'], 0.001);
	}

	/**
	 * Test negative rate (should still convert to float, even if unusual)
	 */
	public function testHandlesNegativeRate(): void {
		$customCurrency = new CustomCurrency();
		$customCurrency->setCode('TEST');
		$customCurrency->setApiEndpoint('https://api.example.com/test');
		$customCurrency->setJsonPath('$.rate');

		$ref = new ReflectionClass($this->resolver);
		$method = $ref->getMethod('fetchCustomCurrencyRate');
		$method->setAccessible(true);

		$apiCache = $ref->getProperty('apiCache');
		$apiCache->setAccessible(true);
		$apiCache->setValue($this->resolver, [
			'https://api.example.com/test' => ['rate' => -100.5],
		]);

		$result = $method->invoke($this->resolver, $customCurrency, 'usd');

		$this->assertIsArray($result);
		$this->assertSame(-100.5, $result['rate']);
	}

	/**
	 * Test integer rate is converted to float
	 */
	public function testIntegerRateConvertedToFloat(): void {
		$customCurrency = new CustomCurrency();
		$customCurrency->setCode('BTC');
		$customCurrency->setApiEndpoint('https://api.example.com/btc');
		$customCurrency->setJsonPath('$.usd');

		$ref = new ReflectionClass($this->resolver);
		$method = $ref->getMethod('fetchCustomCurrencyRate');
		$method->setAccessible(true);

		$apiCache = $ref->getProperty('apiCache');
		$apiCache->setAccessible(true);
		$apiCache->setValue($this->resolver, [
			'https://api.example.com/btc' => ['usd' => 50000], // Integer
		]);

		$result = $method->invoke($this->resolver, $customCurrency, 'usd');

		$this->assertIsArray($result);
		$this->assertIsFloat($result['rate']);
		$this->assertSame(50000.0, $result['rate']);
	}

	/**
	 * Test USD conversion with very large conversion rate (like Vietnamese Dong)
	 */
	public function testUsdConversionWithVeryLargeConversionRate(): void {
		$customCurrency = new CustomCurrency();
		$customCurrency->setCode('BTC');
		$customCurrency->setApiEndpoint('https://api.example.com/btc');
		$customCurrency->setJsonPath('$.usd');

		$ref = new ReflectionClass($this->resolver);
		$method = $ref->getMethod('fetchCustomCurrencyRate');
		$method->setAccessible(true);

		// Mock the API responses - using Vietnamese Dong as example of high-value currency
		$apiCache = $ref->getProperty('apiCache');
		$apiCache->setAccessible(true);
		$apiCache->setValue($this->resolver, [
			'https://api.example.com/btc' => ['usd' => 50000],
			'usd' => [
				'usd' => [
					'vnd' => 24000, // 1 USD = 24,000 VND
				],
			],
		]);

		$result = $method->invoke($this->resolver, $customCurrency, 'vnd');

		$this->assertIsArray($result);
		// 50000 USD * 24000 VND/USD = 1,200,000,000 VND
		$expectedRate = 50000 * 24000;
		$this->assertEqualsWithDelta($expectedRate, $result['rate'], 1);
	}

	/**
	 * Test that API key is properly included in cache key
	 */
	public function testApiKeyCacheIsolation(): void {
		$customCurrency1 = new CustomCurrency();
		$customCurrency1->setCode('BTC');
		$customCurrency1->setApiEndpoint('https://api.example.com/btc');
		$customCurrency1->setJsonPath('$.rate');
		$customCurrency1->setApiKey('key123');

		$customCurrency2 = new CustomCurrency();
		$customCurrency2->setCode('BTC');
		$customCurrency2->setApiEndpoint('https://api.example.com/btc');
		$customCurrency2->setJsonPath('$.rate');
		$customCurrency2->setApiKey('key456');

		$ref = new ReflectionClass($this->resolver);
		$method = $ref->getMethod('fetchCustomCurrencyRate');
		$method->setAccessible(true);

		// Mock responses with different cache keys (URL + hashed API key)
		$apiCache = $ref->getProperty('apiCache');
		$apiCache->setAccessible(true);
		$apiCache->setValue($this->resolver, [
			'https://api.example.com/btc:' . md5('key123') => ['rate' => 50000],
			'https://api.example.com/btc:' . md5('key456') => ['rate' => 51000],
		]);

		$result1 = $method->invoke($this->resolver, $customCurrency1, 'usd');
		$result2 = $method->invoke($this->resolver, $customCurrency2, 'usd');

		$this->assertIsArray($result1);
		$this->assertIsArray($result2);
		// Different API keys should use different cache entries
		$this->assertSame(50000.0, $result1['rate']);
		$this->assertSame(51000.0, $result2['rate']);
	}
}
