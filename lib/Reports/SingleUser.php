<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserUsageReport\Reports;

use OCA\UserUsageReport\Formatter;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\FileInfo;
use OCP\IAppConfig;
use OCP\IDBConnection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @psalm-api
 */
class SingleUser {
	use Formatter;


	/** @var IQueryBuilder[] */
	protected array $queries = [];

	public function __construct(
		protected IDBConnection $connection,
		protected IAppConfig $config,
	) {
	}

	public function printReport(InputInterface $input, OutputInterface $output, string $userId): void {
		$this->createQueries();

		$report = array_merge(
			$this->getNumberOfActionsForUser($userId),
			$this->getFilecacheStatsForUser($userId)
		);

		$report['quota'] = $this->getUserQuota($userId);
		if (is_numeric($report['quota'])) {
			$report['quota'] = (int)$report['quota'];
		}
		if ($input->getOption('last-login')) {
			$report['login'] = $this->getUserLastLogin($userId);
		}
		$report['shares'] = $this->getNumberOfSharesForUser($userId);
		if ($input->getOption('display-name')) {
			$report['display_name'] = $this->getUserDisplayName($userId);
		}

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
				$numActions[$metric] = (int)$row['num_actions'];
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
		$storageId = (int)$result->fetchOne();
		$result->closeCursor();

		if ($storageId === 0) {
			$home = 'object::user:' . $userId;
			if (strlen($home) > 64) {
				$home = md5($home);
			}

			$query->setParameter('storage_identifier', $home);
			$result = $query->executeQuery();
			$storageId = (int)$result->fetchOne();
			$result->closeCursor();
		}

		$query = $this->queries['countFiles'];
		$query->setParameter('storage_identifier', $storageId);
		$result = $query->executeQuery();
		$numFiles = (int)$result->fetchOne();
		$result->closeCursor();

		$query = $this->queries['getUsedSpace'];
		$query->setParameter('storage_identifier', $storageId);
		$result = $query->executeQuery();
		$usedSpace = (int)$result->fetchOne();
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

		return (int)$lastLogin;
	}

	protected function getUserQuota(string $userId): int|float|string {
		$query = $this->queries['getQuota'];
		$query->setParameter('user', $userId);
		$result = $query->executeQuery();
		$quota = $result->fetchOne();
		$result->closeCursor();

		if (is_numeric($quota)) {
			return $quota;
		}

		if ($quota === 'none') {
			return FileInfo::SPACE_UNLIMITED;
		}

		if ($quota !== false) {
			$quota = \OCP\Util::computerFileSize($quota);
			if ($quota !== false) {
				return $quota;
			}
		}

		return $this->config->getValueString('files', 'default_quota', (string)FileInfo::SPACE_UNKNOWN);
	}

	/**
	 * @param string $userId
	 * @return int
	 */
	protected function getNumberOfSharesForUser(string $userId): int {
		$query = $this->queries['countShares'];
		$query->setParameter('initiator', $userId);
		$result = $query->executeQuery();
		$numShares = (int)$result->fetchOne();
		$result->closeCursor();

		return $numShares;
	}

	protected function getUserDisplayName(string $userId): ?string {
		$query = $this->queries['displayName'];
		$query->setParameter('user', $userId);
		$result = $query->executeQuery();

		if ($result->rowCount() === 0) {
			return null;
		}

		return (string)$result->fetchOne();
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
		$query->selectAlias($query->createFunction('COUNT(*)'), 'num_files')
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
		$query->selectAlias($query->createFunction('COUNT(*)'), 'num_shares')
			->from('share')
			->where($query->expr()->eq('uid_initiator', $query->createParameter('initiator')));
		$this->queries['countShares'] = $query;

		// Get number of downloads and uploads
		$query = $this->connection->getQueryBuilder();
		$query->selectAlias('configkey', 'action')
			->selectAlias('configvalue', 'num_actions')
			->from('preferences')
			->where($query->expr()->eq('userid', $query->createParameter('user')))
			->andWhere($query->expr()->eq('appid', $query->createParameter('appid')))
			->orderBy('userid', 'ASC')
			->addOrderBy('configkey', 'ASC')
			->setParameter('appid', 'user_usage_report');
		$this->queries['countActions'] = $query;

		// Get User Display Name
		$query = $this->connection->getQueryBuilder();
		$query->select('value')
			->from('accounts_data')
			->where($query->expr()->eq('name', $query->createNamedParameter('displayname')))
			->andWhere($query->expr()->eq('uid', $query->createParameter('user')));
		$this->queries['displayName'] = $query;
	}
}
