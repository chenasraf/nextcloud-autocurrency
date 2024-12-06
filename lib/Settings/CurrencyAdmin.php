<?php

namespace OCA\AutoCurrency\Settings;

use OCA\AutoCurrency\AppInfo;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\Settings\ISettings;
use OCP\Util;

class CurrencyAdmin implements ISettings {
	private IL10N $l;
	private IAppConfig $config;

	public function __construct(IAppConfig $config, IL10N $l) {
		$this->config = $config;
		$this->l = $l;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		Util::addScript(AppInfo\Application::APP_ID, 'autocurrency-main');
		Util::addStyle(AppInfo\Application::APP_ID, 'autocurrency-style');
		return new TemplateResponse(AppInfo\Application::APP_ID, 'settings', [], '');
	}

	public function getSection(): string {
		return AppInfo\Application::APP_ID;
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
