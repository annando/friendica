<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Content\Conversation\Collection;

use Friendica\BaseCollection;
use Friendica\Content\Conversation\Entity\Thread;

/**
 * Collection class for Thread entities.
 *
 * @extends BaseCollection<int, Thread>
 */
class Threads extends BaseCollection
{
	/**
	 * @param Thread[] $entities
	 * @param int|null $totalCount
	 */
	public function __construct(array $entities = [], ?int $totalCount = null)
	{
		parent::__construct($entities, $totalCount);
	}

	/**
	 * Get the first thread in the collection.
	 *
	 * @return Thread|null
	 */
	public function first(): ?Thread
	{
		return $this->current() ?: null;
	}

	/**
	 * Get all URI IDs from the threads in this collection.
	 *
	 * @return array<int, int>
	 */
	public function getUriIds(): array
	{
		$uriIds = [];
		foreach ($this as $thread) {
			$uriIds[$thread->uriId] = $thread->uriId;
		}
		return $uriIds;
	}
}
