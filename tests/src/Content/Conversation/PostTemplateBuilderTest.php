<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Test\src\Content\Conversation;

use Friendica\Content\Conversation\PostTemplateBuilder;
use Friendica\Content\Feature;
use Friendica\DI;
use Friendica\Test\ApiTestCase;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Regression tests for #15913: replying to a post must only pre-fill the "[abstract=apub]" content
 * warning when the post being replied to is actually marked "sensitive", not merely because it has
 * a summary.
 */
class PostTemplateBuilderTest extends ApiTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		DI::pConfig()->set(self::SELF_USER['id'], 'feature', Feature::ADD_ABSTRACT, true);
	}

	private function getDefaultText(array $item): string
	{
		$builder = DI::postTemplateBuilder();

		$uid = new ReflectionProperty(PostTemplateBuilder::class, 'uid');
		$uid->setValue($builder, self::SELF_USER['id']);

		return (new ReflectionMethod(PostTemplateBuilder::class, 'getDefaultText'))->invoke($builder, $item);
	}

	public function testPlainPostAddsNoAbstract(): void
	{
		$text = $this->getDefaultText(['content-warning' => '', 'sensitive' => false]);

		self::assertSame('', $text);
	}

	public function testContentWarningWithoutSensitiveAddsNoAbstract(): void
	{
		$text = $this->getDefaultText(['content-warning' => 'Spoiler', 'sensitive' => false]);

		self::assertSame('', $text);
	}

	public function testSensitiveContentWarningAddsAbstract(): void
	{
		$text = $this->getDefaultText(['content-warning' => 'Spoiler', 'sensitive' => true]);

		self::assertSame("[abstract=apub]Spoiler[/abstract]\n", $text);
	}
}
