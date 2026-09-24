<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Content;

use Friendica\App\BaseURL;
use Friendica\Content\Contact\Repository\ContactByType;
use Friendica\Content\Text\HTML;
use Friendica\Core\Addon\AddonHelper;
use Friendica\Core\L10n;
use Friendica\Core\Renderer;
use Friendica\Core\Session\Capability\IHandleUserSessions;
use Friendica\Database\Database;
use Friendica\Model\Contact;
use Friendica\Model\Item;
use Friendica\Model\Post;

/**
 * This class handles methods related to the group functionality
 */
class GroupManager
{
	private const CONTACT_TYPES = [Contact::TYPE_COMMUNITY];

	public function __construct(
		private readonly ContactByType $contacts,
		private readonly AddonHelper $addonHelper,
		private readonly BaseURL $baseUrl,
		private readonly L10n $l10n,
		private readonly IHandleUserSessions $session,
		private readonly Database $database,
	) {}

	/**
	 * Function to list all groups a user is connected with
	 *
	 * @param int     $uid         of the profile owner
	 * @param boolean $lastitem    Sort by lastitem
	 * @param boolean $showhidden  Show groups which are not hidden
	 * @param boolean $showprivate Show private groups
	 *
	 * @return array
	 *    'url'    => group url
	 *    'name'    => group name
	 *    'id'    => number of the key from the array
	 *    'micro' => contact photo in format micro
	 *    'thumb' => contact photo in format thumb
	 * @throws \Exception
	 */
	public function getList(int $uid, bool $lastitem, bool $showhidden = true, bool $showprivate = false): array
	{
		return $this->contacts->selectForUser($uid, self::CONTACT_TYPES, $lastitem, $showhidden, $showprivate);
	}


	/**
	 * Group list widget
	 *
	 * Sidebar widget to show subscribed Friendica groups. If activated
	 * in the settings, it appears in the network page sidebar
	 *
	 * @param int $uid The ID of the User
	 * @return string
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 * @throws \ImagickException
	 */
	public function widget(int $uid): string
	{
		//sort by last updated item
		$contacts      = $this->getList($uid, true, true, true);
		$total         = count($contacts);
		$visibleGroups = 10;

		$id = 0;

		$entries = [];

		foreach ($contacts as $contact) {
			$entry = [
				'url'          => 'contact/' . $contact['id'] . '/conversations',
				'external_url' => Contact::magicLinkByContact($contact),
				'name'         => $contact['name'],
				'cid'          => $contact['id'],
				'micro'        => $this->baseUrl->remove(Contact::getMicro($contact)),
				'id'           => ++$id,
			];
			$entries[] = $entry;
		}

		$tpl = Renderer::getMarkupTemplate('widget/group_list.tpl');

		return Renderer::replaceMacros(
			$tpl,
			[
				'$title'                         => $this->l10n->t('Groups'),
				'$groups'                        => $entries,
				'$link_desc'                     => $this->l10n->t('External link to group'),
				'$new_group_page'                => 'register/?type=group',
				'$total'                         => $total,
				'$visible_groups'                => $visibleGroups,
				'$showless'                      => $this->l10n->t('show less'),
				'$showmore'                      => $this->l10n->t('show more'),
				'$create_new_group'              => $this->l10n->t('Create new group'),
				'$addon_group_directory_enabled' => $this->addonHelper->isAddonEnabled('groupdirectory'),
				'$visit_groupdirectory'          => $this->l10n->t('Find groups to join'),
			],
		);
	}

	/**
	 * Format group list as contact block
	 *
	 * This function is used to show the group list in
	 * the advanced profile.
	 *
	 * @param int $uid The ID of the User
	 * @return string
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 * @throws \ImagickException
	 */
	public function profileAdvanced(int $uid): string
	{
		if (!Feature::isEnabled($uid, Feature::GROUPS)) {
			return '';
		}

		$o = '';

		// placeholder in case somebody wants configurability
		$show_total = 9999;

		//don't sort by last updated item
		$lastitem = false;

		$contacts = $this->getList($uid, $lastitem, false, false);

		$total_shown = 0;
		foreach ($contacts as $contact) {
			$o .= HTML::micropro($contact, true, 'grouplist-profile-advanced');
			$total_shown++;
			if ($total_shown == $show_total) {
				break;
			}
		}

		return $o;
	}

