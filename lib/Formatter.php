<?php

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserUsageReport;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

trait Formatter {
	protected string|int|null $timestamp = null;

	protected function printRecord(InputInterface $input, OutputInterface $output, string $userId, array $report): void {
		$separator = $input->getOption('field-separator');
		if ($this->timestamp === null) {
			/** @var string $format */
			$format = $input->getOption('date-format');
			$this->timestamp = date($format);
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
			$output->writeln(json_encode($jsonArray, JSON_THROW_ON_ERROR));
		}
	}
}
