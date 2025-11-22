<?php

declare(strict_types=1);

namespace OCA\Cospend\Db;

// Define only if the real class isn't present (e.g. Cospend not installed in CI)
if (!class_exists('OCA\\Cospend\\Db\\Project')) {
	class Project {
		public function __construct(
			private string $id,
			private string $name,
			private string $currencyName,
		) {
		}
		public function getId(): string {
			return $this->id;
		}
		public function getName(): string {
			return $this->name;
		}
		public function getCurrencyName(): string {
			return $this->currencyName;
		}
	}
}


namespace Controller;

use DateTimeImmutable;
use OCA\AutoCurrency\AppInfo\Application as App;
use OCA\AutoCurrency\Controller\ApiController;
use OCA\AutoCurrency\Db\AutocurrencyRateHistory;
use OCA\AutoCurrency\Db\AutocurrencyRateHistoryMapper;
use OCA\AutoCurrency\Db\CospendProjectMapper;
use OCA\AutoCurrency\Db\Currency;
use OCA\AutoCurrency\Db\CurrencyMapper;
use OCA\AutoCurrency\Db\CustomCurrency;
use OCA\AutoCurrency\Db\CustomCurrencyMapper;
use OCA\AutoCurrency\Service\FetchCurrenciesService;
use OCA\Cospend\Db\Project;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ApiControllerTest extends TestCase {
	/** @var IRequest&MockObject */                      private $request;
	/** @var IAppConfig&MockObject */                    private $config;
	/** @var IL10N&MockObject */                         private $l10n;
	/** @var IUserSession&MockObject */                  private $userSession;
	/** @var CurrencyMapper&MockObject */                private $currencyMapper;
	/** @var CospendProjectMapper&MockObject */          private $projectMapper;
	/** @var AutocurrencyRateHistoryMapper&MockObject */ private $historyMapper;
	/** @var CustomCurrencyMapper&MockObject */          private $customCurrencyMapper;
	/** @var LoggerInterface&MockObject */               private $logger;
	/** @var FetchCurrenciesService */                   private $service;

	/** Helper: set a private property via reflection. */
	private function setPrivate(object $obj, string $prop, mixed $value): void {
		$rp = new \ReflectionProperty($obj, $prop);
		$rp->setAccessible(true);
		$rp->setValue($obj, $value);
	}

	/**
	 * Build controller with optional overrides:
	 *  - 'config', 'request', 'l10n', 'currencyMapper', 'projectMapper', 'historyMapper', 'customCurrencyMapper', 'logger'
	 *  - 'serviceMethods' => methods to partial-mock on FetchCurrenciesService
	 *  - 'symbols'        => fixture array for $service->symbols
	 * @param array<string,mixed> $opts
	 */
	private function buildController(array $opts = []): ApiController {
		$this->request = $opts['request'] ?? $this->createMock(IRequest::class);
		$this->config = $opts['config'] ?? $this->createMock(IAppConfig::class);
		$this->l10n = $opts['l10n'] ?? $this->createMock(IL10N::class);
		$this->logger = $opts['logger'] ?? $this->createMock(LoggerInterface::class);
		$this->userSession = $opts['userSession'] ?? $this->createMock(IUserSession::class);
		$this->currencyMapper = $opts['currencyMapper'] ?? $this->createMock(CurrencyMapper::class);
		$this->projectMapper = $opts['projectMapper'] ?? $this->createMock(CospendProjectMapper::class);
		$this->historyMapper = $opts['historyMapper'] ?? $this->createMock(AutocurrencyRateHistoryMapper::class);
		$this->customCurrencyMapper = $opts['customCurrencyMapper'] ?? $this->createMock(CustomCurrencyMapper::class);
		$this->logger = $opts['logger'] ?? $this->createMock(LoggerInterface::class);

		$this->customCurrencyMapper->method('findAll')->willReturn([]);

		if (!empty($opts['serviceMethods'])) {
			$this->service = $this->getMockBuilder(FetchCurrenciesService::class)
				->setConstructorArgs([$this->config, $this->currencyMapper, $this->projectMapper, $this->historyMapper, $this->customCurrencyMapper, $this->logger])
				->onlyMethods($opts['serviceMethods'])
				->getMock();
		} else {
			$this->service = new FetchCurrenciesService(
				$this->config,
				$this->currencyMapper,
				$this->projectMapper,
				$this->historyMapper,
				$this->customCurrencyMapper,
				$this->logger
			);
		}

		if (isset($opts['symbols'])) {
			// deterministic supported_currencies for tests
			$this->setPrivate($this->service, 'symbols', $opts['symbols']);
		}

		return new ApiController(
			App::APP_ID,
			$this->request,
			$this->logger,
			$this->config,
			$this->l10n,
			$this->userSession,
			$this->service,
			$this->currencyMapper,
			$this->projectMapper,
			$this->historyMapper,
			$this->customCurrencyMapper
		);
	}

	/** Fake a Cospend Project without the class present. */
	private function makeProject(string $id, string $name, string $base): object {
		$p = $this->getMockBuilder(\stdClass::class)
			->onlyMethods(['getId', 'getName', 'getCurrencyName'])
			->getMock();

		$p->method('getId')->willReturn($id);
		$p->method('getName')->willReturn($name);
		$p->method('getCurrencyName')->willReturn($base);

		return $p;
	}

	public function testGetSettings_EmptyLastUpdate_IntervalFromConfig(): void {
		$config = $this->createMock(IAppConfig::class);
		$config->expects($this->once())
			->method('getValueString')
			->with(App::APP_ID, 'last_update', '')
			->willReturn('');
		$config->expects($this->exactly(2))
			->method('getValueInt')
			->willReturnCallback(function ($appId, $key, $default) {
				if ($key === 'cron_interval') {
					return 12;
				}
				if ($key === 'retention_days') {
					return 30;
				}
				return $default;
			});

		$controller = $this->buildController([
			'config' => $config,
			'symbols' => [
				['code' => 'usd', 'symbol' => '$',  'name' => 'US Dollar'],
				['code' => 'eur', 'symbol' => '€',  'name' => 'Euro'],
				['code' => 'ils', 'symbol' => '₪',  'name' => 'Israeli New Shekel'],
			],
		]);

		$data = $controller->getSettings()->getData();

		$this->assertNull($data['last_update']);
		$this->assertSame(12, $data['interval']);
		$this->assertSame(30, $data['retention_days']);
	}

	public function testGetUserSettings_SupportedList(): void {
		$controller = $this->buildController([
			'symbols' => [
				['code' => 'usd', 'symbol' => '$',  'name' => 'US Dollar'],
				['code' => 'eur', 'symbol' => '€',  'name' => 'Euro'],
				['code' => 'ils', 'symbol' => '₪',  'name' => 'Israeli New Shekel'],
			],
		]);

		$data = $controller->getUserSettings()->getData();

		$this->assertSame(
			[
				['name' => 'US Dollar', 'code' => 'usd', 'symbol' => '$'],
				['name' => 'Euro', 'code' => 'eur', 'symbol' => '€'],
				['name' => 'Israeli New Shekel', 'code' => 'ils', 'symbol' => '₪'],
			],
			$data['supported_currencies']
		);
	}

	public function testGetUserSettings_IncludesCustomCurrencies(): void {
		$c1 = new CustomCurrency();
		$c1->setCode('BTC');
		$c1->setSymbol('₿');
		$c1->setApiEndpoint('https://api.example.com/btc');
		$c1->setApiKey('key123');
		$c1->setJsonPath('$.rate');

		$c2 = new CustomCurrency();
		$c2->setCode('ETH');
		$c2->setSymbol('');
		$c2->setApiEndpoint('https://api.example.com/eth');
		$c2->setApiKey('');
		$c2->setJsonPath('$.price');

		$customCurrencyMapper = $this->createMock(CustomCurrencyMapper::class);
		$customCurrencyMapper->expects($this->once())
			->method('findAll')
			->willReturn([$c1, $c2]);

		$controller = $this->buildController([
			'symbols' => [
				['code' => 'usd', 'symbol' => '$',  'name' => 'US Dollar'],
			],
			'customCurrencyMapper' => $customCurrencyMapper,
		]);

		$data = $controller->getUserSettings()->getData();

		$this->assertCount(3, $data['supported_currencies']);
		$this->assertSame(
			[
				['name' => 'US Dollar', 'code' => 'usd', 'symbol' => '$'],
				['name' => 'BTC', 'code' => 'BTC', 'symbol' => '₿'],
				['name' => 'ETH', 'code' => 'ETH', 'symbol' => 'ETH'],
			],
			$data['supported_currencies']
		);
	}

	public function testRunCron_CallsServiceAndReturnsOk(): void {
		$controller = $this->buildController(['serviceMethods' => ['fetchCurrencyRates']]);
		$this->service->expects($this->once())->method('fetchCurrencyRates');

		$resp = $controller->runCron();
		$this->assertSame(['status' => 'OK'], $resp->getData());
	}

	public function testUpdateSettings_WritesInterval(): void {
		$controller = $this->buildController();

		$this->config->expects($this->once())
			->method('setValueInt')
			->with(App::APP_ID, 'cron_interval', 6);

		$resp = $controller->updateSettings(['interval' => 6]);
		$this->assertSame(['status' => 'OK'], $resp->getData());
	}

	public function testUpdateSettings_WritesIntervalAndRetentionDays(): void {
		$controller = $this->buildController();

		$this->config->expects($this->exactly(2))
			->method('setValueInt')
			->willReturnCallback(function ($appId, $key, $value) {
				$this->assertSame(App::APP_ID, $appId);
				if ($key === 'cron_interval') {
					$this->assertSame(6, $value);
				} elseif ($key === 'retention_days') {
					$this->assertSame(60, $value);
				} else {
					$this->fail("Unexpected key: $key");
				}
				return true;
			});

		$resp = $controller->updateSettings(['interval' => 6, 'retention_days' => 60]);
		$this->assertSame(['status' => 'OK'], $resp->getData());
	}

	public function testUpdateSettings_RetentionDaysZeroMeansNoLimit(): void {
		$controller = $this->buildController();

		$this->config->expects($this->exactly(2))
			->method('setValueInt')
			->willReturnCallback(function ($appId, $key, $value) {
				if ($key === 'cron_interval') {
					$this->assertSame(24, $value);
				} elseif ($key === 'retention_days') {
					$this->assertSame(0, $value);
				}
				return true;
			});

		$resp = $controller->updateSettings(['interval' => 24, 'retention_days' => 0]);
		$this->assertSame(['status' => 'OK'], $resp->getData());
	}

	public function testUpdateSettings_NegativeRetentionDaysBecomesZero(): void {
		$controller = $this->buildController();

		$this->config->expects($this->exactly(2))
			->method('setValueInt')
			->willReturnCallback(function ($appId, $key, $value) {
				if ($key === 'cron_interval') {
					$this->assertSame(24, $value);
				} elseif ($key === 'retention_days') {
					// Negative values should be clamped to 0
					$this->assertSame(0, $value);
				}
				return true;
			});

		$resp = $controller->updateSettings(['interval' => 24, 'retention_days' => -5]);
		$this->assertSame(['status' => 'OK'], $resp->getData());
	}

	public function testGetProjects_MapsEntities(): void {
		$controller = $this->buildController();
		$user = $this->createConfiguredMock(\OCP\IUser::class, ['getUID' => 'u1']);
		$this->userSession->method('getUser')->willReturn($user);
		$p1 = $this->getMockBuilder(\OCA\Cospend\Db\Project::class)
			->disableOriginalConstructor()
			->onlyMethods(['getId', 'getName', 'getCurrencyName'])
			->getMock();
		$p1->method('getId')->willReturn('p1');
		$p1->method('getName')->willReturn('Trip');
		$p1->method('getCurrencyName')->willReturn('usd');

		$p2 = $this->getMockBuilder(\OCA\Cospend\Db\Project::class)
			->disableOriginalConstructor()
			->onlyMethods(['getId', 'getName', 'getCurrencyName'])
			->getMock();
		$p2->method('getId')->willReturn('p2');
		$p2->method('getName')->willReturn('');      // triggers fallback to id
		$p2->method('getCurrencyName')->willReturn('eur');

		$this->projectMapper->method('findAllByUser')->willReturn([$p1, $p2]);

		$cUSD = new Currency();
		$cILS = new Currency();
		$cEUR = new Currency();

		// Prefer real setters; if not present, set via reflection.
		if (method_exists($cUSD, 'setName')) {
			$cUSD->setName('USD');
		} else {
			(new \ReflectionProperty($cUSD, 'name'))->setAccessible(true);
			(new \ReflectionProperty($cUSD, 'name'))->setValue($cUSD, 'USD');
		}
		if (method_exists($cILS, 'setName')) {
			$cILS->setName('ILS');
		} else {
			$rp = new \ReflectionProperty($cILS, 'name');
			$rp->setAccessible(true);
			$rp->setValue($cILS, 'ILS');
		}
		if (method_exists($cEUR, 'setName')) {
			$cEUR->setName('EUR');
		} else {
			$rp = new \ReflectionProperty($cEUR, 'name');
			$rp->setAccessible(true);
			$rp->setValue($cEUR, 'EUR');
		}

		$this->currencyMapper->method('findAll')
			->willReturnCallback(function ($projectId) use ($cUSD, $cILS, $cEUR) {
				return $projectId === 'p1' ? [$cUSD, $cILS]
					 : ($projectId === 'p2' ? [$cEUR] : []);
			});

		$data = $controller->getProjects()->getData();

		$this->assertSame(
			[
				'projects' => [
					[
						'id' => 'p1',
						'name' => 'Trip',
						'baseCurrency' => 'usd',
						'currencies' => ['usd', 'ils'],
					],
					[
						'id' => 'p2',
						'name' => 'p2',
						'baseCurrency' => 'eur',
						'currencies' => ['eur'],
					],
				],
			],
			$data
		);
	}

	public function testGetProjects_IncludesCustomCurrencies(): void {
		$btcCustom = new CustomCurrency();
		$btcCustom->setCode('BTC');
		$btcCustom->setSymbol('₿');
		$btcCustom->setApiEndpoint('https://api.example.com/btc');
		$btcCustom->setApiKey('key123');
		$btcCustom->setJsonPath('$.rate');

		$customCurrencyMapper = $this->createMock(CustomCurrencyMapper::class);
		$customCurrencyMapper->method('findAll')
			->willReturn([$btcCustom]);

		$controller = $this->buildController(['customCurrencyMapper' => $customCurrencyMapper]);
		$user = $this->createConfiguredMock(\OCP\IUser::class, ['getUID' => 'u1']);
		$this->userSession->method('getUser')->willReturn($user);

		$p1 = $this->getMockBuilder(\OCA\Cospend\Db\Project::class)
			->disableOriginalConstructor()
			->onlyMethods(['getId', 'getName', 'getCurrencyName'])
			->getMock();
		$p1->method('getId')->willReturn('p1');
		$p1->method('getName')->willReturn('Crypto Trip');
		$p1->method('getCurrencyName')->willReturn('usd');

		$this->projectMapper->method('findAllByUser')->willReturn([$p1]);

		$cUSD = new Currency();
		$cBTC = new Currency();

		// Set up currencies
		if (method_exists($cUSD, 'setName')) {
			$cUSD->setName('USD');
			$cBTC->setName('BTC');
		} else {
			$rp = new \ReflectionProperty($cUSD, 'name');
			$rp->setAccessible(true);
			$rp->setValue($cUSD, 'USD');

			$rp = new \ReflectionProperty($cBTC, 'name');
			$rp->setAccessible(true);
			$rp->setValue($cBTC, 'BTC');
		}

		$this->currencyMapper->method('findAll')
			->willReturn([$cUSD, $cBTC]);

		$data = $controller->getProjects()->getData();

		$this->assertSame(
			[
				'projects' => [
					[
						'id' => 'p1',
						'name' => 'Crypto Trip',
						'baseCurrency' => 'usd',
						'currencies' => ['usd', 'btc'],
					],
				],
			],
			$data
		);
	}

	public function testGetHistory_EndOfDayTo_AndMapping(): void {
		$controller = $this->buildController();

		// Project with base 'usd'
		$project = $this->getMockBuilder(\OCA\Cospend\Db\Project::class)
			->disableOriginalConstructor()
			->onlyMethods(['getCurrencyName'])
			->getMock();
		$project->method('getCurrencyName')->willReturn('usd');

		$this->projectMapper->method('find')->with('p1')->willReturn($project);

		// Use a REAL entity; do not mock final getters
		$row = new AutocurrencyRateHistory();

		// Prefer real setters; else set fields via reflection.
		if (method_exists($row, 'setFetchedAt')) {
			$row->setFetchedAt(new DateTimeImmutable('2025-09-25T12:34:56+00:00'));
		} else {
			$rp = new \ReflectionProperty($row, 'fetchedAt');
			$rp->setAccessible(true);
			$rp->setValue($row, new DateTimeImmutable('2025-09-25T12:34:56+00:00'));
		}
		if (method_exists($row, 'setRate')) {
			$row->setRate('1.2345');
		} else {
			$rp = new \ReflectionProperty($row, 'rate');
			$rp->setAccessible(true);
			$rp->setValue($row, '1.2345');
		}

		if (method_exists($row, 'setCurrencyName')) {
			$row->setCurrencyName('eur');
		} else {
			$rp = new \ReflectionProperty($row, 'currencyName');
			$rp->setAccessible(true);
			$rp->setValue($row, 'eur');
		}

		if (method_exists($row, 'setSource')) {
			$row->setSource('ECB');
		} else {
			$rp = new \ReflectionProperty($row, 'source');
			$rp->setAccessible(true);
			$rp->setValue($row, 'ECB');
		}

		$this->historyMapper->expects($this->once())
			->method('findByProjectAndBase')
			->with(
				'p1',
				'usd',
				'eur',
				$this->isInstanceOf(DateTimeImmutable::class),
				$this->callback(fn ($to) => $to instanceof DateTimeImmutable && $to->format('H:i:s') === '23:59:59'),
				100,
				0,
				'ASC'
			)
			->willReturn([$row]);

		$resp = $controller->getHistory(
			projectId: 'p1',
			currency: 'eur',
			from: '2025-09-20T00:00:00Z',
			to: '2025-09-25', // date-only → end of day
			limit: 100,
			offset: 0
		);

		$data = $resp->getData();

		$this->assertSame('p1', $data['projectId']);
		$this->assertSame('usd', $data['baseCurrency']);
		$this->assertCount(1, $data['points']);
		$this->assertSame('1.2345', $data['points'][0]['rate']);
		$this->assertSame('eur', $data['points'][0]['currencyName']);
		$this->assertSame('ECB', $data['points'][0]['source']);
		$this->assertSame('2025-09-25T12:34:56+00:00', $data['points'][0]['fetchedAt']);
	}

	public function testGetHistory_BadRequest_WhenMissingProjectId(): void {
		$controller = $this->buildController();

		$resp = $controller->getHistory(projectId: '');
		$this->assertSame(400, $resp->getStatus());
		$this->assertSame(['error' => 'projectId is required'], $resp->getData());
	}

	public function testGetCustomCurrencies_ReturnsAllCurrencies(): void {
		$c1 = new CustomCurrency();
		$c1->setCode('BTC');
		$c1->setSymbol('₿');
		$c1->setApiEndpoint('https://api.example.com/btc');
		$c1->setApiKey('key123');
		$c1->setJsonPath('$.rate');

		$c2 = new CustomCurrency();
		$c2->setCode('ETH');
		$c2->setSymbol('Ξ');
		$c2->setApiEndpoint('https://api.example.com/eth');
		$c2->setApiKey('');
		$c2->setJsonPath('$.price');

		$customCurrencyMapper = $this->createMock(CustomCurrencyMapper::class);
		$customCurrencyMapper->expects($this->once())
			->method('findAll')
			->willReturn([$c1, $c2]);

		$controller = $this->buildController(['customCurrencyMapper' => $customCurrencyMapper]);

		$resp = $controller->getCustomCurrencies();
		$data = $resp->getData();

		$this->assertArrayHasKey('currencies', $data);
		$currencies = $data['currencies'];
		$this->assertCount(2, $currencies);

		// Verify the entities are serialized
		$this->assertSame('BTC', $currencies[0]['code']);
		$this->assertSame('₿', $currencies[0]['symbol']);
		$this->assertSame('ETH', $currencies[1]['code']);
	}

	public function testCreateCustomCurrency_Success_WithAllFields(): void {
		$controller = $this->buildController();

		$inputData = [
			'code' => 'BTC',
			'symbol' => '₿',
			'api_endpoint' => 'https://api.example.com/btc',
			'api_key' => 'secret123',
			'json_path' => '$.rate',
		];

		$this->customCurrencyMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function ($currency) {
				$this->assertInstanceOf(CustomCurrency::class, $currency);
				$this->assertSame('BTC', $currency->getCode());
				$this->assertSame('₿', $currency->getSymbol());
				$this->assertSame('https://api.example.com/btc', $currency->getApiEndpoint());
				$this->assertSame('secret123', $currency->getApiKey());
				$this->assertSame('$.rate', $currency->getJsonPath());
				return $currency;
			});

		$resp = $controller->createCustomCurrency($inputData);
		$this->assertSame(201, $resp->getStatus());
	}

	public function testCreateCustomCurrency_Success_WithoutApiKey(): void {
		$controller = $this->buildController();

		$inputData = [
			'code' => 'ETH',
			'symbol' => 'Ξ',
			'api_endpoint' => 'https://api.example.com/eth',
			'json_path' => '$.price',
		];

		$this->customCurrencyMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function ($currency) {
				$this->assertSame('', $currency->getApiKey());
				return $currency;
			});

		$resp = $controller->createCustomCurrency($inputData);
		$this->assertSame(201, $resp->getStatus());
	}

	public function testCreateCustomCurrency_BadRequest_MissingCode(): void {
		$controller = $this->buildController();

		$inputData = [
			'symbol' => '₿',
			'api_endpoint' => 'https://api.example.com/btc',
			'json_path' => '$.rate',
		];

		$resp = $controller->createCustomCurrency($inputData);
		$this->assertSame(400, $resp->getStatus());
		$this->assertArrayHasKey('error', $resp->getData());
		$this->assertStringContainsString('code', $resp->getData()['error']);
	}

	public function testCreateCustomCurrency_Success_WithoutSymbol(): void {
		$controller = $this->buildController();

		$inputData = [
			'code' => 'BTC',
			'api_endpoint' => 'https://api.example.com/btc',
			'json_path' => '$.rate',
		];

		$this->customCurrencyMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function ($currency) {
				$this->assertSame('', $currency->getSymbol());
				return $currency;
			});

		$resp = $controller->createCustomCurrency($inputData);
		$this->assertSame(201, $resp->getStatus());
	}

	public function testCreateCustomCurrency_InternalError_OnException(): void {
		$controller = $this->buildController();

		$inputData = [
			'code' => 'BTC',
			'symbol' => '₿',
			'api_endpoint' => 'https://api.example.com/btc',
			'json_path' => '$.rate',
		];

		$this->customCurrencyMapper->expects($this->once())
			->method('insert')
			->willThrowException(new \Exception('Database error'));

		$this->logger->expects($this->once())
			->method('error')
			->with($this->stringContains('Failed to create custom currency'));

		$resp = $controller->createCustomCurrency($inputData);
		$this->assertSame(500, $resp->getStatus());
		$this->assertArrayHasKey('error', $resp->getData());
	}

	public function testDeleteCustomCurrency_Success(): void {
		$controller = $this->buildController();

		$currency = new CustomCurrency();
		$currency->setCode('BTC');

		$this->customCurrencyMapper->expects($this->once())
			->method('find')
			->with('1')
			->willReturn($currency);

		$this->customCurrencyMapper->expects($this->once())
			->method('delete')
			->with($currency);

		$resp = $controller->deleteCustomCurrency(1);
		$this->assertSame(200, $resp->getStatus());
		$this->assertSame(['status' => 'OK'], $resp->getData());
	}

	public function testDeleteCustomCurrency_InternalError_OnException(): void {
		$controller = $this->buildController();

		$this->customCurrencyMapper->expects($this->once())
			->method('find')
			->with('1')
			->willThrowException(new \Exception('Not found'));

		$this->logger->expects($this->once())
			->method('error')
			->with($this->stringContains('Failed to delete custom currency'));

		$resp = $controller->deleteCustomCurrency(1);
		$this->assertSame(500, $resp->getStatus());
		$this->assertArrayHasKey('error', $resp->getData());
	}

	public function testUpdateCustomCurrency_Success_AllFields(): void {
		$controller = $this->buildController();

		$currency = new CustomCurrency();
		$currency->setCode('BTC');
		$currency->setSymbol('₿');
		$currency->setApiEndpoint('https://api.example.com/btc');
		$currency->setApiKey('oldkey');
		$currency->setJsonPath('$.old');

		$this->customCurrencyMapper->expects($this->once())
			->method('find')
			->with('1')
			->willReturn($currency);

		$inputData = [
			'code' => 'ETH',
			'symbol' => 'Ξ',
			'api_endpoint' => 'https://api.example.com/eth',
			'api_key' => 'newkey',
			'json_path' => '$.new',
		];

		$this->customCurrencyMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($c) {
				$this->assertSame('ETH', $c->getCode());
				$this->assertSame('Ξ', $c->getSymbol());
				$this->assertSame('https://api.example.com/eth', $c->getApiEndpoint());
				$this->assertSame('newkey', $c->getApiKey());
				$this->assertSame('$.new', $c->getJsonPath());
				return $c;
			});

		$resp = $controller->updateCustomCurrency(1, $inputData);
		$this->assertSame(200, $resp->getStatus());
	}

	public function testUpdateCustomCurrency_Success_PartialUpdate(): void {
		$controller = $this->buildController();

		$currency = new CustomCurrency();
		$currency->setCode('BTC');
		$currency->setSymbol('₿');
		$currency->setApiEndpoint('https://api.example.com/btc');
		$currency->setApiKey('key123');
		$currency->setJsonPath('$.rate');

		$this->customCurrencyMapper->expects($this->once())
			->method('find')
			->with('1')
			->willReturn($currency);

		$inputData = [
			'code' => 'BTCUSD',
		];

		$this->customCurrencyMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($c) {
				$this->assertSame('BTCUSD', $c->getCode());
				$this->assertSame('₿', $c->getSymbol()); // unchanged
				$this->assertSame('https://api.example.com/btc', $c->getApiEndpoint()); // unchanged
				$this->assertSame('key123', $c->getApiKey()); // unchanged
				$this->assertSame('$.rate', $c->getJsonPath()); // unchanged
				return $c;
			});

		$resp = $controller->updateCustomCurrency(1, $inputData);
		$this->assertSame(200, $resp->getStatus());
	}

	public function testUpdateCustomCurrency_Success_ClearApiKey(): void {
		$controller = $this->buildController();

		$currency = new CustomCurrency();
		$currency->setCode('BTC');
		$currency->setSymbol('₿');
		$currency->setApiEndpoint('https://api.example.com/btc');
		$currency->setApiKey('oldkey');
		$currency->setJsonPath('$.rate');

		$this->customCurrencyMapper->expects($this->once())
			->method('find')
			->with('1')
			->willReturn($currency);

		$inputData = [
			'api_key' => null,
		];

		$this->customCurrencyMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($c) {
				$this->assertSame('', $c->getApiKey());
				return $c;
			});

		$resp = $controller->updateCustomCurrency(1, $inputData);
		$this->assertSame(200, $resp->getStatus());
	}

	public function testUpdateCustomCurrency_InternalError_OnException(): void {
		$controller = $this->buildController();

		$this->customCurrencyMapper->expects($this->once())
			->method('find')
			->with('1')
			->willThrowException(new \Exception('Not found'));

		$this->logger->expects($this->once())
			->method('error')
			->with($this->stringContains('Failed to update custom currency'));

		$resp = $controller->updateCustomCurrency(1, ['code' => 'ETH']);
		$this->assertSame(500, $resp->getStatus());
		$this->assertArrayHasKey('error', $resp->getData());
	}
}
