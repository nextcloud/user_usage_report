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
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AllUsers {
	public const BATCH_SIZE = 1000;

	use Formatter;

	/** @var IDBConnection */
	protected $connection;

	/** @var IConfig */
	protected $config;

	/** @var IUserManager */
	protected $userManager;

	/** @var IQueryBuilder[] */
	protected $queries = [];

	/** @var array[] */
	protected $reports = [];

	/** @var array[] */
	protected $storages = [];

	/** @var string[] */
	protected $storageMap = [];

	public function __construct(IDBConnection $connection, IConfig $config, IUserManager $userManager) {
		$this->connection = $connection;
		$this->config = $config;
		$this->userManager = $userManager;
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	public function printReport(InputInterface $input, OutputInterface $output): void {
		$this->createQueries();

		$default = [
			'uploads' => 0,
			'downloads' => 0,
			'files' => 0,
			'used' => FileInfo::SPACE_UNKNOWN,
			'quota' => $this->config->getAppValue('files', 'default_quota', FileInfo::SPACE_UNKNOWN),
			'shares' => 0,
			'login' => 0,
			'display_name' => '',
		];

		$progress = new ProgressBar($output);

		$i = 0;
		$this->userManager->callForAllUsers(function(IUser $user) use ($default, $input, $progress, &$i) {
			$this->reports[$user->getUID()] = $default;
			if ($input->getOption('display-name')) {
				$this->reports[$user->getUID()]['display_name'] = $user->getDisplayName();
			}

			$home = 'home::' . $user->getUID();
			if (strlen($home) > 64) {
				$home = md5($home);
			}
			$this->storageMap[$home] = $user->getUID();

			$home = 'object::user:' . $user->getUID();
			if (strlen($home) > 64) {
				$home = md5($home);
			}
			$this->storageMap[$home] = $user->getUID();

			if ($i % 200 === 0) {
				$progress->advance();
			}
			$i++;
		});

		$progress->advance();

		$this->getFilecacheStats($progress);
		$this->getNumberOfActions($progress);
		$this->getUserQuota($progress);
		$this->getNumberOfShares($progress);
		if ($input->getOption('last-login')) {
			$this->getUserLastLogin($progress);
		}
		$progress->advance();


		$progress->clear();

		foreach ($this->reports as $userId => $report) {
			$this->printRecord($input, $output, $userId, $report);
		}
	}

	protected function getNumberOfActions(ProgressBar $progress): void {
		$offset = 0;
		do {
			$numResults = $this->getNumberOfActionsBatch($offset);
			$offset += $numResults;
			$progress->advance();
		} while ($numResults === self::BATCH_SIZE);
	}

	/**
	 * @param int $offset
	 * @return int
	 */
	protected function getNumberOfActionsBatch(int $offset): int {
		$query = $this->queries['countActions'];
		$query->setFirstResult($offset);

		$result = $query->executeQuery();
		$numResults = 0;
		while ($row = $result->fetch()) {
			try {
				$metric = $this->actionToMetric($row['action']);
				$this->reports[$row['user_id']][$metric] = (int) $row['num_actions'];
			} catch (\InvalidArgumentException $e) {
			}
			$numResults++;
		}
		$result->closeCursor();

		return $numResults;
	}

	protected function getFilecacheStats(ProgressBar $progress): void {
		$offset = 0;
		do {
			$result = $this->countFilesBatch($offset);
			$this->getRootSizeBatch($result);
			$this->mapStorageToUser($result);

			$offset += $result['results'];
			$progress->advance();
		} while ($result['results'] === self::BATCH_SIZE);
	}

	/**
	 * @param int $offset
	 * @return array
	 */
	protected function countFilesBatch(int $offset): array {
		$query = $this->queries['countFiles'];
		$query->setFirstResult($offset);

		$result = $query->executeQuery();
		$numResults = $first = $last = 0;

		while ($row = $result->fetch()) {
			if ($first === 0) {
				$first = (int) $row['storage'];
			}
			$last = (int) $row['storage'];
			$this->storages[$last] = [
				'files' => (int) $row['num_files'],
				'used' => FileInfo::SPACE_UNKNOWN,
			];
			$numResults++;
		}
		$result->closeCursor();

		return ['results' => $numResults, 'first' => $first, 'last' => $last];
	}

	/**
	 * @param array $limits
	 */
	protected function getRootSizeBatch(array $limits): void {
		$query = $this->queries['getUsedSpace'];
		$query->setParameter('bottom', $limits['first'])
			->setParameter('top', $limits['last']);

		$result = $query->executeQuery();
		while ($row = $result->fetch()) {
			$this->storages[(int) $row['storage']]['used'] = (int) $row['size'];
		}
		$result->closeCursor();
	}

	protected function mapStorageToUser(array $limits): void {
		$query = $this->queries['getStorage'];
		$query->setParameter('bottom', $limits['first'])
			->setParameter('top', $limits['last']);

		$result = $query->executeQuery();
		while ($row = $result->fetch()) {
			$storage = (int) $row['numeric_id'];

			if (!isset($this->storageMap[$row['id']], $this->storages[$storage])) {
				unset($this->storages[$storage]);
				continue;
			}

			$userId = $this->storageMap[$row['id']];
			$this->reports[$userId]['used'] = $this->storages[$storage]['used'];
			$this->reports[$userId]['files'] = $this->storages[$storage]['files'];
			unset($this->storages[$storage]);
		}
		$result->closeCursor();
	}

	protected function getUserQuota(ProgressBar $progress): void {
		$offset = 0;
		do {
			$numResults = $this->getUserQuotaBatch($offset);
			$offset += $numResults;
			$progress->advance();
		} while ($numResults === self::BATCH_SIZE);
	}

	/**
	 * @param int $offset
	 * @return int
	 */
	protected function getUserQuotaBatch(int $offset): int {
		$query = $this->queries['getQuota'];
		$query->setFirstResult($offset);

		$result = $query->executeQuery();
		$numResults = 0;
		while ($row = $result->fetch()) {
			if ($row['configvalue'] !== 'default') {
				$quota = \OC_Helper::computerFileSize($row['configvalue']);
				$this->reports[$row['userid']]['quota'] = $quota === false ? $row['configvalue'] : $quota;
			}
			$numResults++;
		}
		$result->closeCursor();

		return $numResults;
	}

	protected function getUserLastLogin(ProgressBar $progress): void {
		$offset = 0;
		do {
			$numResults = $this->getUserLastLoginBatch($offset);
			$offset += $numResults;
			$progress->advance();
		} while ($numResults === self::BATCH_SIZE);
	}

	/**
	 * @param int $offset
	 * @return int
	 */
	protected function getUserLastLoginBatch(int $offset): int {
		$query = $this->queries['lastLogin'];
		$query->setFirstResult($offset);

		$result = $query->executeQuery();
		$numResults = 0;
		while ($row = $result->fetch()) {
			$this->reports[$row['userid']]['login'] = $row['configvalue'];
			$numResults++;
		}
		$result->closeCursor();

		return $numResults;
	}

	protected function getNumberOfShares(ProgressBar $progress): void {
		$offset = 0;
		do {
			$numResults = $this->getNumberOfSharesBatch($offset);
			$offset += $numResults;
			$progress->advance();
		} while ($numResults === self::BATCH_SIZE);
	}

	/**
	 * @param int $offset
	 * @return int
	 */
	protected function getNumberOfSharesBatch(int $offset): int {
		$query = $this->queries['countShares'];
		$query->setFirstResult($offset);

		$result = $query->executeQuery();
		$numResults = 0;
		while ($row = $result->fetch()) {
			$this->reports[$row['uid_initiator']]['shares'] = (int) $row['num_shares'];
			$numResults++;
		}
		$result->closeCursor();

		return $numResults;
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
		$query->select(['numeric_id', 'id'])
			->from('storages')
			->where($query->expr()->gte('numeric_id', $query->createParameter('bottom')))
			->andWhere($query->expr()->lte('numeric_id', $query->createParameter('top')));
		$this->queries['getStorage'] = $query;

		// Get number of files
		$query = $this->connection->getQueryBuilder();
		$query->select('storage')
			->selectAlias($query->createFunction('COUNT(*)'),'num_files')
			->from('filecache')
			->groupBy('storage')
			->orderBy('storage', 'ASC');
		$this->queries['countFiles'] = $query;

		// Get used quota
		$query = $this->connection->getQueryBuilder();
		$query->select(['storage', 'size'])
			->from('filecache')
			->where($query->expr()->gte('storage', $query->createParameter('bottom')))
			->andWhere($query->expr()->lte('storage', $query->createParameter('top')))
			->andWhere($query->expr()->eq('path_hash', $query->createNamedParameter(md5('files'))));
		$this->queries['getUsedSpace'] = $query;

		// Get quota
		$query = $this->connection->getQueryBuilder();
		$query->select(['userid', 'configvalue'])
			->from('preferences')
			->where($query->expr()->eq('appid', $query->createNamedParameter('files')))
			->andWhere($query->expr()->eq('configkey', $query->createNamedParameter('quota')))
			->orderBy('userid', 'ASC')
			->setMaxResults(self::BATCH_SIZE);
		$this->queries['getQuota'] = $query;

		// Get last_login
		$query = $this->connection->getQueryBuilder();
		$query->select(['userid', 'configvalue'])
			->from('preferences')
			->where($query->expr()->eq('appid', $query->createNamedParameter('login')))
			->andWhere($query->expr()->eq('configkey', $query->createNamedParameter('lastLogin')))
			->orderBy('userid', 'ASC')
			->setMaxResults(self::BATCH_SIZE);
		$this->queries['lastLogin'] = $query;

		// Get number of shares
		$query = $this->connection->getQueryBuilder();
		$query->select('uid_initiator')
			->selectAlias($query->createFunction('COUNT(*)'),'num_shares')
			->from('share')
			->groupBy('uid_initiator')
			->orderBy('uid_initiator', 'ASC')
			->setMaxResults(self::BATCH_SIZE);
		$this->queries['countShares'] = $query;

		// Get number of downloads and uploads
		$query = $this->connection->getQueryBuilder();
		$query->selectAlias('userid','user_id')
			->selectAlias('configkey','action')
			->selectAlias('configvalue','num_actions')
			->from('preferences')
			->where($query->expr()->eq('appid', $query->createNamedParameter('user_usage_report')))
			->orderBy('userid', 'ASC')
			->addOrderBy('configkey', 'ASC');
		$this->queries['countActions'] = $query;
	}
}
