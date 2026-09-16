<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Module\WellKnown;

use Friendica\BaseModule;
use Friendica\DI;
use Friendica\Module\BaseApi;

/**
 * OAuth 2.0 Authorization Server Metadata, allowing clients to discover our OAuth endpoints instead of hard-coding them.
 * @see https://www.rfc-editor.org/rfc/rfc8414
 */
class OAuthAuthorizationServer extends BaseModule
{
	protected function rawContent(array $request = []): never
	{
		$metadata = [
			'issuer'                                     => DI::baseUrl(),
			'authorization_endpoint'                     => DI::baseUrl() . '/oauth/authorize',
			'token_endpoint'                             => DI::baseUrl() . '/oauth/token',
			'revocation_endpoint'                        => DI::baseUrl() . '/oauth/revoke',
			'scopes_supported'                           => [BaseApi::SCOPE_READ, BaseApi::SCOPE_WRITE, BaseApi::SCOPE_FOLLOW, BaseApi::SCOPE_PUSH],
			'response_types_supported'                   => ['code'],
			'grant_types_supported'                      => ['authorization_code', 'client_credentials'],
			'token_endpoint_auth_methods_supported'      => ['client_secret_post', 'client_secret_basic'],
			'revocation_endpoint_auth_methods_supported' => ['client_secret_post'],
			'service_documentation'                      => 'https://docs.joinmastodon.org/spec/oauth/',
		];

		$this->earlyJsonExit($metadata);
	}
}
