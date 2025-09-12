<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserUsageReport\AppInfo;

use OCA\UserUsageReport\Listener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\Events\Node\BeforeNodeReadEvent;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Preview\BeforePreviewFetchedEvent;

/**
 * @psalm-api
 */
class Application extends App implements IBootstrap {
	public const APP_ID = 'user_usage_report';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}


	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(BeforeNodeReadEvent::class, Listener::class);
		$context->registerEventListener(BeforePreviewFetchedEvent::class, Listener::class);
		$context->registerEventListener(NodeCreatedEvent::class, Listener::class);
	}

	public function boot(IBootContext $context): void {
		// done
	}
}
