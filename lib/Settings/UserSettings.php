<?php

namespace OCA\AutoCurrency\Settings;

use OCA\AutoCurrency\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\Settings\ISettings;
use OCP\Util;

class UserSettings implements ISettings {
	public function __construct(
		private IAppConfig $config,
		private IL10N $l,
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		Util::addScript(Application::APP_ID, Application::JS_DIR . '/autocurrency-user');
		Util::addStyle(Application::APP_ID, Application::CSS_DIR . '/autocurrency-style');
		return new TemplateResponse(Application::APP_ID, 'settings', [], '');
	}

	public function getSection(): string {
		return Application::APP_ID;
	}

	/**
	 * @return int whether the form should be rather on the top or bottom of
	 *             the admin section. The forms are arranged in ascending order of the
	 *             priority values. It is required to return a value between 0 and 100.
	 *
	 * E.g.: 70
	 */
	public function getPriority(): int {
		return 10;
	}
}
