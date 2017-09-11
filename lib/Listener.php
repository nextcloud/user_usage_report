<?php
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
	protected $query;

	/**
	 * @param IUserSession $userSession
	 * @param IDBConnection $connection
	 */
	public function __construct(IUserSession $userSession, IDBConnection $connection) {
		$this->userSession = $userSession;
		$this->connection = $connection;
	}

	/**
	 * Log file creation
	 */
	public function fileCreated() {
		$this->storeAction('created');
	}

	/**
	 * Log reading of file
	 */
	public function fileRead() {
		$this->storeAction('read');
	}

	/**
	 * @param string $action
	 */
	protected function storeAction($action) {
		$user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
			// Guest user
			return;
		}

		$query = $this->getQuery();
		$query->setParameter('user', $user->getUID(), IQueryBuilder::PARAM_STR)
			->setParameter('action', $action, IQueryBuilder::PARAM_STR)
			->setParameter('timestamp', new \DateTime(), IQueryBuilder::PARAM_DATE);
		$query->execute();
	}

	/**
	 * @return IQueryBuilder
	 */
	protected function getQuery() {
		if ($this->query !== null) {
			return $this->query;
		}

		$query = $this->connection->getQueryBuilder();
		$query->insert('usage_report')
			->values([
				'user_id' => $query->createParameter('user'),
				'action' => $query->createParameter('action'),
				'timestamp' => $query->createParameter('timestamp'),
			]);
		$this->query = $query;

		return $query;
	}
}
