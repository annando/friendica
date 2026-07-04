<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Content\Conversation\Entity;

/**
 * Entity class representing a thread with identification fields.
 *
 * @property-read int    $uriId        The URI ID of the thread
 * @property-read int    $uid          The user ID of the thread owner
 * @property-read int    $authorId     The author ID of the thread
 * @property-read string $channel      The channel associated with the thread
 * @property-read int    $thrParentId  The thread parent URI ID
 * @property-read int    $gravity      The gravity of the item
 * @property-read string $commented    The commented timestamp
 * @property-read string $received     The received timestamp
 * @property-read string $created      The created timestamp
 * @property-read string $network      The network of the item
 * @property-read bool   $writable     Whether the item is writable
 * @property-read bool   $pagedrop     Whether page drop is enabled
 */
class Thread extends \Friendica\BaseEntity
{
	/** @var int */
	protected $uriId;
	/** @var int */
	protected $uid;
	/** @var int */
	protected $authorId;
	/** @var string */
	protected $channel;
	/** @var int */
	protected $thrParentId;
	/** @var int */
	protected $gravity;
	/** @var string */
	protected $commented;
	/** @var string */
	protected $received;
	/** @var string */
	protected $created;
	/** @var string */
	protected $network;
	/** @var bool */
	protected $writable;
	/** @var bool */
	protected $pagedrop;

	public function __construct(
		?int $uriId = null,
		?int $uid = null,
		?int $authorId = null,
		?string $channel = null,
		?int $thrParentId = null,
		?int $gravity = null,
		?string $commented = null,
		?string $received = null,
		?string $created = null,
		?string $network = null,
		?bool $writable = null,
		?bool $pagedrop = null,
	) {
		$this->uriId        = $uriId;
		$this->uid          = $uid;
		$this->authorId     = $authorId;
		$this->channel      = $channel;
		$this->thrParentId  = $thrParentId;
		$this->gravity      = $gravity;
		$this->commented    = $commented;
		$this->received     = $received;
		$this->created      = $created;
		$this->network      = $network;
		$this->writable     = $writable;
		$this->pagedrop     = $pagedrop;
	}
}
