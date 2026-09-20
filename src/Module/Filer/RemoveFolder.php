<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Module\Filer;

use Friendica\App;
use Friendica\BaseModule;
use Friendica\Core\L10n;
use Friendica\Core\Session\Capability\IHandleUserSessions;
use Friendica\Model\Post;
use Friendica\Module\Response;
use Friendica\Navigation\SystemMessages;
use Friendica\Util\Profiler;
use Psr\Log\LoggerInterface;

/**
 * Removes a whole folder, with every post filed under it
 */
class RemoveFolder extends BaseModule
{
	public function __construct(
		private readonly SystemMessages $systemMessages,
		L10n $l10n,
		App\BaseURL $baseUrl,
		App\Arguments $args,
		LoggerInterface $logger,
		Profiler $profiler,
		Response $response,
		private readonly IHandleUserSessions $userSession,
		array $server,
		array $parameters = [],
	) {
		parent::__construct($l10n, $baseUrl, $args, $logger, $profiler, $response, $server, $parameters);
	}

	protected function post(array $request = []): never
	{
		$this->earlyHttpError($this->removeFolder($request));
	}

	private function removeFolder(array $request): int
	{
		self::checkFormSecurityTokenForbiddenOnError('filer_remove', 't');

		$term = trim($request['term'] ?? '');

		$this->logger->info('Filer - Remove Folder', ['term' => $term]);

		if (!strlen($term)) {
			$this->systemMessages->addNotice($this->l10n->t('Folder was not removed'));
			return 400;
		}

		if (!Post\Category::deleteFolder($this->userSession->getLocalUserId(), $term)) {
			$this->systemMessages->addNotice($this->l10n->t('Folder was not removed'));
			return 500;
		}

		return 200;
	}
}
