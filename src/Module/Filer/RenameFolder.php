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
 * Renames a whole folder, moving every post filed under it to the new name
 */
class RenameFolder extends BaseModule
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
		$newname = '';
		$this->earlyHttpError($this->renameFolder($request, $newname));
	}

	private function renameFolder(array $request, string &$newname): int
	{
		self::checkFormSecurityTokenForbiddenOnError('filer_rename', 't');

		$oldname = trim($request['oldname'] ?? '');
		$newname = trim($request['newname'] ?? '');

		$this->logger->info('Filer - Rename Folder', ['old' => $oldname, 'new' => $newname]);

		if (!strlen($oldname) || !strlen($newname) || $oldname === $newname) {
			$this->systemMessages->addNotice($this->l10n->t('Folder was not renamed'));
			return 400;
		}

		if (!Post\Category::renameFolder($this->userSession->getLocalUserId(), $oldname, $newname)) {
			$this->systemMessages->addNotice($this->l10n->t('Folder was not renamed'));
			return 500;
		}

		return 200;
	}
}
