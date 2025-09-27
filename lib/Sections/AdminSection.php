<?php

namespace OCA\AutoCurrency\Sections;

use OCA\AutoCurrency\AppInfo;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class AdminSection implements IIconSection {
	public function __construct(
		private IL10N $l,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getIcon(): string {
		return $this->urlGenerator->imagePath(AppInfo\Application::APP_ID, 'app-dark.svg');
	}

	public function getID(): string {
		return AppInfo\Application::APP_ID;
	}

	public function getName(): string {
		return $this->l->t('Auto Currency');
	}

	public function getPriority(): int {
		return 80;
	}
}
