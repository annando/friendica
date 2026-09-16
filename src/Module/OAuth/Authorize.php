<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Module\OAuth;

use Friendica\DI;
use Friendica\Module\BaseApi;
use Friendica\Security\OAuth;

/**
 * @see https://docs.joinmastodon.org/spec/oauth/
 * @see https://aaronparecki.com/oauth-2-simplified/
 */
class Authorize extends BaseApi
{
	private static $oauth_code = '';

	/**
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	protected function rawContent(array $request = [])
	{
		$request = $this->getRequest([
			'force_login'   => '', // Forces the user to re-login, which is necessary for authorizing with multiple accounts from the same instance.
			'response_type' => '', // Should be set equal to "code".
			'client_id'     => '', // Client ID, obtained during app registration.
			'client_secret' => '', // Isn't normally provided. We will use it if present.
			'redirect_uri'  => '', // Set a URI to redirect the user to. If this parameter is set to "urn:ietf:wg:oauth:2.0:oob" then the authorization code will be shown instead. Must match one of the redirect URIs declared during app registration.
			'scope'         => 'read', // List of requested OAuth scopes, separated by spaces (or by pluses, if using query parameters). Must be a subset of scopes declared during app registration. If not provided, defaults to "read".
			'state'         => '',
			'oauth_denied'  => '', // Internal marker set by the acknowledge page when the user declined the request.
		], $request);

		// client_id and redirect_uri have to be validated first: only once we know the redirect_uri is one the
		// application actually registered is it safe to report further errors by redirecting there (RFC 6749 4.1.2.1).
		if (empty($request['client_id']) || empty($request['redirect_uri'])) {
			$this->logger->warning('Incomplete request data', ['request' => $request]);
			$this->logAndJsonError(400, $this->errorFactory->BadRequest('invalid_request', $this->t('Incomplete request data')));
		}

		$application = OAuth::getApplication($request['client_id'], $request['client_secret'], $request['redirect_uri']);
		if (empty($application)) {
			$this->logger->warning('An application could not be fetched.', ['request' => $request]);
			$this->logAndJsonError(401, $this->errorFactory->Unauthorized('invalid_client', $this->t('Invalid data or unknown client')));
		}

		if (!empty($request['oauth_denied'])) {
			$this->logger->info('User denied the authorization request', ['application' => $application['name'], 'uid' => DI::userSession()->getLocalUserId()]);
			$this->authorizeError($request, 'access_denied', $this->t('The user has denied the request for authorization.'));
		}

		if ($request['response_type'] != 'code') {
			$this->logger->warning('Unsupported or missing response type', ['request' => $request]);
			$this->authorizeError($request, 'unsupported_response_type', $this->t('Unsupported or missing response type'));
		}

		// @todo Compare the application scope and requested scope
		// @todo Support PKCE (code_challenge/code_challenge_method), see RFC 7636

		$uid = DI::userSession()->getLocalUserId();

		if (!empty($request['force_login']) && !empty($uid)) {
			$this->logger->info('Force fresh login for OAuth authorization', ['uid' => $uid]);
			DI::session()->clear();
			// Drop force_login before rebuilding the redirect, otherwise we'd log the freshly authenticated user out again.
			unset($_REQUEST['force_login']);
			$uid = 0;
		}

		$redirect_request = $_REQUEST;
		unset($redirect_request['pagename']);
		$redirect = http_build_query($redirect_request);

		if (empty($uid)) {
			$this->logger->info('Redirect to login');
			DI::appHelper()->redirect('login?' . http_build_query(['return_authorize' => $redirect]));
		} else {
			$this->logger->info('Already logged in user', ['uid' => $uid]);
		}

		if (!OAuth::existsTokenForUser($application, $uid) && !DI::session()->get('oauth_acknowledge')) {
			$this->logger->info('Redirect to acknowledge');
			DI::appHelper()->redirect('oauth/acknowledge?' . http_build_query(['return_authorize' => $redirect, 'application' => $application['name']]));
		}

		DI::session()->remove('oauth_acknowledge');

		$token = OAuth::createTokenForUser($application, $uid, $request['scope']);
		if (!$token) {
			$this->authorizeError($request, 'server_error', $this->t('The token could not be created.'));
		}

		if ($application['redirect_uri'] != 'urn:ietf:wg:oauth:2.0:oob') {
			DI::appHelper()->redirect($request['redirect_uri'] . (strpos((string) $request['redirect_uri'], '?') ? '&' : '?') . http_build_query(['code' => $token['code'], 'state' => $request['state']]));
		}

		self::$oauth_code = $token['code'];
	}

	protected function content(array $request = []): string
	{
		if (empty(self::$oauth_code)) {
			return '';
		}

		return DI::l10n()->t('Please copy the following authentication code into your application and close this window: %s', self::$oauth_code);
	}

	/**
	 * Reports an OAuth error to the client, per RFC 6749 4.1.2.1: by redirecting to its (already validated)
	 * redirect_uri with "error"/"error_description"/"state" appended, or as a JSON error if that isn't possible
	 * because the client requested the out-of-band flow.
	 */
	private function authorizeError(array $request, string $error, string $error_description): never
	{
		if ((string) $request['redirect_uri'] === 'urn:ietf:wg:oauth:2.0:oob') {
			$this->logAndJsonError(400, $this->errorFactory->BadRequest($error, $error_description));
		}

		$params = ['error' => $error, 'error_description' => $error_description, 'state' => $request['state']];
		DI::appHelper()->redirect($request['redirect_uri'] . (strpos((string) $request['redirect_uri'], '?') ? '&' : '?') . http_build_query($params));
	}
}
