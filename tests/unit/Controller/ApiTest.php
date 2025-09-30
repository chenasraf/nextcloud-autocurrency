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
	 *  - 'config', 'request', 'l10n', 'currencyMapper', 'projectMapper', 'historyMapper', 'logger'
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
		$this->logger = $opts['logger'] ?? $this->createMock(LoggerInterface::class);

		if (!empty($opts['serviceMethods'])) {
			$this->service = $this->getMockBuilder(FetchCurrenciesService::class)
				->setConstructorArgs([$this->config, $this->currencyMapper, $this->projectMapper, $this->historyMapper, $this->logger])
				->onlyMethods($opts['serviceMethods'])
				->getMock();
		} else {
			$this->service = new FetchCurrenciesService(
				$this->config,
				$this->currencyMapper,
				$this->projectMapper,
				$this->historyMapper,
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
			$this->historyMapper
		);
	}

	/** Fake a Cospend Project without the class present. */
	private function makeProject(string $id, string $name, string $base): object {
		$p = $this->getMockBuilder(\stdClass::class)
			->addMethods(['getId', 'getName', 'getCurrencyName'])
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
		$config->expects($this->once())
			->method('getValueInt')
			->with(App::APP_ID, 'cron_interval', 24)
			->willReturn(12);

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

	public function testGetProjects_MapsEntities(): void {
		$controller = $this->buildController();
		$user = $this->createConfiguredMock(\OCP\IUser::class, ['getUID' => 'u1']);
		$this->userSession->method('getUser')->willReturn($user);
		$p1 = $this->getMockBuilder(\OCA\Cospend\Db\Project::class)
			->disableOriginalConstructor()
			->addMethods(['getId', 'getName', 'getCurrencyName'])
			->getMock();
		$p1->method('getId')->willReturn('p1');
		$p1->method('getName')->willReturn('Trip');
		$p1->method('getCurrencyName')->willReturn('usd');

		$p2 = $this->getMockBuilder(\OCA\Cospend\Db\Project::class)
			->disableOriginalConstructor()
			->addMethods(['getId', 'getName', 'getCurrencyName'])
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

	public function testGetHistory_EndOfDayTo_AndMapping(): void {
		$controller = $this->buildController();

		// Project with base 'usd'
		$project = $this->getMockBuilder(\OCA\Cospend\Db\Project::class)
			->disableOriginalConstructor()
			->addMethods(['getCurrencyName'])
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
}
