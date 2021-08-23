<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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
			->selectAlias($query->createFunction('COUNT(*)'),'num_actions')
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
