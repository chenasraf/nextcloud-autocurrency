<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: Chen Asraf <casraf@pm.me>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AutoCurrency\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class {{pascalCase name}} extends Command {
	/**
	 * {{pascalCase name}} constructor.
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 *
	 */
	protected function configure(): void {
		parent::configure();
		$this->setName('autocurrency:{{kebabCase name}}');
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @throws Exception
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		return 0;
	}
}
