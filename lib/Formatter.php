<?php
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserUsageReport;

use OCP\Files\FileInfo;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

trait Formatter {
	protected $timestamp;

	protected function printRecord(InputInterface $input, OutputInterface $output, $userId, array $report): void {
		$separator = $input->getOption('field-separator');
		if ($this->timestamp === null) {
			$this->timestamp = date($input->getOption('date-format'));
		}

		$jsonArray = ['user_id' => $userId];
		$data = '"' . $userId . '"' . $separator;
		if ($input->getOption('display-name')) {
			$jsonArray['display_name'] = $report['display_name'] ?? 'no display name found';
			$data .= '"' . ($report['display_name'] ?? 'no display name found') . '"' . $separator;
		}
		$jsonArray['date'] = $this->timestamp;
		$data .= '"' . $this->timestamp . '"' . $separator;
		if ($input->getOption('last-login')) {
			$report['login'] = isset($report['login']) ? date($input->getOption('date-format'), $report['login']) : 'no last login found';
			$jsonArray['login'] = $report['login'];
			$data .= '"' . $report['login'] . '"' . $separator;
		}

		// ensure all fields we are trying to print are set
		$fields = ['quota', 'used', 'files', 'shares', 'uploads', 'downloads'];
		foreach ($fields as $field) {
			if (!isset($report[$field])) {
				$report[$field] = 0;
			}
		}

		$data .= (!is_numeric($report['quota']) ? '"' . $report['quota'] . '"' : $report['quota']) . $separator;
		$jsonArray['quota'] = $report['quota'];
		$data .= (!is_numeric($report['used']) ? '"' . $report['used'] . '"' : $report['used']) . $separator;
		$jsonArray['used'] = $report['used'];
		$data .= $report['files'] . $separator;
		$jsonArray['files'] = $report['files'];
		$data .= $report['shares'] . $separator;
		$jsonArray['shares'] = $report['shares'];
		$data .= $report['uploads'] . $separator;
		$jsonArray['uploads'] = $report['uploads'];
		$data .= $report['downloads'];
		$jsonArray['downloads'] = $report['downloads'];

		if ($input->getOption('output') === 'csv') {
			$output->writeln($data);
		} else {
			$output->writeln(json_encode($jsonArray));
		}
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
}
