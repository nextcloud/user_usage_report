<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserUsageReport\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1004Date20200630071803 extends SimpleMigrationStep {
	public const MIGRATION_SIZE = 2000;

	/** @var IDBConnection */
	protected $connection;

	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('usage_report')) {
			$sourceTable = 'usage_report';
		} elseif ($schema->hasTable('usage_report_actions')) {
			$sourceTable = 'usage_report_actions';
		} else {
			return;
		}

		$insert = $this->connection->getQueryBuilder();
		$insert->insert('preferences')
			->values([
				'userid' => $insert->createParameter('userid'),
				'appid' => $insert->createParameter('appid'),
				'configkey' => $insert->createParameter('configkey'),
				'configvalue' => $insert->createParameter('configvalue'),
			]);

		$query = $this->connection->getQueryBuilder();
		$query->select(['user_id', 'action'])
			->selectAlias($query->createFunction('COUNT(*)'), 'num_actions')
			->from($sourceTable)
			->groupBy('user_id')
			->addGroupBy('action')
			->orderBy('user_id', 'ASC')
			->addOrderBy('action', 'ASC')
			->setMaxResults(self::MIGRATION_SIZE);

		$offset = 0;
		do {
			$query->setFirstResult($offset);
			$result = $query->execute();
			$rows = $result->fetchAll();
			$result->closeCursor();

			if (empty($rows)) {
				return;
			}

			foreach ($rows as $row) {
				$insert->setParameter('userid', $row['user_id'])
					->setParameter('appid', 'user_usage_report')
					->setParameter('configkey', $row['action'])
					->setParameter('configvalue', $row['num_actions']);
				$insert->execute();
			}

			$offset += self::MIGRATION_SIZE;
		} while (true);
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('usage_report_actions')) {
			$schema->dropTable('usage_report_actions');
		}

		if ($schema->hasTable('usage_report')) {
			$schema->dropTable('usage_report');
		}

		return $schema;
	}
}
