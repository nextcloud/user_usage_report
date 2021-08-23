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

namespace OCA\UserUsageReport\Reports;


use OCA\UserUsageReport\Formatter;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\FileInfo;
use OCP\IConfig;
use OCP\IDBConnection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SingleUser {

	use Formatter;

	/** @var IDBConnection */
	protected $connection;

	/** @var IConfig */
	protected $config;

	/** @var IQueryBuilder[] */
	protected $queries = [];

	public function __construct(IDBConnection $connection, IConfig $config) {
		$this->connection = $connection;
		$this->config = $config;
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @param string $userId
	 */
	public function printReport(InputInterface $input, OutputInterface $output, string $userId): void {
		$this->createQueries();

		$report = array_merge(
			$this->getNumberOfActionsForUser($userId),
			$this->getFilecacheStatsForUser($userId)
		);

		$report['quota'] = $this->getUserQuota($userId);
		if ($input->getOption('last-login')) {
			$report['login'] = $this->getUserLastLogin($userId);
		}
		$report['shares'] = $this->getNumberOfSharesForUser($userId);
		$report['display_name'] = $this->getUserDisplayName($userId);

		$this->printRecord($input, $output, $userId, $report);
	}

	/**
	 * @param string $userId
	 * @return array
	 */
	protected function getNumberOfActionsForUser(string $userId): array {
		$query = $this->queries['countActions'];
		$query->setParameter('user', $userId);
		$result = $query->executeQuery();

		$numActions = [
			'uploads' => 0,
			'downloads' => 0,
		];

		while ($row = $result->fetch()) {
			try {
				$metric = $this->actionToMetric($row['action']);
				$numActions[$metric] = (int) $row['num_actions'];
			} catch (\InvalidArgumentException $e) {
				continue;
			}
		}
		$result->closeCursor();

		return $numActions;
	}

	/**
	 * @param string $userId
	 * @return array
	 */
	protected function getFilecacheStatsForUser(string $userId): array {
		$query = $this->queries['getStorageId'];

		$home = 'home::' . $userId;
		if (strlen($home) > 64) {
			$home = md5($home);
		}

		$query->setParameter('storage_identifier', $home);
		$result = $query->executeQuery();
		$storageId = (int) $result->fetchOne();
		$result->closeCursor();

		if ($storageId === 0) {
			$home = 'object::user:' . $userId;
			if (strlen($home) > 64) {
				$home = md5($home);
			}

			$query->setParameter('storage_identifier', $home);
			$result = $query->executeQuery();
			$storageId = (int) $result->fetchOne();
			$result->closeCursor();
		}

		$query = $this->queries['countFiles'];
		$query->setParameter('storage_identifier', $storageId);
		$result = $query->executeQuery();
		$numFiles = (int) $result->fetchOne();
		$result->closeCursor();

		$query = $this->queries['getUsedSpace'];
		$query->setParameter('storage_identifier', $storageId);
		$result = $query->executeQuery();
		$usedSpace = (int) $result->fetchOne();
		$result->closeCursor();

		return [
			'files' => $numFiles,
			'used' => $usedSpace,
		];
	}

	/**
	 * @param string $userId
	 * @return int
	 */
	protected function getUserLastLogin(string $userId): int {
		$query = $this->queries['lastLogin'];
		$query->setParameter('user', $userId);
		$result = $query->executeQuery();
		$lastLogin = $result->fetchOne();
		$result->closeCursor();

		return (int) $lastLogin;
	}

	/**
	 * @param string $userId
	 * @return int|string
	 */
	protected function getUserQuota(string $userId) {
		$query = $this->queries['getQuota'];
		$query->setParameter('user', $userId);
		$result = $query->executeQuery();
		$quota = $result->fetchOne();
		$result->closeCursor();

		if (is_numeric($quota)) {
			return (int) $quota;
		}

		if ($quota === 'none') {
			return FileInfo::SPACE_UNLIMITED;
		}

		if ($quota) {
			$quota = \OC_Helper::computerFileSize($quota);
			if ($quota !== false) {
				return (int) $quota;
			}
		}

		return $this->config->getAppValue('files', 'default_quota', FileInfo::SPACE_UNKNOWN);
	}

	/**
	 * @param string $userId
	 * @return int
	 */
	protected function getNumberOfSharesForUser(string $userId): int {
		$query = $this->queries['countShares'];
		$query->setParameter('initiator', $userId);
		$result = $query->executeQuery();
		$numShares = (int) $result->fetchOne();
		$result->closeCursor();

		return $numShares;
	}

	/**
	 * @param string $userId
	 * @return string
	 */
	protected function getUserDisplayName(string $userId): string {
		$query = $this->queries['displayName'];
		$query->setParameter('user', $userId);
		$result = $query->executeQuery();
		$data = $result->fetchOne();
		$result->closeCursor();
		$json = json_decode($data, true);
		$displayName = $json['displayname']['value'];
		return (string) $displayName;
	}


	/**
	 * @param string $action
	 * @return string
	 * @throws \InvalidArgumentException
	 */
	protected function actionToMetric(string $action): string {
		switch ($action) {
			case 'created':
				return 'uploads';
			case 'read':
				return 'downloads';
			default:
				throw new \InvalidArgumentException('Unknown action');
		}
	}

	protected function createQueries(): void {
		if (!empty($this->queries)) {
			return;
		}

		// Get home storage
		$query = $this->connection->getQueryBuilder();
		$query->select('numeric_id')
			->from('storages')
			->where($query->expr()->eq('id', $query->createParameter('storage_identifier')));
		$this->queries['getStorageId'] = $query;

		// Get number of files
		$query = $this->connection->getQueryBuilder();
		$query->selectAlias($query->createFunction('COUNT(*)'),'num_files')
			->from('filecache')
			->where($query->expr()->eq('storage', $query->createParameter('storage_identifier')));
		$this->queries['countFiles'] = $query;

		// Get used quota
		$query = $this->connection->getQueryBuilder();
		$query->select('size')
			->from('filecache')
			->where($query->expr()->eq('storage', $query->createParameter('storage_identifier')))
			->andWhere($query->expr()->eq('path_hash', $query->createNamedParameter(md5('files'))));
		$this->queries['getUsedSpace'] = $query;

		// Get quota
		$query = $this->connection->getQueryBuilder();
		$query->select('configvalue')
			->from('preferences')
			->where($query->expr()->eq('userid', $query->createParameter('user')))
			->andWhere($query->expr()->eq('appid', $query->createNamedParameter('files')))
			->andWhere($query->expr()->eq('configkey', $query->createNamedParameter('quota')));
		$this->queries['getQuota'] = $query;

		// Get quota
		$query = $this->connection->getQueryBuilder();
		$query->select('configvalue')
			->from('preferences')
			->where($query->expr()->eq('userid', $query->createParameter('user')))
			->andWhere($query->expr()->eq('appid', $query->createNamedParameter('login')))
			->andWhere($query->expr()->eq('configkey', $query->createNamedParameter('lastLogin')));
		$this->queries['lastLogin'] = $query;

		// Get number of shares
		$query = $this->connection->getQueryBuilder();
		$query->selectAlias($query->createFunction('COUNT(*)'),'num_shares')
			->from('share')
			->where($query->expr()->eq('uid_initiator', $query->createParameter('initiator')));
		$this->queries['countShares'] = $query;

		// Get number of downloads and uploads
		$query = $this->connection->getQueryBuilder();
		$query->selectAlias('configkey','action')
			->selectAlias('configvalue','num_actions')
			->from('preferences')
			->where($query->expr()->eq('userid', $query->createParameter('user')))
			->andWhere($query->expr()->eq('appid', $query->createParameter('appid')))
			->orderBy('userid', 'ASC')
			->addOrderBy('configkey', 'ASC')
			->setParameter('appid', 'user_usage_report');
		$this->queries['countActions'] = $query;

	        // Get User Display Name
		$query = $this->connection->getQueryBuilder();
		$query->select('data')
			->from('accounts')
		  ->where($query->expr()->eq('uid', $query->createParameter('user')));
		$this->queries['displayName'] = $query;

	}
}
