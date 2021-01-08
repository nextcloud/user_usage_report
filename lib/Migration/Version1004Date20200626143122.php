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

class Version1004Date20200626143122 extends SimpleMigrationStep {

	public const MIGRATION_SIZE = 500;

	/** @var IDBConnection */
	protected $connection;

	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
//		/** @var ISchemaWrapper $schema */
//		$schema = $schemaClosure();
//
//		if (!$schema->hasTable('usage_report_actions')) {
//			$table = $schema->createTable('usage_report_actions');
//			$table->addColumn('id', Types::BIGINT, [
//				'autoincrement' => true,
//				'notnull' => true,
//				'length' => 20,
//			]);
//			$table->addColumn('user_id', Types::STRING, [
//				'notnull' => true,
//				'length' => 64,
//			]);
//			$table->addColumn('action', Types::STRING, [
//				'notnull' => false,
//				'length' => 64,
//			]);
//			$table->addColumn('datetime', Types::DATETIME_MUTABLE, [
//				'notnull' => false,
//			]);
//			$table->addIndex(['user_id', 'action', 'datetime'], 'usage_report_uad');
//
//			$table->setPrimaryKey(['id']);
//		}
//
//		return $schema;
		return null;
	}

//	/**
//	 * @param IOutput $output
//	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
//	 * @param array $options
//	 */
//	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
//		$insert = $this->connection->getQueryBuilder();
//		$insert->insert('usage_report_actions')
//			->values([
//				'user_id' => $insert->createParameter('user'),
//				'action' => $insert->createParameter('action'),
//				'datetime' => $insert->createParameter('datetime'),
//			]);
//
//		$query = $this->connection->getQueryBuilder();
//		$query->select('*')
//			->from('usage_report')
//			->orderBy('timestamp', 'ASC')
//			->addOrderBy('user_id', 'ASC')
//			->addOrderBy('action', 'ASC')
//			->setMaxResults(self::MIGRATION_SIZE);
//
//		$offset = 0;
//		do {
//			$query->setFirstResult($offset);
//			$result = $query->execute();
//			$rows = $result->fetchAll();
//			$result->closeCursor();
//
//			if (empty($rows)) {
//				return;
//			}
//
//			foreach ($rows as $row) {
//				$date = new \DateTime($row['timestamp']);
//				$insert->setParameter('user', $row['user_id'])
//					->setParameter('action', $row['action'])
//					->setParameter('datetime', $date, IQueryBuilder::PARAM_DATE);
//				$insert->execute();
//			}
//
//			$offset += self::MIGRATION_SIZE;
//		} while (true);
//	}
}
