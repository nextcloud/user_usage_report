<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserUsageReport;

use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\BeforeNodeReadEvent;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Preview\BeforePreviewFetchedEvent;

/**
 * @template-implements IEventListener<Event|BeforeNodeReadEvent|BeforePreviewFetchedEvent>
 * @psalm-api
 */
class Listener implements IEventListener {

	protected ?IQueryBuilder $insert = null;

	protected ?IQueryBuilder $update = null;

	public function __construct(
		private readonly IUserSession $userSession,
		private readonly IDBConnection $connection,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof BeforeNodeReadEvent) {
			$this->fileRead();
		}

		if ($event instanceof BeforePreviewFetchedEvent && ($event->getHeight() > 256 || $event->getWidth() > 256)) {
			$this->fileRead();
		}

		if ($event instanceof NodeCreatedEvent) {
			$this->fileCreated();
		}
	}

	/**
	 * Log file creation
	 */
	public function fileCreated(): void {
		$this->storeAction('created');
	}

	/**
	 * Log reading of file
	 */
	public function fileRead(): void {
		$this->storeAction('read');
	}

	protected function storeAction(string $action): void {
		$user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
			// Guest user
			return;
		}

		$update = $this->getUpdateQuery();
		$update
			->setParameter('user', $user->getUID(), IQueryBuilder::PARAM_STR)
			->setParameter('action', $action, IQueryBuilder::PARAM_STR)
		;
		$updated = $update->executeStatement();

		if ($updated === 0) {
			$insert = $this->getInsertQuery();
			$insert
				->setParameter('user', $user->getUID(), IQueryBuilder::PARAM_STR)
				->setParameter('action', $action, IQueryBuilder::PARAM_STR)
			;

			try {
				$insert->executeStatement();
			} catch (Exception $e) {
				// Ignore temporary issues only when two entries are generated in parallel
				if ($e->getReason() !== Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
					throw $e;
				}
			}
		}
	}

	protected function getUpdateQuery(): IQueryBuilder {
		if ($this->update !== null) {
			return $this->update;
		}

		$query = $this->connection->getQueryBuilder();
		$query->update('preferences')
			->set('configvalue',
				$query->expr()->castColumn(
					$query->createFunction(
						'(' . (string)$query->expr()->castColumn('configvalue', IQueryBuilder::PARAM_INT)
						. ' + 1)'
					), IQueryBuilder::PARAM_STR
				)
			)
			->where($query->expr()->eq('userid', $query->createParameter('user')))
			->andWhere($query->expr()->eq('configkey', $query->createParameter('action')))
			->andWhere($query->expr()->eq('appid', $query->createParameter('appid')))
			->setParameter('appid', 'user_usage_report');
		$this->update = $query;

		return $query;
	}

	protected function getInsertQuery(): IQueryBuilder {
		if ($this->insert !== null) {
			return $this->insert;
		}

		$query = $this->connection->getQueryBuilder();
		$query->insert('preferences')
			->values([
				'userid' => $query->createParameter('user'),
				'appid' => $query->createParameter('appid'),
				'configkey' => $query->createParameter('action'),
				'configvalue' => $query->createParameter('configvalue'),
			])
			->setParameter('appid', 'user_usage_report')
			->setParameter('configvalue', '1');
		$this->insert = $query;

		return $query;
	}
}
