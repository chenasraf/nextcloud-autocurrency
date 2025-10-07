<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\AutoCurrency\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1Date20250925012201 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// no-op
	}

	/**
	 * Create table `autocurrency_rate_history`
	 *
	 * Columns:
	 *  - id: PK
	 *  - project_id: Cospend project identifier (string to stay compatible)
	 *  - project_name: Cospend project display name
	 *  - currency_name: quoted currency code (e.g. "eur", "usd")
	 *  - base_currency: base used when fetching (e.g. project's base, "ils")
	 *  - rate: numeric value (DECIMAL(20,10))
	 *  - fetched_at: timestamp the rate was fetched/calculated
	 *  - source: optional source string/URL/version
	 *  - currency_id: optional link to current currencies table (no FK to avoid cross-db issues)
	 *
	 * Indexes:
	 *  - UNIQUE(project_id, currency_name, fetched_at) to prevent duplicate samples
	 *  - INDEXes to speed up common queries
	 *
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$tableName = 'autocurrency_history';

		if ($schema->hasTable($tableName)) {
			// Nothing to do
			return null;
		}

		$table = $schema->createTable($tableName);

		$table->addColumn('id', 'bigint', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->setPrimaryKey(['id']);

		// Cospend project identifier (string to be safe; Cospend may use short hashes)
		$table->addColumn('project_id', 'string', [
			'length' => 64,
			'notnull' => true,
		]);

		// Cospend project display name
		$table->addColumn('project_name', 'string', [
			'length' => 64,
			'notnull' => true,
		]);

		// Quoted currency code (e.g. "usd", "eur") — lowercased in your service
		$table->addColumn('currency_name', 'string', [
			'length' => 12,
			'notnull' => true,
		]);

		// Base currency code used for the fetch (e.g. project's base)
		$table->addColumn('base_currency', 'string', [
			'length' => 12,
			'notnull' => true,
		]);

		// Exchange rate value (use generous precision/scale for FX)
		$table->addColumn('rate', 'decimal', [
			'precision' => 20,
			'scale' => 10,
			'notnull' => true,
		]);

		// When this rate was fetched/recorded
		$table->addColumn('fetched_at', 'datetime', [
			'notnull' => true,
		]);

		// Optional: source string/URL/version of feed
		$table->addColumn('source', 'string', [
			'length' => 255,
			'notnull' => false,
		]);

		// Optional: link to current currency row (if you want to store it)
		$table->addColumn('currency_id', 'bigint', [
			'unsigned' => true,
			'notnull' => false,
		]);

		// Indexes for typical lookups (by project/currency/time)
		$table->addIndex(['project_id'], 'ach_project_idx');
		$table->addIndex(['currency_name'], 'ach_currency_idx');
		$table->addIndex(['fetched_at'], 'ach_fetched_idx');

		// Prevent duplicates for a given project+currency at the same timestamp
		$table->addUniqueIndex(['project_id', 'currency_name', 'fetched_at'], 'ach_proj_cur_time_uniq');

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// no-op
	}
}
