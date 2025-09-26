<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\AutoCurrency\Tests\Service;

use OCA\AutoCurrency\Db\AutocurrencyRateHistoryMapper;
use OCA\AutoCurrency\Db\CospendProjectMapper;
use OCA\AutoCurrency\Db\CurrencyMapper;
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
		$logger = $this->createMock(LoggerInterface::class);

		$this->resolver = new FetchCurrenciesService(
			$config,
			$currencyMapper,
			$projectMapper,
			$historyMapper,
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
}
