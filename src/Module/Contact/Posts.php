<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Module\Contact;

use Friendica\App;
use Friendica\AppHelper;
use Friendica\BaseModule;
use Friendica\Content\Nav;
use Friendica\Content\Widget;
use Friendica\Core\L10n;
use Friendica\Core\Protocol;
use Friendica\Core\Session\Capability\IHandleUserSessions;
use Friendica\Database\DBA;
use Friendica\Model;
use Friendica\Model\Profile as ProfileModel;
use Friendica\Module\BaseProfile;
use Friendica\Module\Contact;
use Friendica\Module\Response;
use Friendica\Module\Security\Login;
use Friendica\Network\HTTPException\NotFoundException;
use Friendica\Util\Profiler;
use Psr\Log\LoggerInterface;

/**
 *  Show a contact posts and comments
 */
class Posts extends BaseModule
{
	public function __construct(L10n $l10n, App\BaseURL $baseUrl, App\Arguments $args, private readonly AppHelper $appHelper, LoggerInterface $logger, Profiler $profiler, Response $response, private App\Page $page, private readonly IHandleUserSessions $userSession, $server, array $parameters = [])
	{
		parent::__construct($l10n, $baseUrl, $args, $logger, $profiler, $response, $server, $parameters);
	}

	protected function content(array $request = []): string
	{
		if (!$this->userSession->getLocalUserId()) {
			return Login::form($_SERVER['REQUEST_URI']);
		}

		// Backward compatibility: Ensure to use the public contact when the user contact is provided
		// Remove by version 2022.03
		$pcid = Model\Contact::getPublicContactId(intval($this->parameters['id']), $this->userSession->getLocalUserId());
		if (!$pcid) {
			throw new NotFoundException($this->t('Contact not found.'));
		}

		$contact = Model\Contact::getById($pcid);
		if (!DBA::isResult($contact)) {
			throw new NotFoundException($this->t('Contact not found.'));
		}

		// Don't display contacts that are about to be deleted
		if ($contact['deleted'] || $contact['network'] == Protocol::PHANTOM) {
			throw new NotFoundException($this->t('Contact not found.'));
		}

		if (Model\Contact::isSelf($contact['id'], $this->userSession->getLocalUserId())) {
			$profile = ProfileModel::load($this->appHelper, $contact['nick']);
			Nav::setSelected('home');
			$o = BaseProfile::getTabsHTML('posts', true, $profile['nickname'], $profile['hide-friends']);
		} else {
			$this->page['aside'] .= Widget\VCard::getHTML($contact);
			Nav::setSelected('contacts');
			Contact::setPageTitle($contact);
			$o = Contact::getTabsHTML($contact, Contact::TAB_POSTS);
		}

		$o .= Model\Contact::getPostsFromId($contact['id'], $this->userSession->getLocalUserId(), false, $request);

		return $o;
	}
}
