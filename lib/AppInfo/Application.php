<?php
declare(strict_types=1);
// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\AutoCurrency\AppInfo;

use OCP\AppFramework\App;

class Application extends App {
	public const APP_ID = 'autocurrency';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}
}
