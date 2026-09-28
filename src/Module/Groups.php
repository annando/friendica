<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Module;

use Friendica\App\Arguments;
use Friendica\App\BaseURL;
use Friendica\BaseModule;
use Friendica\Content\GroupManager;
use Friendica\Content\Nav;
use Friendica\Content\Text\BBCode;
use Friendica\Content\Text\Plaintext;
use Friendica\Core\L10n;
use Friendica\Core\Renderer;
use Friendica\Core\Session\Capability\IHandleUserSessions;
use Friendica\Database\Database;
use Friendica\Database\DBA;
use Friendica\Model\Contact;
use Friendica\Model\Item;
use Friendica\Model\Post;
use Friendica\Network\HTTPException\ForbiddenException;
use Friendica\Util\Profiler;
use Psr\Log\LoggerInterface;

/**
 * Overview of the groups the user is subscribed to
 */
class Groups extends BaseModule
{
	public function __construct(
		private readonly GroupManager $groupManager,
		private readonly IHandleUserSessions $session,
		private readonly Database $database,
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

		Nav::setSelected('groups');

		$contacts = $this->groupManager->getList($uid, true, true, true);

		$stats   = $this->groupManager->getGroupStats($uid, array_column($contacts, 'pid'));
		$threads = $this->groupManager->getLatestThreads($uid, $stats);
		$parents = $this->getParents($uid, array_values($threads));
		$gravity = [Item::GRAVITY_PARENT, Item::GRAVITY_COMMENT];
		$latest  = $this->groupManager->getLatestPosts($uid, $this->groupManager->getThreadStats($uid, array_values($threads), $gravity), $gravity, ['guid', 'author-name', 'author-link', 'unseen']);

		$groups  = [];
		$servers = $this->groupManager->getServers(array_column($contacts, 'gsid'));
		foreach ($contacts as $contact) {
			$pid  = $contact['pid'];
			$host = parse_url((string) $contact['url'], PHP_URL_HOST) ?: '';

			$group = [
				'id'       => $contact['id'],
				'link'     => 'group/' . rawurlencode((string) $contact['addr'] ?: (string) $contact['id']),
				'name'     => $contact['name'],
				'thumb'    => Contact::getThumb($contact),
				'about'    => Plaintext::shorten(BBCode::toPlaintext($contact['about'], false), 200),
				'posts'    => $stats[$pid]['threads'] ?? 0,
				'unread'   => $stats[$pid]['unread']  ?? 0,
				'received' => '',
				'latest'   => '',
			];

			$uriId = $threads[$pid] ?? 0;
			if (!empty($parents[$uriId]) && !empty($latest[$uriId])) {
				$parent = $parents[$uriId];
				$post   = $latest[$uriId];

				$group['received'] = $post['received'];
				$group['latest']   = $this->t(
					'%1$s by %2$s in %3$s',
					'<a href="display/' . $post['guid'] . '">' . htmlspecialchars($this->l10n->relativeDateTime($post['received'])) . '</a>',
					'<a href="' . htmlspecialchars(Contact::magicLink($post['author-link'])) . '">' . htmlspecialchars((string) $post['author-name']) . '</a>',
					'<a href="display/' . $parent['guid'] . '">' . htmlspecialchars((string) $parent['title'] ?: Plaintext::shorten(BBCode::toPlaintext($parent['body'], false), 100)) . '</a>',
				);
				if ($post['unseen']) {
					$group['latest'] = '<strong>' . $group['latest'] . '</strong>';
				}
			}

			if (!isset($groups[$host])) {
				$groups[$host] = array_merge($this->groupManager->getServerDescription($servers[$contact['gsid']] ?? [], $host), [
					'gsid'   => $contact['gsid'],
					'groups' => [],
				]);
			}
			$groups[$host]['groups'][] = $group;
		}

		foreach ($groups as $host => $server) {
			usort($groups[$host]['groups'], fn ($a, $b): int => strcmp((string) $b['received'], (string) $a['received']));
		}
		usort($groups, fn ($a, $b): int => strcmp((string) $b['groups'][0]['received'], (string) $a['groups'][0]['received']));

		$tpl = Renderer::getMarkupTemplate('groups.tpl');
		return Renderer::replaceMacros($tpl, [
			'$title'           => $this->t('Groups'),
			'$group'           => $this->t('Group'),
			'$posts'           => $this->t('Posts'),
			'$unread'          => $this->t('Unread'),
			'$latest'          => $this->t('Latest post'),
			'$discover'        => $this->t('Discover groups'),
			'$discover_short'  => $this->t('Discover'),
			'$discover_server' => $this->t('Discover more groups on this server'),
			'$no_groups'       => $this->t('You are not subscribed to any groups.'),
			'$groups'          => $groups,
		]);
	}

	/**
	 * Fetches the starting posts of the given threads
	 *
	 * @param int   $uid    User id
	 * @param int[] $uriIds Uri-ids of the threads
	 *
	 * @return array Posts keyed by their uri-id
	 * @throws \Exception
	 */
	private function getParents(int $uid, array $uriIds): array
	{
		if (empty($uriIds)) {
			return [];
		}

		$parents = [];

		$posts = Post::selectForUser($uid, ['uri-id', 'guid', 'title', 'body'], DBA::mergeConditions(["(`uid` = 0 OR (`uid` = ? AND NOT `global`))", $uid], ['uri-id' => $uriIds]));
		while ($post = Post::fetch($posts)) {
			$parents[$post['uri-id']] = $post;
		}
		$this->database->close($posts);

		return $parents;
	}
}
