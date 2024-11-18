<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\UserUsageReport\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1001Date20180806133516 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 * @since 13.0.0
	 */
	public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options) {
		//		/** @var ISchemaWrapper $schema */
		//		$schema = $schemaClosure();
		//
		//		if (!$schema->hasTable('usage_report')) {
		//			$table = $schema->createTable('usage_report');
		//			$table->addColumn('user_id', Types::STRING, [
		//				'notnull' => true,
		//				'length' => 64,
		//			]);
		//			$table->addColumn('action', Types::STRING, [
		//				'notnull' => false,
		//				'length' => 64,
		//			]);
		//			$table->addColumn('timestamp', Types::DATETIME_MUTABLE, [
		//				'notnull' => false,
		//			]);
		//			$table->addIndex(['user_id', 'action', 'timestamp'], 'usage_report_uta');
		//		}
		//		return $schema;
		return null;
	}
}
