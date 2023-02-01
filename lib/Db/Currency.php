<?php
declare(strict_types=1);
// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\AutoCurrency\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/**
 * @method getId(): int
 * @method getName(): string
 * @method setName(string $name): void
 * @method getExchangeRate(): string
 * @method setExchangeRate(string $exchangeRate): void
 * @method getProjectId(): string
 * @method setProjectId(string $exchangeRate): void
 */
class Currency extends Entity implements JsonSerializable {
  protected string $name = '';
  protected string $exchangeRate = '';
  protected string $projectid = '';

  public function jsonSerialize(): array {
    return [
      'id' => $this->id,
      'name' => $this->name,
      'exchange_rate' => $this->exchangeRate,
      'projectid' => $this->projectid,
    ];
  }
}
