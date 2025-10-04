<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\AutoCurrency\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2Date20251005011020 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('autocurrency_custom')) {
			return null;
		}

		$table = $schema->createTable('autocurrency_custom');
		$table->addColumn('id', 'integer', [
			'autoincrement' => true,
			'notnull' => true,
		]);
		$table->addColumn('code', 'string', [
			'notnull' => true,
			'length' => 10,
		]);
		$table->addColumn('symbol', 'string', [
			'notnull' => false,
			'length' => 10,
		]);
		$table->addColumn('api_endpoint', 'string', [
			'notnull' => true,
			'length' => 255,
		]);
		$table->addColumn('api_key', 'string', [
			'notnull' => false,
			'length' => 255,
		]);
		$table->addColumn('json_path', 'string', [
			'notnull' => true,
			'length' => 255,
		]);
		$table->setPrimaryKey(['id']);

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}
}
