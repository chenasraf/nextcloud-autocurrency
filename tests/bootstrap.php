<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tests/bootstrap.php';

\OC_App::loadApp(OCA\AutoCurrency\AppInfo\Application::APP_ID);
OC_Hook::clear();
