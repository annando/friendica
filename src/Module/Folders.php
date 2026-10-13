<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Module;

use Friendica\App\Arguments;
use Friendica\App\BaseURL;
use Friendica\BaseModule;
use Friendica\Content\Nav;
use Friendica\Content\Widget;
use Friendica\Core\L10n;
use Friendica\Core\Renderer;
use Friendica\Core\Session\Capability\IHandleUserSessions;
use Friendica\DI;
use Friendica\Model\Post;
use Friendica\Network\HTTPException\ForbiddenException;
use Friendica\Util\Profiler;
use Psr\Log\LoggerInterface;

/**
 * Overview of the saved folders of the user
 */
class Folders extends BaseModule
{
	public function __construct(
		private readonly IHandleUserSessions $session,
		L10n $l10n,
		BaseURL $baseUrl,
		Arguments $args,
		LoggerInterface $logger,
		Profiler $profiler,
		Response $response,
		array $server,
		array $parameters = [],
	) {
		parent::__construct($l10n, $baseUrl, $args, $logger, $profiler, $response, $server, $parameters);
	}

	protected function content(array $request = []): string
	{
		$uid = $this->session->getLocalUserId();
		if (!$uid) {
			throw new ForbiddenException($this->t('Permission denied.'));
		}

		Nav::setSelected('network');
		DI::page()['aside'] .= Widget\SidebarMenu::getHTML($uid, Widget\SidebarMenu::FOLDERS);

		$items = [['name' => $this->t('Everything'), 'href' => 'filed']];
		foreach (Post\Category::getArray($uid, Post\Category::FILE) as $folder) {
			$items[] = ['name' => $folder, 'href' => 'filed?file=' . rawurlencode((string) $folder)];
		}

		return Renderer::replaceMacros(Renderer::getMarkupTemplate('overview_list.tpl'), [
			'$title'  => $this->t('Saved Folders'),
			'$intro'  => '',
			'$links'  => [],
			'$add'    => null,
			'$filter' => $this->t('Filter folders'),
			'$items'  => $items,
			'$empty'  => '',
		]);
	}
}
