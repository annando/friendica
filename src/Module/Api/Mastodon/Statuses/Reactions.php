<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Module\Api\Mastodon\Statuses;

use Friendica\Content\Smilies;
use Friendica\Database\DBA;
use Friendica\DI;
use Friendica\Model\Item;
use Friendica\Model\Post;
use Friendica\Module\BaseApi;

/**
 * Emoji reactions, only Unicode emojis are supported
 *
 * @see https://docs-develop.pleroma.social/backend/development/API/differences_in_mastoapi_responses/#emoji-reactions
 */
class Reactions extends BaseApi
{
	protected function rawContent(array $request = [])
	{
		$uid = self::getCurrentUserID();

		if (empty($this->parameters['id'])) {
			$this->logAndJsonError(422, $this->errorFactory->UnprocessableEntity());
		}

		if (!Post::exists(['uri-id' => $this->parameters['id'], 'uid' => [0, $uid]])) {
			$this->logAndJsonError(404, $this->errorFactory->RecordNotFound());
		}

		$emoji     = rawurldecode($this->parameters['emoji'] ?? '');
		$reactions = [];
		foreach (Post\Counts::getReactions($this->parameters['id'], $uid) as $reaction) {
			if (($emoji !== '') && ($reaction['name'] !== $emoji)) {
				continue;
			}

			$accounts = [];
			foreach ($reaction['account_ids'] as $id) {
				$accounts[] = DI::mstdnAccount()->createFromContactId($id, $uid);
			}

			$reactions[] = ['name' => $reaction['name'], 'count' => $reaction['count'], 'me' => $reaction['me'], 'accounts' => $accounts];
		}

		$this->earlyJsonExit($reactions);
	}

	protected function put(array $request = [])
	{
		$this->react(false);
	}

	/**
	 * "react" and "unreact" endpoints of Fedibird, kmyblue and Chuckya
	 */
	protected function post(array $request = [])
	{
		$this->react(($this->parameters['action'] ?? '') === 'unreact');
	}

	protected function delete(array $request = [])
	{
		$this->react(true);
	}

	private function react(bool $undo)
	{
		$this->checkAllowedScope(self::SCOPE_WRITE);
		$uid = self::getCurrentUserID();

		$emoji = rawurldecode($this->parameters['emoji'] ?? '');
		if (empty($this->parameters['id']) || !Smilies::isReaction($emoji)) {
			$this->logAndJsonError(422, $this->errorFactory->UnprocessableEntity());
		}

		$item = Post::selectOriginalForUser($uid, ['id', 'uri-id'], ['uri-id' => $this->parameters['id'], 'uid' => [$uid, 0]]);
		if (!DBA::isResult($item)) {
			$this->logAndJsonError(404, $this->errorFactory->RecordNotFound());
		}

		if (!Item::performActivity($item['id'], $undo ? 'unreact' : 'react', $uid, null, null, null, null, $emoji)) {
			$this->logAndJsonError(422, $this->errorFactory->UnprocessableEntity());
		}

		$isReblog = $item['uri-id'] != $this->parameters['id'];
		$this->earlyJsonExit(DI::mstdnStatus()->createFromUriId($this->parameters['id'], $uid, $isReblog)->toArray());
	}
}