	/**
	 * count unread group items
	 *
	 * Count unread items of connected groups and private groups
	 *
	 * @return array
	 *    'id' => contact id
	 *    'name' => contact/group name
	 *    'count' => counted unseen group items
	 * @throws \Exception
	 */
	public function countUnseenItems(): array
	{
		$uid = $this->session->getLocalUserId();
		if (!is_int($uid)) {
			return [];
		}

		return $this->contacts->countUnseenItems($uid, self::CONTACT_TYPES);
	}

	/**
	 * Marks all posts in the threads of a group as seen
	 *
	 * @param int $uid  User id
	 * @param int $pcid Public contact id of the group
	 * @throws \Exception
	 */
	public function markSeen(int $uid, int $pcid): void
	{
		Item::update(['unseen' => false], ["`uid` = ? AND `unseen` AND `parent-uri-id` IN (SELECT `uri-id` FROM `post-thread-user` WHERE `uid` = ? AND `owner-id` = ?)", $uid, $uid, $pcid]);
	}

	/**
	 * Fetches the number of posts, the number of unread posts and the date of the latest post per thread in one go
	 *
	 * @param int   $uid          User id
	 * @param int[] $parentUriIds Uri-ids of the threads
	 * @param int[] $gravity      Gravities of the posts that are counted
	 *
	 * @return array Statistics keyed by the uri-id of the thread
	 * @throws \Exception
	 */
	public function getThreadStats(int $uid, array $parentUriIds, array $gravity): array
	{
		if (empty($parentUriIds)) {
			return [];
		}

		$stats = [];

		$posts = $this->database->p(
			"SELECT `parent-uri-id`, COUNT(*) AS `posts`, SUM(`unseen`) AS `unread`, MAX(`received`) AS `received` FROM `post-user`
				WHERE `uid` = ? AND `visible` AND NOT `deleted` AND `gravity` IN (" . implode(', ', array_fill(0, count($gravity), '?')) . ")
				AND `parent-uri-id` IN (" . implode(', ', array_fill(0, count($parentUriIds), '?')) . ")
				GROUP BY `parent-uri-id`",
			$uid,
			...$gravity,
			...$parentUriIds,
		);
		while ($row = $this->database->fetch($posts)) {
			$stats[$row['parent-uri-id']] = [
				'posts'    => (int) $row['posts'],
				'unread'   => (int) $row['unread'],
				'received' => $row['received'],
			];
		}
		$this->database->close($posts);

		return $stats;
	}

	/**
	 * Fetches the latest post of each thread
	 *
	 * @param int   $uid     User id
	 * @param array $stats   Thread statistics from getThreadStats()
	 * @param int[] $gravity Gravities of the posts
	 * @param array $fields  Fields of the posts
	 *
	 * @return array Posts keyed by the uri-id of the thread
	 * @throws \Exception
	 */
	public function getLatestPosts(int $uid, array $stats, array $gravity, array $fields): array
	{
		if (empty($stats)) {
			return [];
		}

		$latest = [];

		$condition = [
			'uid'           => $uid,
			'parent-uri-id' => array_keys($stats),
			'received'      => array_unique(array_column($stats, 'received')),
			'gravity'       => $gravity,
			'visible'       => true,
			'deleted'       => false,
		];

		$posts = Post::selectForUser($uid, array_merge($fields, ['parent-uri-id', 'received']), $condition);
		while ($post = Post::fetch($posts)) {
			// Different threads can share the same date, so we have to check the thread as well
			if (!isset($latest[$post['parent-uri-id']]) && ($post['received'] === $stats[$post['parent-uri-id']]['received'])) {
				$latest[$post['parent-uri-id']] = $post;
			}
		}
		$this->database->close($posts);

		return $latest;
	}
}
