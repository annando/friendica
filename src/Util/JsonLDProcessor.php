<?php

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Util;

use Exception;
use Friendica\Core\System;
use Friendica\DI;
use Friendica\Protocol\ActivityPub;
use ML\JsonLD\DocumentLoaderInterface;
use ML\JsonLD\JsonLD as MLJsonLD;
use ML\JsonLD\NQuads;
use ML\JsonLD\RemoteDocument;
use stdClass;

/**
 * JSON-LD helper using the JsonLdProcessor class instance directly.
 */
class JsonLDProcessor
{
	/**
	 * Compacts a given JSON array
	 *
	 * @param array $json
	 * @param bool  $logfailed
	 *
	 * @return array Compacted JSON array
	 * @throws Exception
	 */
	public static function compact(array $json, bool $logfailed = true): array
	{
		$context = (object) [
			'as'        => 'https://www.w3.org/ns/activitystreams#',
			'w3id'      => 'https://w3id.org/security#',
			'ldp'       => (object) ['@id' => 'http://www.w3.org/ns/ldp#', '@type' => '@id'],
			'vcard'     => (object) ['@id' => 'http://www.w3.org/2006/vcard/ns#', '@type' => '@id'],
			'dfrn'      => (object) ['@id' => 'http://purl.org/macgirvin/dfrn/1.0/', '@type' => '@id'],
			'diaspora'  => (object) ['@id' => 'https://diasporafoundation.org/ns/', '@type' => '@id'],
			'ostatus'   => (object) ['@id' => 'http://ostatus.org#', '@type' => '@id'],
			'dc'        => (object) ['@id' => 'http://purl.org/dc/terms/', '@type' => '@id'],
			'toot'      => (object) ['@id' => 'http://joinmastodon.org/ns#', '@type' => '@id'],
			'litepub'   => (object) ['@id' => 'http://litepub.social/ns#', '@type' => '@id'],
			'sc'        => (object) ['@id' => 'http://schema.org#', '@type' => '@id'],
			'pt'        => (object) ['@id' => 'https://joinpeertube.org/ns#', '@type' => '@id'],
			'mobilizon' => (object) ['@id' => 'https://joinmobilizon.org/ns#', '@type' => '@id'],
			'fedibird'  => (object) ['@id' => 'http://fedibird.com/ns#', '@type' => '@id'],
			'misskey'   => (object) ['@id' => 'https://misskey-hub.net/ns#', '@type' => '@id'],
			'pixelfed'  => (object) ['@id' => 'http://pixelfed.org/ns#', '@type' => '@id'],
			'lemmy'     => (object) ['@id' => 'https://join-lemmy.org/ns#', '@type' => '@id'],
			'quote'     => (object) ['@id' => 'https://w3id.org/fep/044f#', '@type' => '@id'],
			'gts'       => (object) ['@id' => 'https://gotosocial.org/ns#', '@type' => '@id'],
		];

		$origJson = $json;
		$jsonobj  = self::fixInvalidJsonLD($json);

		try {
			$compacted = MLJsonLD::compact($jsonobj, $context, [
				'documentLoader' => self::getDocumentLoader(),
			]
);
			/*
			*/
		} catch (Exception $e) {
			$compacted = false;
			DI::logger()->notice('compacting error', ['msg' => $e->getMessage(), 'previous' => $e->getPrevious(), 'line' => $e->getLine()]);
			if ($logfailed && DI::config()->get('debug', 'ap_log_failure')) {
				$tempfile = tempnam(System::getTempPath(), 'failed-jsonld');
				file_put_contents($tempfile, json_encode(['json' => $origJson, 'msg' => $e->getMessage(), 'previous' => $e->getPrevious()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
				DI::logger()->notice('Failed message stored', ['file' => $tempfile]);
			}
		}

		$result = json_decode(json_encode($compacted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), true);
		if (!is_array($result) || !self::isValidObject($result)) {
			return [];
		}

		if ($result === false) {
			DI::logger()->notice('JSON encode->decode failed', ['orig_json' => $origJson, 'compacted' => $compacted]);
			$result = [];
		}

		return $result;
	}

	/**
	 * Normalises a given JSON array using RDF dataset canonicalization.
	 *
	 * @param array  $json
	 * @param string $algorithm Canonicalization algorithm, defaults to RDFC-1.0.
	 *
	 * @return mixed|bool normalized JSON string
	 * @throws Exception
	 */
	public static function normalize(array $json, string $algorithm = 'RDFC-1.0')
	{
		if (!self::isValidObject($json)) {
			return [];
		}

		$jsonobj = json_decode(json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

		try {
			$quads = MLJsonLD::toRdf($jsonobj, [
				'produceGeneralizedRdf' => false,
				'documentLoader'        => self::getDocumentLoader(),
			]);

			/*
							
			*/
			$serialized = (new NQuads())->serialize($quads);
			$normalized = jsonld_normalize($serialized, [
				'inputFormat' => 'application/nquads',
				'algorithm'   => $algorithm,
				'format'      => 'application/nquads',
			]);
		} catch (Exception $e) {
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

	private static function getDocumentLoader(): DocumentLoaderInterface
	{
		return new class implements DocumentLoaderInterface {
			public function loadDocument($url)
			{
				$document = JsonLD::documentLoader($url);
				$payload = $document->document;

				if (is_string($payload)) {
					$decoded = json_decode($payload);
					if (json_last_error() === JSON_ERROR_NONE) {
						$payload = $decoded;
					}
				}

				return new RemoteDocument(
					$document->documentUrl ?? $url,
					$payload,
					null,
					$document->contextUrl ?? null
				);
			}
		};
	}

	/**
	 * Checks if a JSON object contains suspicious commands.
	 */
	private static function isValidObject(array $data): bool
	{
		$valid = true;

		array_walk_recursive($data, function (&$value, $key) use ($data, &$valid) {
			$suspicious = ['@graph', '@included', '@reverse'];
			if (in_array((string) $key, $suspicious) || in_array((string) $value, $suspicious)) {
				DI::logger()->warning('Document with suspicious commands.', ['key' => $key, 'value' => $value, 'document' => $data]);
				$valid = false;
			}
		});

		return $valid;
	}

	private static function fixInvalidJsonLD(array $json): stdClass
	{
		if (empty($json['@context'])) {
			$json['@context'] = ActivityPub::CONTEXT;
		}

		if (!empty($json['@context']) && is_string($json['@context'])) {
			$json['@context'] = [$json['@context']];
		}

		if (!empty($json['@context']) && is_array($json['@context'])) {
			$json['@context'] = array_filter($json['@context']);

			if (!in_array('https://w3id.org/security/v1', $json['@context'])) {
				DI::logger()->debug('Missing security context');
				$json['@context'][] = 'https://w3id.org/security/v1';
			}
		}

		array_walk_recursive($json['@context'], function (&$value, $key) {
			if ($key == '@type' && $value == '@json') {
				DI::logger()->debug('"@json" converted to "@id"');
				$value = '@id';
			}
		});

		return json_decode(json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
	}
}
