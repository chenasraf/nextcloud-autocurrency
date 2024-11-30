<?php
namespace OCA\AutoCurrency\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\Settings\ISettings;
use OCP\Util;
use OCA\AutoCurrency\AppInfo;

class CurrencyAdmin implements ISettings {
    private IL10N $l;
    private IConfig $config;

    public function __construct(IConfig $config, IL10N $l) {
        $this->config = $config;
        $this->l = $l;
    }

    /**
     * @return TemplateResponse
     */
    public function getForm() {
        $parameters = [
            // 'mySetting' => $this->config->getSystemValue('my_notes_setting', true),
            'mySetting' => 'Hello World!',
        ];

        Util::addScript(AppInfo\Application::APP_ID, 'autocurrency-main');
        Util::addStyle(AppInfo\Application::APP_ID, 'autocurrency-style');

        return new TemplateResponse('autocurrency', 'settings', $parameters, '');
    }

    public function getSection() {
        return 'autocurrency'; // Name of the previously created section.
    }

    /**
     * @return int whether the form should be rather on the top or bottom of
     * the admin section. The forms are arranged in ascending order of the
     * priority values. It is required to return a value between 0 and 100.
     *
     * E.g.: 70
     */
    public function getPriority() {
        return 10;
    }
}
