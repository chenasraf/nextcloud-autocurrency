<?php

namespace OCA\AutoCurrency\Sections;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class CurrencyAdmin implements IIconSection {
	private IL10N $l;
	private IURLGenerator $urlGenerator;

	public function __construct(IL10N $l, IURLGenerator $urlGenerator) {
		$this->l = $l;
		$this->urlGenerator = $urlGenerator;
	}

	public function getIcon(): string {
		return $this->urlGenerator->imagePath('autocurrency', 'app-dark.svg');
	}

	public function getID(): string {
		return 'autocurrency';
	}

	public function getName(): string {
		return $this->l->t('Auto Currency');
	}

	public function getPriority(): int {
		return 80;
	}
}
