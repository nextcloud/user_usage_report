<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
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

namespace OCA\UserUsageReport;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserSession;

class Listener {

	/** @var IUserSession */
	protected $userSession;

	/** @var IDBConnection */
	protected $connection;

	/** @var IQueryBuilder */
	protected $insert;

	/** @var IQueryBuilder */
	protected $update;

	public function __construct(IUserSession $userSession, IDBConnection $connection) {
		$this->userSession = $userSession;
		$this->connection = $connection;
	}

	/**
	 * Log file creation
	 */
	public function fileCreated(): void {
		$this->storeAction('created');
	}

	/**
	 * Log reading of file
	 */
	public function fileRead(): void {
		$this->storeAction('read');
	}

	protected function storeAction(string $action): void {
		$user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
			// Guest user
			return;
		}

		$update = $this->getUpdateQuery();
		$update
			->setParameter('user', $user->getUID(), IQueryBuilder::PARAM_STR)
			->setParameter('action', $action, IQueryBuilder::PARAM_STR)
		;
		$updated = $update->executeStatement();

		if ($updated === 0) {
			$insert = $this->getInsertQuery();
			$insert
				->setParameter('user', $user->getUID(), IQueryBuilder::PARAM_STR)
				->setParameter('action', $action, IQueryBuilder::PARAM_STR)
			;

			try {
				$insert->executeStatement();
			} catch (Exception $e) {
				// Ignore temporary issues only when two entries are generated in parallel
				if ($e->getReason() !== Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
					throw $e;
				}
			}
		}
	}

	protected function getUpdateQuery(): IQueryBuilder {
		if ($this->update !== null) {
			return $this->update;
		}

		$query = $this->connection->getQueryBuilder();
		$query->update('preferences')
			->set('configvalue',
				$query->expr()->castColumn(
					$query->createFunction(
						'(' . $query->expr()->castColumn('configvalue', IQueryBuilder::PARAM_INT)
						. ' + 1)'
					)
					, IQueryBuilder::PARAM_STR
				)
			)
			->where($query->expr()->eq('userid', $query->createParameter('user')))
			->andWhere($query->expr()->eq('configkey', $query->createParameter('action')))
			->andWhere($query->expr()->eq('appid', $query->createParameter('appid')))
			->setParameter('appid', 'user_usage_report');
		$this->update = $query;

		return $query;
	}

	protected function getInsertQuery(): IQueryBuilder {
		if ($this->insert !== null) {
			return $this->insert;
		}

		$query = $this->connection->getQueryBuilder();
		$query->insert('preferences')
			->values([
				'userid' => $query->createParameter('user'),
				'appid' => $query->createParameter('appid'),
				'configkey' => $query->createParameter('action'),
				'configvalue' => $query->createParameter('configvalue'),
			])
			->setParameter('appid', 'user_usage_report')
			->setParameter('configvalue', '1');
		$this->insert = $query;

		return $query;
	}
}
