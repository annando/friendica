<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Friendica\Protocol\IRC;

use WebSocket\Connection;

/**
 * One browser WebSocket connection paired with its outbound IRC socket.
 *
 * The gateway keeps the socket handling in {@see Gateway}; this class only holds
 * the per-connection state and the line buffering between the two sockets.
 */
final class ClientSession
{
	/** @var resource|null Raw IRC socket, null until connected */
	public $irc = null;

	/** Bytes waiting to be written to the IRC socket */
	public string $ircWriteBuffer = '';

	/** Lines from the browser that still need a flood token before they may be forwarded */
	public array $pendingToIrc = [];

	/** Incomplete trailing line read from the IRC socket */
	public string $ircReadBuffer = '';

	public float $lastActivity;

	/** Time a keepalive ping was sent to the browser, null when none is pending */
	public ?float $pingSentAt = null;

	public bool $registered = false;

	private float $floodTokens;
	private float $floodStamp;

	/**
	 * @param string     $id        Short random id used as log context
	 * @param string     $token     Network token from the request path
	 * @param array      $network   Resolved entry from the irc_gateway.networks config
	 * @param string     $clientIp  Remote address the browser connected from
	 * @param bool       $secure    Whether the browser reached the gateway over TLS
	 * @param Connection $ws        The WebSocket connection to the browser
	 * @param int        $floodLines Token bucket size
	 * @param int        $floodSeconds Window the bucket refills over
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $token,
		public readonly array $network,
		public readonly string $clientIp,
		public readonly bool $secure,
		public readonly Connection $ws,
		private readonly int $floodLines,
		private readonly int $floodSeconds,
	) {
		$this->lastActivity = microtime(true);
		$this->floodTokens  = $floodLines;
		$this->floodStamp   = microtime(true);
	}

	public function touch(): void
	{
		$this->lastActivity = microtime(true);
		$this->pingSentAt   = null;
	}

	/**
	 * Take one token from the flood bucket.
	 *
	 * @return bool true if a line may be forwarded right now
	 */
	public function takeFloodToken(): bool
	{
		$now  = microtime(true);
		$rate = $this->floodLines / max(1, $this->floodSeconds);

		$this->floodTokens = min($this->floodLines, $this->floodTokens + ($now - $this->floodStamp) * $rate);
		$this->floodStamp  = $now;

		if ($this->floodTokens >= 1) {
			$this->floodTokens -= 1;
			return true;
		}

		return false;
	}

	/**
	 * Move browser lines that now have a flood token into the IRC write buffer.
	 */
	public function drainPending(): void
	{
		while (!empty($this->pendingToIrc) && $this->takeFloodToken()) {
			$this->ircWriteBuffer .= array_shift($this->pendingToIrc) . "\r\n";
		}
	}

	/**
	 * Write as much of the IRC write buffer as the socket accepts.
	 *
	 * @return bool false on a write error
	 */
	public function flushToIrc(): bool
	{
		if ($this->ircWriteBuffer === '' || !is_resource($this->irc)) {
			return true;
		}

		$written = @fwrite($this->irc, $this->ircWriteBuffer);
		if ($written === false) {
			return false;
		}

		$this->ircWriteBuffer = substr($this->ircWriteBuffer, $written);
		return true;
	}

	/**
	 * Read from the IRC socket and return the complete lines received.
	 *
	 * @return string[]|null null when the socket has closed
	 */
	public function readFromIrc(int $length): ?array
	{
		$data = @fread($this->irc, $length);
		if ($data === false || ($data === '' && feof($this->irc))) {
			return null;
		}

		$this->ircReadBuffer .= $data;

		$lines = [];
		while (($pos = strpos($this->ircReadBuffer, "\n")) !== false) {
			$lines[]             = rtrim(substr($this->ircReadBuffer, 0, $pos), "\r");
			$this->ircReadBuffer = substr($this->ircReadBuffer, $pos + 1);
		}

		return $lines;
	}
}
