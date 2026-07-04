<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Content\Conversation\Factory;

use Friendica\Content\Conversation\Entity\Thread as ThreadEntity;
use Psr\Log\LoggerInterface;

/**
 * Factory class for creating Thread entities.
 */
final class Thread extends \Friendica\BaseFactory
{
	public function __construct(LoggerInterface $logger)
	{
		parent::__construct($logger);
	}

	/**
	 * Create a Thread entity from an item array.
	 *
	 * @param array $item The item array with all necessary thread fields
	 * @return ThreadEntity
	 */
	public function createFromArray(array $item): ThreadEntity
	{
		return new ThreadEntity(
			$item['uri-id'] ?? null,
			$item['uid'] ?? null,
			$item['author-id'] ?? null,
			$item['channel'] ?? null,
			$item['thr-parent-id'] ?? null,
			$item['gravity'] ?? null,
			$item['commented'] ?? null,
			$item['received'] ?? null,
			$item['created'] ?? null,
			$item['network'] ?? null,
			$item['writable'] ?? null,
			$item['pagedrop'] ?? null,
		);
	}

	/**
	 * Create an array of Thread entities from an items array.
	 *
	 * @param array<int, array> $items Array of item arrays
	 * @return array<int, ThreadEntity> Array of Thread entities
	 */
	public function createFromArrayList(array $items): array
	{
		$threads = [];
		foreach ($items as $item) {
			$threads[] = $this->createFromArray($item);
		}
		return $threads;
	}
}
