<?php
/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
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

namespace OCA\UserUsageReport\Command;

use OCA\UserUsageReport\Reports\AllUsers;
use OCA\UserUsageReport\Reports\SingleUser;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Generate extends Command {

	/** @var SingleUser */
	protected $single;

	/** @var AllUsers */
	protected $all;

	/** @var IUserManager */
	protected $userManager;

	/**
	 * @param SingleUser $single
	 * @param AllUsers $all
	 * @param IUserManager $userManager
	 */
	public function __construct(SingleUser $single, AllUsers $all, IUserManager $userManager) {
		parent::__construct();

		$this->single = $single;
		$this->all = $all;
		$this->userManager = $userManager;
	}

	protected function configure() {
		$this
			->setName('usage-report:generate')
			->setDescription(
				'Prints a CVS entry with some usage information of the user:' . "\n"
				. 'userId,assignedQuota,usedQuota,numFiles,numShares,numUploads,numDownloads'
			)
			->addArgument(
				'user-id',
				InputArgument::OPTIONAL,
				'User to generate the report for, if none is given the report is generated for all users'
			)
		;
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		if ($input->getArgument('user-id')) {
			$this->single->printReport($output, $input->getArgument('user-id'));
		} else {
			$this->all->printReport($output);
		}

		return 0;
	}
}
