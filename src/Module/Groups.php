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

		$stats   = $this->getGroupStats($uid, array_column($contacts, 'pid'));
		$threads = $this->getLatestThreads($uid, $stats);
		$parents = $this->getParents($uid, array_values($threads));
		$gravity = [Item::GRAVITY_PARENT, Item::GRAVITY_COMMENT];
		$latest  = $this->groupManager->getLatestPosts($uid, $this->groupManager->getThreadStats($uid, array_values($threads), $gravity), $gravity, ['guid', 'author-name', 'author-link', 'unseen']);

		$groups = [];
		foreach ($contacts as $contact) {
			$pid = $contact['pid'];

			$group = [
				'id'       => $contact['id'],
				'link'     => 'group/' . rawurlencode($contact['addr'] ?: (string) $contact['id']),
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

			$groups[] = $group;
		}

		usort($groups, fn ($a, $b): int => strcmp((string) $b['received'], (string) $a['received']));

		$tpl = Renderer::getMarkupTemplate('groups.tpl');
		return Renderer::replaceMacros($tpl, [
			'$title'     => $this->t('Groups'),
			'$group'     => $this->t('Group'),
			'$posts'     => $this->t('Posts'),
			'$unread'    => $this->t('Unread'),
			'$latest'    => $this->t('Latest post'),
			'$no_groups' => $this->t('You are not subscribed to any groups.'),
			'$groups'    => $groups,
		]);
	}

	/**
	 * Fetches the number of threads, the number of threads with unread posts and the latest activity per group in one go
	 *
	 * @param int   $uid   User id
	 * @param int[] $pcids Public contact ids of the groups
	 *
	 * @return array Statistics keyed by the public contact id
	 * @throws \Exception
	 */
	private function getGroupStats(int $uid, array $pcids): array
	{
		if (empty($pcids)) {
			return [];
		}

		$stats        = [];
		$placeholders = implode(', ', array_fill(0, count($pcids), '?'));

		$threads = $this->database->p(
			"SELECT `owner-id`, COUNT(*) AS `threads`, MAX(`commented`) AS `commented` FROM `post-thread-user`
				WHERE `uid` = ? AND `owner-id` IN (" . $placeholders . ")
				GROUP BY `owner-id`",
			$uid,
			...$pcids,
		);
		while ($row = $this->database->fetch($threads)) {
			$stats[$row['owner-id']] = [
				'threads'   => (int) $row['threads'],
				'unread'    => 0,
				'commented' => $row['commented'],
			];
		}
		$this->database->close($threads);

		$unread = $this->database->p(
			"SELECT `post-thread-user`.`owner-id`, COUNT(DISTINCT `post-user`.`parent-uri-id`) AS `unread` FROM `post-user`
				INNER JOIN `post-thread-user` ON `post-thread-user`.`uri-id` = `post-user`.`parent-uri-id` AND `post-thread-user`.`uid` = `post-user`.`uid`
				WHERE `post-user`.`uid` = ? AND `post-user`.`unseen` AND NOT `post-user`.`hidden` AND NOT `post-user`.`deleted`
				AND `post-user`.`gravity` IN (?, ?) AND `post-thread-user`.`owner-id` IN (" . $placeholders . ")
				GROUP BY `post-thread-user`.`owner-id`",
			$uid,
			Item::GRAVITY_PARENT,
			Item::GRAVITY_COMMENT,
			...$pcids,
		);
		while ($row = $this->database->fetch($unread)) {
			$stats[$row['owner-id']]['unread'] = (int) $row['unread'];
		}
		$this->database->close($unread);

		return $stats;
	}

	/**
	 * Fetches the most recently commented thread of each group
	 *
	 * @param int   $uid   User id
	 * @param array $stats Group statistics from getGroupStats()
	 *
	 * @return array Uri-id of the thread keyed by the public contact id
	 * @throws \Exception
	 */
	private function getLatestThreads(int $uid, array $stats): array
	{
		if (empty($stats)) {
			return [];
		}

		$latest = [];

		$condition = ['uid' => $uid, 'owner-id' => array_keys($stats), 'commented' => array_unique(array_column($stats, 'commented'))];

		$threads = $this->database->select('post-thread-user', ['owner-id', 'uri-id', 'commented'], $condition);
		while ($thread = $this->database->fetch($threads)) {
			if (!isset($latest[$thread['owner-id']]) && ($thread['commented'] === $stats[$thread['owner-id']]['commented'])) {
				$latest[$thread['owner-id']] = $thread['uri-id'];
			}
		}
		$this->database->close($threads);

		return $latest;
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

		$posts = Post::selectForUser($uid, ['uri-id', 'guid', 'title', 'body'], ['uid' => $uid, 'uri-id' => $uriIds]);
		while ($post = Post::fetch($posts)) {
			$parents[$post['uri-id']] = $post;
		}
		$this->database->close($posts);

		return $parents;
	}
}
