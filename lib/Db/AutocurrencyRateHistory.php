<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\AutoCurrency\Db;

use DateTimeInterface;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method int|null getId()
 * @method void setId(int $id)
 *
 * @method string getProjectId()
 * @method void setProjectId(string $value)
 *
 * @method string getProjectName()
 * @method void setProjectName(string $value)
 *
 * @method string getCurrencyName()
 * @method void setCurrencyName(string $value)
 *
 * @method string getBaseCurrency()
 * @method void setBaseCurrency(string $value)
 *
 * @method string getRate()
 * @method void setRate(string $value)
 *
 * @method DateTimeInterface getFetchedAt()
 * @method void setFetchedAt(DateTimeInterface $value)
 *
 * @method string|null getSource()
 * @method void setSource(?string $value)
 *
 * @method int|null getCurrencyId()
 * @method void setCurrencyId(?int $value)
 */
class AutocurrencyRateHistory extends Entity implements JsonSerializable {
	protected $projectId = '';
	protected $projectName = '';
	protected $currencyName = '';
	protected $baseCurrency = '';
	protected $rate = '';
	protected $fetchedAt = null;
	protected $source = null;
	protected $currencyId = null;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('projectId', 'string');
		$this->addType('projectName', 'string');
		$this->addType('currencyName', 'string');
		$this->addType('baseCurrency', 'string');
		$this->addType('rate', 'string');
		$this->addType('fetchedAt', 'datetime');
		$this->addType('source', 'string');
		$this->addType('currencyId', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'projectId' => $this->getProjectId(),
			'projectName' => $this->getProjectName(),
			'currencyName' => $this->getCurrencyName(),
			'baseCurrency' => $this->getBaseCurrency(),
			'rate' => $this->getRate(),
			'fetchedAt' => $this->getFetchedAt() ? $this->getFetchedAt()->format(DATE_ATOM) : null,
			'source' => $this->getSource(),
			'currencyId' => $this->getCurrencyId(),
		];
	}
}
