<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\AutoCurrency\Command;

use OCA\AutoCurrency\Service\FetchCurrenciesService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FetchCurrencies extends Command {
	/**
	 * FetchCurrencies constructor.
	 */
	public function __construct(
		private FetchCurrenciesService $service,
		private LoggerInterface $logger,
	) {
		parent::__construct();
	}

	/**
	 *
	 */
	protected function configure(): void {
		parent::configure();
		$this->setName('autocurrency:fetch-currencies');
		$this->setDescription('Fetch currency exchange rates');
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @throws Exception
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$output->writeln('Fetching currency exchange rates...');
		$this->logger->info('Running FetchCurrencies command');

		try {
			$this->service->fetchCurrencyRates();
			$output->writeln('<info>Successfully fetched currency rates</info>');
			return 0;
		} catch (\Exception $e) {
			$this->logger->error('Failed to fetch currency rates: ' . $e->getMessage());
			$output->writeln('<error>Failed to fetch currency rates: ' . $e->getMessage() . '</error>');
			return 1;
		}
	}
}
