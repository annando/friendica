<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Util;

use Friendica\Core\Cache\Enum\Duration;
use Exception;
use Friendica\Core\System;
use Friendica\DI;
use Friendica\Protocol\ActivityPub;
use stdClass;
use Throwable;

/**
 * This class contain methods to work with JsonLD data
 */
class JsonLD
{
	/**
	 * Loader for LD-JSON validation
	 *
	 * @param $url
	 *
	 * @return mixed the loaded data
	 * @throws \JsonLdException
	 */
	public static function documentLoader($url)
	{
		switch ($url) {
			case 'https://w3id.org/security/v1':
				$url = DI::basePath() . '/static/security-v1.jsonld';
				break;
			case 'https://w3id.org/security/data-integrity/v1':
				$url = DI::basePath() . '/static/security-data-integrity-v1.jsonld';
				break;
			case 'https://w3id.org/security/multikey/v1':
				$url = DI::basePath() . '/static/security-multikey-v1.jsonld';
				break;
			case 'https://w3id.org/identity/v1':
				$url = DI::basePath() . '/static/identity-v1.jsonld';
				break;
			case 'https://www.w3.org/ns/activitystreams':
				$url = DI::basePath() . '/static/activitystreams.jsonld';
				break;
			case 'https://www.w3.org/ns/did/v1':
				$url = DI::basePath() . '/static/did-v1.jsonld';
				break;
			case 'https://funkwhale.audio/ns':
				$url = DI::basePath() . '/static/funkwhale.audio.jsonld';
				break;
			case 'http://schema.org':
				$url = DI::basePath() . '/static/schema.jsonld';
				break;
			case 'http://joinmastodon.org/ns':
				$url = DI::basePath() . '/static/joinmastodon.jsonld';
				break;
			case 'https://purl.archive.org/socialweb/webfinger':
				$url = DI::basePath() . '/static/socialweb-webfinger.jsonld';
				break;
			case 'https://purl.archive.org/socialweb/mls':
				$url = DI::basePath() . '/static/socialweb-mls.jsonld';
				break;
			case 'https://www.w3.org/ns/cid/v1':
				$url = DI::basePath() . '/static/cid-v1.jsonld';
				break;
			case 'https://w3id.org/security/data-integrity/v2':
				$url = DI::basePath() . '/static/data-integrity-v2.jsonld';
				break;
			default:
				DI::logger()->info('Got url', ['url' => $url]);
				break;
		}

		$data = DI::cache()->get('documentLoader:' . $url);
		if (is_null($data)) {
			$data = jsonld_default_document_loader($url);
			DI::cache()->set('documentLoader:' . $url, $data, Duration::DAY);
		}

		// The local contexts never change, so the processed contexts can be kept between the calls
		if (strpos($url, DI::basePath() . '/static/') === 0) {
			$data->tag = 'static';
		}

		return $data;
	}

	/**
	 * Checks if the given data contains suspicious commands that could be used in a malicious way, like @graph, @included or @reverse.
	 * If such commands are found, a warning is logged and false is returned.
	 *
	 * @param array $data
	 * @return boolean
	 */
	private static function isValidObject(array $data): bool
	{
		// Data integrity proofs are graphs by definition, so "@graph" is allowed there
		$command = self::findSuspiciousCommand(self::removeProofGraphs($data));
		if ($command !== null) {
			DI::logger()->warning('Document with suspicious commands.', ['command' => $command, 'document' => $data]);
			return false;
		}

		return true;
	}

