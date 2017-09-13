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

namespace OCA\UserUsageReport\AppInfo;

use OCP\Util;
use OCA\UserUsageReport\Listener;
use OCP\AppFramework\App;

class Application extends App {

	public function __construct() {
		parent::__construct('user_usage_report');
	}

	public function register() {
		$this->registerFilesActivity();
	}

	/**
	 * Register the hooks for filesystem operations
	 *
	 * @throws \OCP\AppFramework\QueryException
	 */
	public function registerFilesActivity() {
		$listener = $this->getContainer()->query(Listener::class);

		Util::connectHook('OC_Filesystem', 'post_create', $listener, 'fileCreated');
		Util::connectHook('OC_Filesystem', 'read', $listener, 'fileRead');
	}
}
