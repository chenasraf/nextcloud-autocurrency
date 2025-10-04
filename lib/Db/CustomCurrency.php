<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\AutoCurrency\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getCode()
 * @method void setCode(string $value)
 * @method string getSymbol()
 * @method void setSymbol(string $value)
 * @method string getApiEndpoint()
 * @method void setApiEndpoint(string $value)
 * @method string getApiKey()
 * @method void setApiKey(string $value)
 * @method string getJsonPath()
 * @method void setJsonPath(string $value)
 */
class CustomCurrency extends Entity implements JsonSerializable {
	protected $code = '';
	protected $symbol = '';
	protected $apiEndpoint = '';
	protected $apiKey = '';
	protected $jsonPath = '';

	public function __construct() {
		$this->addType('code', 'string');
		$this->addType('symbol', 'string');
		$this->addType('apiEndpoint', 'string');
		$this->addType('apiKey', 'string');
		$this->addType('jsonPath', 'string');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'code' => $this->getCode(),
			'symbol' => $this->getSymbol(),
			'api_endpoint' => $this->getApiEndpoint(),
			'api_key' => $this->getApiKey(),
			'json_path' => $this->getJsonPath(),
		];
	}
}
