<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Module\OAuth;

use Friendica\Database\DBA;
use Friendica\Module\BaseApi;

/**
 * @see https://docs.joinmastodon.org/spec/oauth/
 */
class Revoke extends BaseApi
{
	/**
	 * @internal
	 */
	protected function checkScope(): void {}

	protected function post(array $request = [])
	{
		$request = $this->getRequest([
			'client_id'     => '', // Client ID, obtained during app registration
			'client_secret' => '', // Client secret, obtained during app registration
			'token'         => '', // The previously obtained token, to be invalidated
		], $request);

		$application = DBA::selectFirst('application', ['id'], ['client_id' => $request['client_id'], 'client_secret' => $request['client_secret']]);
		if (empty($application['id'])) {
			$this->logger->notice('Unknown client', ['client_id' => $request['client_id']]);
			$this->logAndJsonError(401, $this->errorFactory->Unauthorized('invalid_client', $this->t('Invalid client credentials')));
		}

		// Revocation is idempotent, see RFC 7009 section 2.2: revoking an unknown or already revoked token isn't an error.
		DBA::delete('application-token', ['application-id' => $application['id'], 'access_token' => $request['token']]);
		$this->earlyJsonExit([]);
	}
}
