<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Module\Notifications;

use Friendica\App\Arguments;
use Friendica\App\BaseURL;
use Friendica\BaseModule;
use Friendica\Content\BoundariesPager;
use Friendica\Content\Conversation\ConversationRenderer;
use Friendica\Content\Nav;
use Friendica\Core\L10n;
use Friendica\Core\Renderer;
use Friendica\Core\Session\Capability\IHandleUserSessions;
use Friendica\Factory\Api\Mastodon\Notification as MastodonNotificationFactory;
use Friendica\Model\Contact;
use Friendica\Model\Post;
use Friendica\Module\Response;
use Friendica\Module\Security\Login;
use Friendica\Navigation\Notifications\Entity\Notification as NotificationEntity;
use Friendica\Navigation\Notifications\Repository\Notification as NotificationRepository;
use Friendica\Object\Api\Mastodon\Notification as MastodonNotification;
use Friendica\Util\Profiler;
use Friendica\Util\Proxy;
use Psr\Log\LoggerInterface;

/**
 * All kinds of notifications as one list, similar to the Mastodon notifications.
 *
 * Mentions and replies are shown as the post itself, all other notifications get a line that
 * tells what happened, followed by the post it is about.
 */
class Stream extends BaseModule
{
	private const ITEMS_PER_PAGE = 20;

	public function __construct(private readonly NotificationRepository $notification, private readonly ConversationRenderer $conversationRenderer, private readonly IHandleUserSessions $session, L10n $l10n, BaseURL $baseUrl, Arguments $args, LoggerInterface $logger, Profiler $profiler, Response $response, array $server, array $parameters = [])
	{
		parent::__construct($l10n, $baseUrl, $args, $logger, $profiler, $response, $server, $parameters);
	}

	protected function content(array $request = []): string
	{
		$uid = $this->session->getLocalUserId();
		if (!$uid) {
			return Login::form('notifications/stream');
		}

		Nav::setSelected('notifications');

		$request = $this->checkDefaults([
			'min_id' => null,
			'max_id' => null,
			'mode'   => '',
		], $request);

		$condition = ["`uid` = ? AND NOT `dismissed` AND (NOT `type` IN (?, ?)) AND NOT EXISTS(SELECT `cid` FROM `user-contact` WHERE `user-contact`.`cid` = `notification`.`actor-id` AND `user-contact`.`uid` = `notification`.`uid` AND (`is-blocked` OR `blocked`))",
			$uid, Post\UserNotification::TYPE_ACTIVITY_PARTICIPATION, Post\UserNotification::TYPE_COMMENT_PARTICIPATION];

		$notifications = $this->notification->selectByBoundaries(
			$condition,
			['order' => ['id' => true]],
			isset($request['min_id']) ? (int) $request['min_id'] : null,
			isset($request['max_id']) ? (int) $request['max_id'] : null,
			self::ITEMS_PER_PAGE,
		);

		$output = '';
		$first  = null;
		$last   = null;
		$count  = 0;

		foreach ($notifications as $notification) {
			$first ??= $notification->id;
			$last    = $notification->id;
			$count++;

			try {
				$output .= $this->renderNotification($notification, $uid);
			} catch (\Exception $e) {
				$this->logger->notice('Notification could not be rendered', ['id' => $notification->id, 'code' => $e->getCode(), 'message' => $e->getMessage()]);
			}
		}

		if (!$count) {
			return '<p class="notification-stream-empty">' . $this->t('No notifications.') . '</p>';
		}

		$pager = new BoundariesPager($this->l10n, $this->args->getQueryString(), (string) $first, (string) $last, self::ITEMS_PER_PAGE);

		return $output . $pager->renderMinimal($count);
	}

	private function renderNotification(NotificationEntity $notification, int $uid): string
	{
		$type = MastodonNotificationFactory::getType($notification);
		if ($type === '') {
			return '';
		}

		$post = $notification->targetUriId ? $this->conversationRenderer->renderItemByUriId($notification->targetUriId, $uid) : '';

		$actor = Contact::getById($notification->actorId, ['name', 'nick']);
		$name  = $actor['name'] ?: ($actor['nick'] ?? '');
		$link  = '<a href="contact/' . $notification->actorId . '">' . htmlspecialchars($name) . '</a>';

		switch ($type) {
			case MastodonNotification::TYPE_FOLLOW:
				$icon = 'ri-user-add-line';
				$text = $this->t('%s started following you', $link);
				break;
			case MastodonNotification::TYPE_INTRODUCTION:
				$icon = 'ri-user-add-line';
				$text = $this->t('%s requested to follow you', '<a href="notifications/intros">' . htmlspecialchars($name) . '</a>');
				break;
			case MastodonNotification::TYPE_RESHARE:
				$icon = 'ri-repeat-line';
				$text = $this->t('%s shared your post', $link);
				break;
			case MastodonNotification::TYPE_LIKE:
				$icon = 'ri-star-fill';
				$text = $this->t('%s reacted to your post', $link);
				break;
			default:
				// Mentions and replies are shown as the post alone
				if ($post === '') {
					return '';
				}
				return Renderer::replaceMacros(Renderer::getMarkupTemplate('notifications/stream_item.tpl'), [
					'$type' => $type,
					'$post' => $post,
				]);
		}

		return Renderer::replaceMacros(Renderer::getMarkupTemplate('notifications/stream_item.tpl'), [
			'$type'   => $type,
			'$icon'   => $icon,
			'$avatar' => Contact::getAvatarUrlForId($notification->actorId, Proxy::SIZE_MICRO),
			'$text'   => $text,
			'$post'   => $post,
		]);
	}
}