	/**
	 * Searches the keys and values of the given data for @graph, @included or @reverse
	 *
	 * @param array $data
	 * @return string|null the found command
	 */
	private static function findSuspiciousCommand(array $data): ?string
	{
		$suspicious = ['@graph', '@included', '@reverse'];

		foreach ($data as $key => $value) {
			if (in_array((string) $key, $suspicious, true)) {
				return (string) $key;
			}
			if (is_array($value)) {
				$command = self::findSuspiciousCommand($value);
				if ($command !== null) {
					return $command;
				}
			} elseif (is_string($value) && in_array($value, $suspicious, true)) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * Replaces the graphs of compacted data integrity proofs ("w3id:proof") with their content
	 *
	 * @param array $data
	 * @return array
	 */
	private static function removeProofGraphs(array $data): array
	{
		foreach ($data as $key => $value) {
			if (!is_array($value)) {
				continue;
			}
			if ($key === 'w3id:proof') {
				$proofs = array_is_list($value) ? $value : [$value];
				foreach ($proofs as $index => $proof) {
					if (is_array($proof) && isset($proof['@graph']) && empty(array_diff(array_keys($proof), ['@graph', '@id']))) {
						$proofs[$index] = $proof['@graph'];
					}
				}
				$value = $proofs;
			}
			$data[$key] = self::removeProofGraphs($value);
		}

		return $data;
	}

	/**
	 * Normalises a given JSON array
	 *
	 * @param array $json
	 *
	 * @return mixed|bool normalized JSON string
	 * @throws Exception
	 */
	public static function normalize($json)
	{
		if (!is_array($json) || !self::isValidObject($json)) {
			return [];
		}

		jsonld_set_document_loader('Friendica\Util\JsonLD::documentLoader');

		$jsonobj = json_decode(json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

		try {
			$normalized = jsonld_normalize($jsonobj, ['algorithm' => 'URDNA2015', 'format' => 'application/nquads']);
		} catch (Throwable $e) {
			$normalized       = false;
			$messages         = [];
			$currentException = $e;
			do {
				$messages[] = $currentException->getMessage();
			} while ($currentException = $currentException->getPrevious());

			DI::logger()->notice('JsonLD normalize error', ['messages' => $messages]);
			DI::logger()->info('JsonLD normalize error', ['trace' => $e->getTraceAsString()]);
			DI::logger()->debug('JsonLD normalize error', ['jsonobj' => $jsonobj]);
		}

		return $normalized;
	}

	/**
	 * Compacts a given JSON array
	 *
	 * @param array $json
	 * @param bool  $logfailed
	 *
	 * @return array Compacted JSON array
	 * @throws Exception
	 */
	public static function compact($json, bool $logfailed = true): array
	{
		jsonld_set_document_loader('Friendica\Util\JsonLD::documentLoader');

		// In JSON-LD 1.1, terms that are defined with an object are only used as prefixes when they have "@prefix"
		$context = (object) [
			'as'        => 'https://www.w3.org/ns/activitystreams#',
			'w3id'      => 'https://w3id.org/security#',
			'ldp'       => (object) ['@id' => 'http://www.w3.org/ns/ldp#', '@type' => '@id', '@prefix' => true],
			'vcard'     => (object) ['@id' => 'http://www.w3.org/2006/vcard/ns#', '@type' => '@id', '@prefix' => true],
			'dfrn'      => (object) ['@id' => 'http://purl.org/macgirvin/dfrn/1.0/', '@type' => '@id', '@prefix' => true],
			'diaspora'  => (object) ['@id' => 'https://diasporafoundation.org/ns/', '@type' => '@id', '@prefix' => true],
			'ostatus'   => (object) ['@id' => 'http://ostatus.org#', '@type' => '@id', '@prefix' => true],
			'dc'        => (object) ['@id' => 'http://purl.org/dc/terms/', '@type' => '@id', '@prefix' => true],
			'toot'      => (object) ['@id' => 'http://joinmastodon.org/ns#', '@type' => '@id', '@prefix' => true],
			'litepub'   => (object) ['@id' => 'http://litepub.social/ns#', '@type' => '@id', '@prefix' => true],
			'sc'        => (object) ['@id' => 'http://schema.org#', '@type' => '@id', '@prefix' => true],
			'pt'        => (object) ['@id' => 'https://joinpeertube.org/ns#', '@type' => '@id', '@prefix' => true],
			'mobilizon' => (object) ['@id' => 'https://joinmobilizon.org/ns#', '@type' => '@id', '@prefix' => true],
			'fedibird'  => (object) ['@id' => 'http://fedibird.com/ns#', '@type' => '@id', '@prefix' => true],
			'misskey'   => (object) ['@id' => 'https://misskey-hub.net/ns#', '@type' => '@id', '@prefix' => true],
			'pixelfed'  => (object) ['@id' => 'http://pixelfed.org/ns#', '@type' => '@id', '@prefix' => true],
			'lemmy'     => (object) ['@id' => 'https://join-lemmy.org/ns#', '@type' => '@id', '@prefix' => true],
			'quote'     => (object) ['@id' => 'https://w3id.org/fep/044f#', '@type' => '@id', '@prefix' => true],
			'gts'       => (object) ['@id' => 'https://gotosocial.org/ns#', '@type' => '@id', '@prefix' => true],
		];

		$orig_json = $json;

		$jsonobj = self::fixInvalidJsonLD($json);

		try {
			$compacted = jsonld_compact($jsonobj, $context);
		} catch (Throwable $e) {
			$compacted = false;
			DI::logger()->notice('compacting error', ['msg' => $e->getMessage(), 'previous' => $e->getPrevious(), 'line' => $e->getLine()]);
			if ($logfailed && DI::config()->get('debug', 'ap_log_failure')) {
				$tempfile = tempnam(System::getTempPath(), 'failed-jsonld');
				file_put_contents($tempfile, json_encode(['json' => $orig_json, 'msg' => $e->getMessage(), 'previous' => $e->getPrevious()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
				DI::logger()->notice('Failed message stored', ['file' => $tempfile]);
			}
		}

		$json = json_decode(json_encode($compacted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), true);
		if (!is_array($json) || !self::isValidObject($json)) {
			return [];
		}

		return $json;
	}

	private static function fixInvalidJsonLD(array $json): stdClass
	{
		if (empty($json['@context'])) {
			$json['@context'] = ActivityPub::CONTEXT;
		}

		// Preparation for adding possibly missing content to the context
		if (is_string($json['@context'])) {
			$json['@context'] = [$json['@context']];
		}

		if (is_array($json['@context'])) {
			// Remove empty entries from the context (a problem with WriteFreely)
			$json['@context'] = array_values(array_filter($json['@context']));

			// Workaround for servers with missing context
			// See issue https://github.com/nextcloud/social/issues/330
			if (!in_array('https://w3id.org/security/v1', $json['@context'])) {
				DI::logger()->debug('Missing security context');
				$json['@context'][] = 'https://w3id.org/security/v1';
			}
		}

		// Issue 14448: Peertube uses a different schema URL.
		array_walk_recursive($json['@context'], function (&$value, $key): void {
			if ($key == 'sc' && $value == 'http://schema.org/') {
				DI::logger()->debug('schema.org path fixed');
				$value = 'http://schema.org#';
			}
		});

		// Bookwyrm transmits "id" fields with "null", which isn't allowed.
		array_walk_recursive($json, function (&$value, $key): void {
			if ($key == 'id' && is_null($value)) {
				DI::logger()->debug('Fixed null id');
				$value = '';
			}
		});

		return json_decode(json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
	}

	/**
	 * Fetches an element array from a JSON array
	 *
	 * @return array|null fetched element or null
	 */
	public static function fetchElementArray($array, $element, $key = null, $type = null, $type_value = null)
	{
		if (!isset($array[$element])) {
			return null;
		}

		// If it isn't an array yet, make it to one
		if (!is_array($array[$element]) || !is_int(key($array[$element]))) {
			$array[$element] = [$array[$element]];
		}

		$elements = [];

		foreach ($array[$element] as $entry) {
			if (!is_array($entry) || is_null($key)) {
				$item = $entry;
			} elseif (isset($entry[$key])) {
				$item = $entry[$key];
			}

			if (isset($item) && (is_null($type) || is_null($type_value) || isset($item[$type]) && $item[$type] == $type_value)) {
				$elements[] = $item;
			}
		}

		return $elements;
	}

	/**
	 * Fetches an element from a JSON array
	 *
	 * @param $array
	 * @param $element
	 * @param $key
	 * @param $type
	 * @param $type_value
	 *
	 * @return mixed|null fetched element. If the element is not found, null is returned.
	 */
	public static function fetchElement($array, $element, $key = '@id', $type = null, $type_value = null)
	{
		if (empty($array)) {
			return null;
		}

		if (!isset($array[$element])) {
			return null;
		}

		if (!is_array($array[$element])) {
			return $array[$element];
		}

		if (is_null($type) || is_null($type_value)) {
			$element_array = self::fetchElementArray($array, $element, $key);
			if (is_null($element_array)) {
				return null;
			}

			return array_shift($element_array);
		}

		$element_array = self::fetchElementArray($array, $element);
		if (is_null($element_array)) {
			return null;
		}

		foreach ($element_array as $entry) {
			if (isset($entry[$key]) && isset($entry[$type]) && ($entry[$type] == $type_value)) {
				return $entry[$key];
			}
		}

		return null;
	}
}
