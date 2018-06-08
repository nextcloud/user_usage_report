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

use OC\AppFramework\Http\Request;
use OCP\Files\FileInfo;
use OCP\IRequest;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

trait Formatter {

	protected $timestamp;

	protected $userAgents = [
		'IE' => 				Request::USER_AGENT_IE,
		'Edge' => 				Request::USER_AGENT_MS_EDGE,
		'Chrome' => 			Request::USER_AGENT_CHROME,
		'Firefox' => 			Request::USER_AGENT_FIREFOX,
		'Safari' => 			Request::USER_AGENT_SAFARI,
		'Chrome for Android' => Request::USER_AGENT_ANDROID_MOBILE_CHROME,
		'Android' => 			IRequest::USER_AGENT_CLIENT_ANDROID,
		'Talk for Android' =>	IRequest::USER_AGENT_TALK_ANDROID,
		'Desktop client' => 	IRequest::USER_AGENT_CLIENT_DESKTOP,
		'IOS client' => 		IRequest::USER_AGENT_CLIENT_IOS,
		'Talk for IOS' => 		IRequest::USER_AGENT_TALK_IOS,
		'Outlook' => 			IRequest::USER_AGENT_OUTLOOK_ADDON,
		'Thunderbird' => 		IRequest::USER_AGENT_THUNDERBIRD_ADDON
	];

	protected function printRecord(InputInterface $input, OutputInterface $output, $userId, array $report) {
		$separator = $input->getOption('field-separator');
		if ($this->timestamp === null) {
			$this->timestamp = date($input->getOption('date-format'));
		}

		$data = '"'. $userId . '"'. $separator;
		$data .= '"'. $this->timestamp . '"'. $separator;
		$data .= (!is_numeric($report['quota']) ? '"'. $report['quota'] . '"' : $report['quota']). $separator;
		$data .= (!is_numeric($report['used']) ? '"'. $report['used'] . '"' : $report['used']). $separator;
		$data .= $report['files'] . $separator;
		$data .= $report['shares'] . $separator;
		$data .= $report['uploads'] . $separator;
		$data .= $report['downloads'] . $separator;
		$data .= $report['platform'];
		$output->writeln($data);
	}

	public function humanFileSize($bytes) {
		if ($bytes < 0) {
			return FileInfo::SPACE_UNKNOWN;
		}

		if ($bytes < 1024) {
			return "$bytes B";
		}
		$bytes = round($bytes / 1024, 2);
		if ($bytes < 1024) {
			return "$bytes KB";
		}
		$bytes = round($bytes / 1024, 2);
		if ($bytes < 1024) {
			return "$bytes MB";
		}
		$bytes = round($bytes / 1024, 2);
		if ($bytes < 1024) {
			return "$bytes GB";
		}
		$bytes = round($bytes / 1024, 2);
		if ($bytes < 1024) {
			return "$bytes TB";
		}

		$bytes = round($bytes / 1024, 2);
		return "$bytes PB";
	}

	public function getPlatformFromUA(string $userAgent) {
		if ($userAgent === '') {
			return 'None';
		}
		foreach ($this->userAgents as $platform => $regex) {
			if (preg_match($regex, $userAgent)) {
				return $platform;
			}
		}
		return 'Unknown';
	}

}
