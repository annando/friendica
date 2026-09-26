<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Module;

use Friendica\App\Arguments;
use Friendica\App\BaseURL;
use Friendica\App\Mode;
use Friendica\BaseModule;
use Friendica\Content\Conversation\StatusEditor;
use Friendica\Content\GroupManager;
use Friendica\Content\Nav;
use Friendica\Content\Pager;
use Friendica\Content\Text\BBCode;
use Friendica\Content\Text\Plaintext;
use Friendica\Core\Config\Capability\IManageConfigValues;
use Friendica\Core\L10n;
use Friendica\Core\PConfig\Capability\IManagePersonalConfigValues;
use Friendica\Core\Protocol;
use Friendica\Core\Renderer;
use Friendica\Core\Session\Capability\IHandleUserSessions;
use Friendica\Database\Database;
use Friendica\Model\Contact;
use Friendica\Model\Item;
use Friendica\Model\Post;
use Friendica\Network\HTTPException\ForbiddenException;
use Friendica\Network\HTTPException\NotFoundException;
use Friendica\Util\Profiler;
use Friendica\Util\Proxy;
use Psr\Log\LoggerInterface;

/**
 * Overview of the threads of a single group
 */
class Group extends BaseModule
{
	public function __construct(
		private readonly IHandleUserSessions $session,
		private readonly Database $database,
		private readonly IManageConfigValues $config,
		private readonly IManagePersonalConfigValues $pConfig,
		private readonly Mode $mode,
		private readonly StatusEditor $statusEditor,
		private readonly GroupManager $groupManager,
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

	protected function post(array $request = [])
	{
		$uid = $this->session->getLocalUserId();
		if (!$uid) {
			throw new ForbiddenException($this->t('Permission denied.'));
		}

		$return = 'group/' . rawurlencode((string) $this->parameters['id']);

		self::checkFormSecurityTokenRedirectOnError($return, 'group_mark_seen');

		$this->groupManager->markSeen($uid, $this->getGroup($uid)['id']);

		$this->baseUrl->redirect($return);
	}

	protected function content(array $request = []): string
	{
		$uid = $this->session->getLocalUserId();
		if (!$uid) {
			throw new ForbiddenException($this->t('Permission denied.'));
		}

		$contact = $this->getGroup($uid);
		$pcid    = $contact['id'];

		Nav::setSelected('groups');

		// Posting to the group requires a relationship to it
		$ucid   = Contact::getUserContactId($pcid, $uid);
		$editor = '';
		if ($ucid && !$contact['ap-posting-restricted']) {
			$this->statusEditor->registerAssets();
			$editor = $this->statusEditor->renderEditor([
				'group_cid'            => $ucid,
				'contact_account_type' => $contact['contact-type'],
			]);
		}

		if ($this->mode->isMobile()) {
			$itemsPerPage = $this->pConfig->get($uid, 'system', 'itemspage_mobile_network', $this->config->get('system', 'itemspage_network_mobile'));
		} else {
			$itemsPerPage = $this->pConfig->get($uid, 'system', 'itemspage_network', $this->config->get('system', 'itemspage_network'));
		}

		$pager = new Pager($this->l10n, $this->args->getQueryString(), $itemsPerPage);

		$condition = ['uid' => $uid, 'owner-id' => $pcid];

		$total  = $this->database->count('post-thread-user', $condition);
		$uriIds = array_column($this->database->selectToArray('post-thread-user', ['uri-id'], $condition, ['order' => ['commented' => true], 'limit' => [$pager->getStart(), $pager->getItemsPerPage()]]), 'uri-id');

		$parents = [];

		$posts = Post::selectForUser(
			$uid,
			['uri-id', 'guid', 'title', 'body', 'author-id', 'author-name', 'author-link', 'author-updated', 'created', 'unseen'],
			['uri-id' => $uriIds, 'uid' => $uid],
		);
		while ($post = Post::fetch($posts)) {
			$parents[$post['uri-id']] = $post;
		}
		$this->database->close($posts);

		$stats  = $this->groupManager->getThreadStats($uid, $uriIds, [Item::GRAVITY_COMMENT]);
		$latest = $this->groupManager->getLatestPosts($uid, $stats, [Item::GRAVITY_COMMENT], ['guid', 'body', 'author-name', 'author-link', 'unseen']);

		$threads = [];
		foreach ($uriIds as $uriId) {
			if (empty($parents[$uriId])) {
				continue;
			}

			$parent = $parents[$uriId];

			$thread = [
				'guid'     => $parent['guid'],
				'thumb'    => Contact::getAvatarUrlForId($parent['author-id'], Proxy::SIZE_THUMB, $parent['author-updated']),
				'author'   => $parent['author-name'],
				'link'     => Contact::magicLink($parent['author-link']),
				'created'  => $this->l10n->relativeDateTime($parent['created']),
				'title'    => $parent['title'] ?: Plaintext::shorten(BBCode::toPlaintext($parent['body'], false), 200),
				'unseen'   => $parent['unseen'],
				'comments' => $stats[$uriId]['posts']  ?? 0,
				'unread'   => $stats[$uriId]['unread'] ?? 0,
				'latest'   => '',
			];

			if (!empty($latest[$uriId])) {
				$comment = $latest[$uriId];

				$thread['latest'] = $this->t(
					'%1$s by %2$s: %3$s',
					'<a href="display/' . $comment['guid'] . '">' . htmlspecialchars($this->l10n->relativeDateTime($comment['received'])) . '</a>',
					'<a href="' . htmlspecialchars(Contact::magicLink($comment['author-link'])) . '">' . htmlspecialchars((string) $comment['author-name']) . '</a>',
					htmlspecialchars(Plaintext::shorten(BBCode::toPlaintext($comment['body'], false), 100)),
				);
				if ($comment['unseen']) {
					$thread['latest'] = '<strong>' . $thread['latest'] . '</strong>';
				}
			}

			$threads[] = $thread;
		}

		$tpl = Renderer::getMarkupTemplate('group.tpl');
		return Renderer::replaceMacros($tpl, [
			'$back'       => $this->t('Back to groups'),
			'$mark_seen'  => $this->t('Mark all as read'),
			'$editor'     => $editor,
			'$form_token' => self::getFormSecurityToken('group_mark_seen'),
			'$title'      => $contact['name'],
			'$id'         => rawurlencode((string) $this->parameters['id']),
			'$cid'        => $pcid,
			'$thread'     => $this->t('Thread'),
			'$comments'   => $this->t('Comments'),
			'$unread'     => $this->t('Unread'),
			'$latest'     => $this->t('Latest comment'),
			'$no_threads' => $this->t('There are no threads in this group.'),
			'$threads'    => $threads,
			'$paginate'   => $pager->renderFull($total),
		]);
	}

	/**
	 * The group can be addressed either by the contact id or by its address
	 *
	 * @param int $uid User id
	 *
	 * @return array public contact of the group
	 * @throws NotFoundException
	 */
	private function getGroup(int $uid): array
	{
		if (is_numeric($this->parameters['id'])) {
			$cid = (int) $this->parameters['id'];
		} else {
			$cid = Contact::getIdForURL($this->parameters['id'], 0, false);
		}

		$pcid = Contact::getPublicContactId($cid, $uid);
		if (!$pcid) {
			throw new NotFoundException($this->t('Contact not found.'));
		}

		$contact = Contact::getAccountById($pcid);
		if (empty($contact) || $contact['deleted'] || $contact['network'] === Protocol::PHANTOM || $contact['contact-type'] != Contact::TYPE_COMMUNITY) {
			throw new NotFoundException($this->t('Contact not found.'));
		}

		return $contact;
	}
}
