<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\AutoCurrency\Db;

use DateTimeInterface;
use OCA\AutoCurrency\AppInfo\Application;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * Mapper for autocurrency_rate_history
 *
 * Table columns (snake_case) map to entity props (camelCase):
 *  - id               -> id
 *  - project_id       -> projectId
 *  - project_name     -> projectName
 *  - currency_name    -> currencyName
 *  - base_currency    -> baseCurrency
 *  - rate             -> rate
 *  - fetched_at       -> fetchedAt
 *  - source           -> source
 *  - currency_id      -> currencyId
 *
 * @extends QBMapper<Entity>
 */
class AutocurrencyRateHistoryMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, Application::tableName('history'), AutocurrencyRateHistory::class);
	}

	/**
	 * Fetch history points for a given project and base currency.
	 *
	 * @param string $projectId
	 * @param string $baseCurrency Lowercase code (e.g. "ils", "usd")
	 * @param string|null $currencyName Optional quoted currency code filter (e.g. "eur")
	 * @param DateTimeInterface|null $from Optional >= start time
	 * @param DateTimeInterface|null $to Optional <= end time
	 * @param int $limit Optional limit (0 = no limit)
	 * @param int $offset Optional offset
	 * @param string $order 'ASC' or 'DESC' (default ASC by fetched_at)
	 * @return AutocurrencyRateHistory[]
	 */
	public function findByProjectAndBase(
		string $projectId,
		string $baseCurrency,
		?string $currencyName = null,
		?DateTimeInterface $from = null,
		?DateTimeInterface $to = null,
		int $limit = 0,
		int $offset = 0,
		string $order = 'ASC',
	): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->andX(
					$qb->expr()->eq('project_id', $qb->createNamedParameter($projectId)),
					$qb->expr()->eq('base_currency', $qb->createNamedParameter($baseCurrency))
				)
			)
			->orderBy('fetched_at', strtoupper($order) === 'DESC' ? 'DESC' : 'ASC');

		if ($currencyName !== null && $currencyName !== '') {
			$qb->andWhere(
				$qb->expr()->eq('currency_name', $qb->createNamedParameter($currencyName))
			);
		}

		if ($from !== null) {
			$qb->andWhere(
				$qb->expr()->gte('fetched_at', $qb->createNamedParameter($from->format('Y-m-d H:i:s')))
			);
		}
		if ($to !== null) {
			$qb->andWhere(
				$qb->expr()->lte('fetched_at', $qb->createNamedParameter($to->format('Y-m-d H:i:s')))
			);
		}

		if ($limit > 0) {
			$qb->setMaxResults($limit);
		}
		if ($offset > 0) {
			$qb->setFirstResult($offset);
		}

		return $this->findEntities($qb);
	}
}
