<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Test\Unit\Util;

use Dice\Dice;
use Friendica\Core\Cache\Capability\ICanCache;
use Friendica\Core\Config\Capability\IManageConfigValues;
use Friendica\DI;
use Friendica\Util\BasePath;
use Friendica\Util\JsonLD;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * JsonLD utility test class
 */
class JsonLDTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		// The JSON-LD document loader needs the base path and a cache
		$cache = $this->createMock(ICanCache::class);
		$cache->method('get')->willReturn(null);

		$config = $this->createMock(IManageConfigValues::class);
		$config->method('get')->willReturn(null);

		$dice = $this->createMock(Dice::class);
		$dice->method('create')->willReturnCallback(fn (string $name): object => match ($name) {
			LoggerInterface::class     => new NullLogger(),
			ICanCache::class           => $cache,
			IManageConfigValues::class => $config,
			BasePath::class            => new BasePath(dirname(__DIR__, 3)),
			default                    => throw new \InvalidArgumentException('Unexpected DI::create() call for class: ' . $name),
		});

		DI::init($dice, true);
	}

	public function testFetchElementArrayNotFound(): void
	{
		$object = [];

		$data = JsonLD::fetchElementArray($object, 'field');
		self::assertNull($data);
	}

	public function testFetchElementArrayFoundEmptyArray(): void
	{
		$object = ['field' => []];

		$data = JsonLD::fetchElementArray($object, 'field');
		self::assertSame([[]], $data);
	}

	public function testFetchElementArrayFoundID(): void
	{
		$object = ['field' => ['value1', ['@id' => 'value2'], ['@id' => 'value3']]];

		$data = JsonLD::fetchElementArray($object, 'field', '@id');
		self::assertSame(['value1', 'value2', 'value3'], $data);
	}

	public function testFetchElementArrayFoundID2(): void
	{
		$object = ['field' => [['subfield11' => 'value11', 'subfield12' => 'value12'],
			['subfield21' => 'value21', 'subfield22' => 'value22'],
			'value3', ['@id' => 'value4', 'subfield42' => 'value42']]];

		$data = JsonLD::fetchElementArray($object, 'field', '@id');
		self::assertSame(['value3', 'value4'], $data);
	}

	public function testFetchElementArrayFoundArrays(): void
	{
		$object = ['field' => [['subfield11' => 'value11', 'subfield12' => 'value12'],
			['subfield21' => 'value21', 'subfield22' => 'value22']]];

		$expect = [['subfield11' => 'value11', 'subfield12' => 'value12'],
			['subfield21' => 'value21', 'subfield22' => 'value22']];

		$data = JsonLD::fetchElementArray($object, 'field');
		self::assertSame($expect, $data);
	}

	public function testFetchElementArrayTypeValue(): void
	{
		$object = ['field' => [['subfield11' => 'value11', 'subfield12' => 'value12'],
			['subfield21' => 'value21', 'subfield22' => 'value22']]];

		$expect = [['subfield11' => 'value11', 'subfield12' => 'value12']];

		$data = JsonLD::fetchElementArray($object, 'field', null, 'subfield11', 'value11');
		self::assertSame($expect, $data);
	}

	public function testFetchElementNotFound(): void
	{
		$object = [];

		$data = JsonLD::fetchElement($object, 'field');
		self::assertNull($data);
	}

	public function testFetchElementFound(): void
	{
		$object = ['field' => 'value'];

		$data = JsonLD::fetchElement($object, 'field');
		self::assertSame('value', $data);
	}

	public function testFetchElementFoundEmptyString(): void
	{
		$object = ['field' => ''];

		$data = JsonLD::fetchElement($object, 'field');
		self::assertSame('', $data);
	}

	public function testFetchElementKeyFoundEmptyArray(): void
	{
		$object = ['field' => ['content' => []]];

		$data = JsonLD::fetchElement($object, 'field', 'content');
		self::assertSame([], $data);
	}

	public function testFetchElementFoundID(): void
	{
		$object = ['field' => ['field2' => 'value2', '@id' => 'value', 'field3' => 'value3']];

		$data = JsonLD::fetchElement($object, 'field');
		self::assertSame('value', $data);
	}

	public function testFetchElementType(): void
	{
		$object = ['source' => ['content' => 'body', 'mediaType' => 'text/bbcode']];

		$data = JsonLD::fetchElement($object, 'source', 'content', 'mediaType', 'text/bbcode');
		self::assertSame('body', $data);
	}

	public function testFetchElementTypeValueNotFound(): void
	{
		$object = ['source' => ['content' => 'body', 'mediaType' => 'text/html']];

		$data = JsonLD::fetchElement($object, 'source', 'content', 'mediaType', 'text/bbcode');
		self::assertNull($data);
	}

	public function testFetchElementTypeNotFound(): void
	{
		$object = ['source' => ['content' => 'body', 'mediaType' => 'text/html']];

		$data = JsonLD::fetchElement($object, 'source', 'content', 'mediaType2', 'text/html');
		self::assertNull($data);
	}

	public function testFetchElementKeyWithoutType(): void
	{
		$object = ['source' => ['content' => 'body', 'mediaType' => 'text/bbcode']];

		$data = JsonLD::fetchElement($object, 'source', 'content');
		self::assertSame('body', $data);
	}

	public function testFetchElementTypeArray(): void
	{
		$object = ['source' => [['content' => 'body2', 'mediaType' => 'text/html'],
			['content' => 'body', 'mediaType' => 'text/bbcode']]];

		$data = JsonLD::fetchElement($object, 'source', 'content', 'mediaType', 'text/bbcode');
		self::assertSame('body', $data);
	}

	public function testFetchElementTypeValueArrayNotFound(): void
	{
		$object = ['source' => [['content' => 'body2', 'mediaType' => 'text/html'],
			['content' => 'body', 'mediaType' => 'text/bbcode']]];

		$data = JsonLD::fetchElement($object, 'source', 'content', 'mediaType', 'text/markdown');
		self::assertNull($data);
	}

	public function testFetchElementTypeArrayNotFound(): void
	{
		$object = ['source' => [['content' => 'body2', 'mediaType' => 'text/html'],
			['content' => 'body', 'mediaType' => 'text/bbcode']]];

		$data = JsonLD::fetchElement($object, 'source', 'content', 'mediaType2', 'text/bbcode');
		self::assertNull($data);
	}

	/**
	 * A note like Mastodon sends it, with terms that are defined in the inline context
	 */
	private static function mastodonNote(): array
	{
		return [
			'@context' => [
				'https://www.w3.org/ns/activitystreams',
				[
					'ostatus'           => 'http://ostatus.org#',
					'atomUri'           => 'ostatus:atomUri',
					'conversation'      => 'ostatus:conversation',
					'sensitive'         => 'as:sensitive',
					'gts'               => 'https://gotosocial.org/ns#',
					'interactionPolicy' => ['@id' => 'gts:interactionPolicy', '@type' => '@id'],
				],
				'https://w3id.org/security/v1',
			],
			'id'           => 'https://mastodon.example/users/alice/statuses/1',
			'type'         => 'Note',
			'attributedTo' => 'https://mastodon.example/users/alice',
			'to'           => ['https://www.w3.org/ns/activitystreams#Public'],
			'content'      => '<p>Hello</p>',
			'sensitive'    => false,
			'atomUri'      => 'https://mastodon.example/users/alice/statuses/1',
			'conversation' => 'tag:mastodon.example,2026-10-04:objectId=1:objectType=Conversation',
		];
	}

	public function testCompactNote(): void
	{
		$compacted = JsonLD::compact(self::mastodonNote());

		self::assertSame('as:Note', $compacted['@type']);
		self::assertSame('https://mastodon.example/users/alice/statuses/1', $compacted['ostatus:atomUri']);
		self::assertSame('tag:mastodon.example,2026-10-04:objectId=1:objectType=Conversation', $compacted['ostatus:conversation']);
		self::assertFalse($compacted['as:sensitive']);
	}

	public function testCompactActor(): void
	{
		$compacted = JsonLD::compact([
			'@context'     => ['https://www.w3.org/ns/activitystreams', 'https://w3id.org/security/v1', ['toot' => 'http://joinmastodon.org/ns#', 'discoverable' => 'toot:discoverable']],
			'id'           => 'https://mastodon.example/users/alice',
			'type'         => 'Person',
			'inbox'        => 'https://mastodon.example/users/alice/inbox',
			'discoverable' => true,
			'publicKey'    => ['id' => 'https://mastodon.example/users/alice#main-key', 'owner' => 'https://mastodon.example/users/alice', 'publicKeyPem' => 'PEM'],
		]);

		self::assertSame('https://mastodon.example/users/alice/inbox', JsonLD::fetchElement($compacted, 'ldp:inbox', '@id'));
		self::assertTrue($compacted['toot:discoverable']);
		self::assertSame('PEM', JsonLD::fetchElement($compacted['w3id:publicKey'], 'w3id:publicKeyPem', '@value'));
	}

	public function testCompactUndefinedTerms(): void
	{
		// Terms that aren't defined in any context are mapped to blank node identifiers by the "@vocab" of the ActivityStreams context
		$compacted = JsonLD::compact([
			'@context' => ['https://www.w3.org/ns/activitystreams', 'https://w3id.org/security/v1'],
			'id'       => 'https://books.example/user/alice/review/1',
			'type'     => 'Review',
			'language' => ['identifier' => 'fr', 'name' => 'French'],
		]);

		self::assertSame('_:Review', $compacted['@type']);
		self::assertSame('fr', $compacted['_:language']['_:identifier']);
	}

	public static function dataInvalidJsonLD(): array
	{
		return [
			'null id' => [
				'json' => [
					'@context'   => ['https://www.w3.org/ns/activitystreams', 'https://w3id.org/security/v1'],
					'id'         => 'https://books.example/user/alice/review/1',
					'type'       => 'Article',
					'attachment' => [['id' => null, 'type' => 'Document', 'url' => 'https://books.example/images/cover.jpg']],
				],
			],
			'empty context entry' => [
				'json' => [
					'@context' => ['https://www.w3.org/ns/activitystreams', '', 'https://w3id.org/security/v1'],
					'id'       => 'https://books.example/user/alice/review/1',
					'type'     => 'Article',
				],
			],
			'@json type' => [
				'json' => [
					'@context' => ['https://www.w3.org/ns/activitystreams', ['pt' => 'https://joinpeertube.org/ns#', 'hashes' => ['@id' => 'pt:hashes', '@type' => '@json']]],
					'id'       => 'https://books.example/user/alice/review/1',
					'type'     => 'Article',
					'hashes'   => ['a' => 'b'],
				],
			],
			'no context' => [
				'json' => [
					'id'   => 'https://books.example/user/alice/review/1',
					'type' => 'Article',
				],
			],
		];
	}

	#[DataProvider('dataInvalidJsonLD')]
	public function testCompactInvalidJsonLD(array $json): void
	{
		$compacted = JsonLD::compact($json);

		self::assertSame('https://books.example/user/alice/review/1', $compacted['@id']);
		self::assertSame('as:Article', $compacted['@type']);
	}

	public function testCompactDataIntegrityProof(): void
	{
		// Proofs are graphs, they mustn't cause the document to be rejected
		$compacted = JsonLD::compact([
			'@context' => ['https://www.w3.org/ns/activitystreams', 'https://w3id.org/security/data-integrity/v2'],
			'id'       => 'https://mitra.example/users/alice',
			'type'     => 'Person',
			'proof'    => [
				'type'               => 'DataIntegrityProof',
				'cryptosuite'        => 'eddsa-jcs-2022',
				'proofPurpose'       => 'assertionMethod',
				'proofValue'         => 'z5Croj8RckNeLHQjYSEZE9kb2VzGzBaCHwnqqhv79cd37ZPGirrtyGrJkh4tKWxyL7vgnhuJSGhhQxZYnu9wMBJzc',
				'verificationMethod' => 'https://mitra.example/users/alice#ed25519-key',
			],
		]);

		self::assertSame('https://mitra.example/users/alice', $compacted['@id']);
		self::assertSame('w3id:DataIntegrityProof', $compacted['w3id:proof']['@graph']['@type']);
	}

	public function testCompactSuspiciousGraph(): void
	{
		$compacted = JsonLD::compact([
			'@context' => ['https://www.w3.org/ns/activitystreams'],
			'id'       => 'https://mastodon.example/users/alice/statuses/1',
			'type'     => 'Note',
			'@graph'   => [['id' => 'https://mastodon.example/users/bob/statuses/2', 'type' => 'Note']],
		]);

		self::assertSame([], $compacted);
	}

	public function testNormalize(): void
	{
		$normalized = JsonLD::normalize(self::mastodonNote());

		self::assertStringContainsString('<https://mastodon.example/users/alice/statuses/1> <http://ostatus.org#atomUri> "https://mastodon.example/users/alice/statuses/1" .', $normalized);
		self::assertStringContainsString('<https://mastodon.example/users/alice/statuses/1> <https://www.w3.org/ns/activitystreams#sensitive> "false"^^<http://www.w3.org/2001/XMLSchema#boolean> .', $normalized);
	}

	public function testNormalizeDoesNotDependOnPreviousDocuments(): void
	{
		$normalized = JsonLD::normalize(self::mastodonNote());

		// With the old library, a document with a different order of the contexts changed the result
		JsonLD::normalize([
			'@context' => ['https://www.w3.org/ns/activitystreams', 'https://w3id.org/security/v1', ['toot' => 'http://joinmastodon.org/ns#']],
			'id'       => 'https://mastodon.example/users/bob',
			'type'     => 'Person',
		]);

		self::assertSame($normalized, JsonLD::normalize(self::mastodonNote()));
	}
}
