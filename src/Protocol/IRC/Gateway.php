<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Friendica\Protocol\IRC;

use Friendica\App\BaseURL;
use Friendica\Core\Config\Capability\IManageConfigValues;
use Friendica\Core\KeyValueStorage\Capability\IManageKeyValuePairs;
use Friendica\Util\Strings;
use Nyholm\Psr7\Response;
use Phrity\Net\StreamFactory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use WebSocket\Configuration;
use WebSocket\Connection;
use WebSocket\Exception\ConnectionLevelInterface;
use WebSocket\Exception\Exception as WebSocketException;
use WebSocket\Middleware\CloseHandler;
use WebSocket\Middleware\PingResponder;
use WebSocket\Middleware\SubprotocolNegotiation;

/**
 * WebSocket-to-IRC gateway.
 *
 * Browsers cannot open raw IRC sockets, so the chat addon's IRC client (and any
 * other kiwiirc/irc-framework based client) connects over WebSocket instead.
 * This daemon accepts those WebSocket connections and bridges each one to a
 * plain TCP or TLS connection to one of the IRC networks configured in
 * `irc_gateway.networks`. WebSocket text frames carry raw IRC protocol lines in
 * both directions, following the informal `text.ircv3.net` convention.
 *
 * The event loop is single threaded: one `stream_select()` over the listening
 * socket, every browser socket and every IRC socket. A slow peer can therefore
 * stall the loop for up to CLIENT_TIMEOUT seconds while a partial frame is read;
 * this matches the blocking model of the underlying websocket library.
 */
final class Gateway
{
	private const SUBPROTOCOL    = 'text.ircv3.net';
	private const HANDSHAKE_GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
	private const POLL_TIMEOUT   = 0.2;
	private const CLIENT_TIMEOUT = 10;
	private const READ_CHUNK     = 65535;
	private const PING_GRACE     = 30;
	private const MAX_PENDING    = 512;
	private const DRAIN_LIMIT    = 32;

	/** @var resource|null */
	private $listen = null;

	/** @var array<string, ClientSession> keyed by connection id */
	private array $sessions = [];

	/** @var array<string, resource> browser sockets keyed by connection id */
	private array $clients = [];

	private bool $stopping       = false;
	private int $lastClientCount = -1;

	private array $networks         = [];
	private array $trustedProxies   = [];
	private int $maxClients         = 200;
	private int $maxClientsPerIp    = 5;
	private int $idleTimeout        = 240;
	private int $connectTimeout     = 10;
	private int $floodLines         = 8;
	private int $floodSeconds       = 4;
	private int $maxMessageBytes    = 16384;
	private int $maxSendBufferBytes = 262144;
	private string $allowedOrigin   = '';

	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly IManageConfigValues $config,
		private readonly IManageKeyValuePairs $keyValue,
		private readonly BaseURL $baseUrl,
	) {}

	/**
	 * Run the gateway until it receives a termination signal.
	 */
	public function listen(): void
	{
		$this->loadConfig();

		if (empty($this->networks)) {
			$this->logger->warning('No networks configured in irc_gateway.networks, the gateway will reject every connection');
		}

		$bind = (string) ($this->config->get('irc_gateway', 'listen') ?: '127.0.0.1:8765');
		if (strpos($bind, ':') === false) {
			// A bare port stays on loopback; a wider bind has to be spelled out
			$bind = '127.0.0.1:' . $bind;
		}

		$this->listen = @stream_socket_server('tcp://' . $bind, $errno, $errstr);
		if (!is_resource($this->listen)) {
			$this->logger->error('Could not open the listening socket', ['address' => $bind, 'code' => $errno, 'message' => $errstr]);
			return;
		}
		stream_set_blocking($this->listen, false);
		$this->logger->notice('IRC gateway listening', ['address' => $bind, 'networks' => array_keys($this->networks)]);

		pcntl_async_signals(true);
		pcntl_signal(SIGTERM, function (): void {
			$this->stopping = true;
		});
		pcntl_signal(SIGINT, function (): void {
			$this->stopping = true;
		});

		while (!$this->stopping) {
			$this->tick();
		}

		$this->shutdown();
	}

	/**
	 * One iteration of the event loop.
	 */
	private function tick(): void
	{
		$read   = ['@listen' => $this->listen];
		$write  = null;
		$except = null;

		foreach ($this->sessions as $id => $session) {
			$read['ws:' . $id] = $this->clients[$id];
			if (is_resource($session->irc)) {
				$read['irc:' . $id] = $session->irc;
			}
		}

		$seconds      = (int) self::POLL_TIMEOUT;
		$microseconds = (int) round((self::POLL_TIMEOUT - $seconds) * 1000000);

		$ready = @stream_select($read, $write, $except, $seconds, $microseconds);
		if ($ready === false) {
			// Interrupted by a signal
			return;
		}

		foreach (array_keys($read) as $key) {
			if ($key === '@listen') {
				$this->acceptClient();
			} elseif (strpos($key, 'ws:') === 0) {
				$this->readFromClient(substr($key, 3));
			} elseif (strpos($key, 'irc:') === 0) {
				$this->readFromIrc(substr($key, 4));
			}
		}

		foreach ($this->sessions as $id => $session) {
			$session->drainPending();
			if (!$session->flushToIrc()) {
				$this->teardown($id, 'IRC write failed');
			}
		}

		$this->housekeeping();
	}

	/**
	 * Accept a browser connection, perform the handshake and open the IRC socket.
	 */
	private function acceptClient(): void
	{
		$socket = @stream_socket_accept($this->listen, 0, $peer);
		if (!is_resource($socket)) {
			return;
		}

		$peerIp = $this->addressToIp((string) $peer);

		// An empty list is treated as loopback only, never as "allow everyone"
		if (!in_array($peerIp, $this->trustedProxies ?: ['127.0.0.1', '::1'])) {
			$this->logger->warning('Rejected connection from untrusted address', ['address' => $peerIp]);
			fclose($socket);
			return;
		}

		if (count($this->sessions) >= $this->maxClients) {
			$this->logger->warning('Rejected connection, client limit reached', ['limit' => $this->maxClients]);
			fclose($socket);
			return;
		}

		if ($this->clientsForIp($peerIp) >= $this->maxClientsPerIp) {
			$this->logger->warning('Rejected connection, per-address limit reached', ['address' => $peerIp, 'limit' => $this->maxClientsPerIp]);
			fclose($socket);
			return;
		}

		$stream        = (new StreamFactory())->createSocketStreamFromResource($socket);
		$configuration = new Configuration($this->logger, null, self::CLIENT_TIMEOUT);

		$connection = new Connection($stream, false, true, false, null, $configuration);
		$connection
			->addMiddleware(new CloseHandler())
			->addMiddleware(new PingResponder())
			->addMiddleware(new SubprotocolNegotiation([self::SUBPROTOCOL]));

		try {
			$request = $connection->pullHttp();
			if (!$request instanceof ServerRequestInterface) {
				throw new \RuntimeException('Unexpected handshake request');
			}
			[$response, $token] = $this->handshake($request);
			$connection->pushHttp($response);
		} catch (Throwable $e) {
			$this->logger->info('Handshake failed', ['address' => $peerIp, 'error' => $e->getMessage()]);
			$connection->disconnect();
			return;
		}

		if ($response->getStatusCode() !== 101) {
			$this->logger->info('Handshake rejected', ['address' => $peerIp, 'status' => $response->getStatusCode()]);
			$connection->disconnect();
			return;
		}

		// Prefixed so the id is never an all-digit string that PHP would turn into an integer array key
		$id       = 'c' . Strings::getRandomHex(8);
		$clientIp = $this->resolveClientIp($request, $peerIp);
		$secure   = strtolower($request->getHeaderLine('X-Forwarded-Proto')) === 'https';

		$session = new ClientSession(
			$id,
			$token,
			$this->networks[$token],
			$clientIp,
			$secure,
			$connection,
			$this->floodLines,
			$this->floodSeconds,
		);

		if (!$this->connectIrc($session)) {
			try {
				$connection->close(1011, 'IRC connection failed');
			} catch (Throwable) {
			}
			$connection->disconnect();
			return;
		}

		$this->sessions[$id] = $session;
		$this->clients[$id]  = $socket;

		$this->logger->notice('Client connected', ['id' => $id, 'network' => $token, 'address' => $clientIp]);
	}

	/**
	 * Validate the upgrade request and pick the target network.
	 *
	 * @return array{0: Response, 1: string} the response to send and the network token
	 */
	private function handshake(ServerRequestInterface $request): array
	{
		if ($request->getMethod() !== 'GET') {
			return [new Response(405), ''];
		}

		if (
			stripos($request->getHeaderLine('Connection'), 'upgrade') === false
			|| strtolower($request->getHeaderLine('Upgrade')) !== 'websocket'
			|| trim($request->getHeaderLine('Sec-WebSocket-Version')) !== '13'
		) {
			return [new Response(426), ''];
		}

		$key = trim($request->getHeaderLine('Sec-WebSocket-Key'));
		if (strlen(base64_decode($key, true) ?: '') !== 16) {
			return [new Response(400), ''];
		}

		if ($this->allowedOrigin !== '') {
			// Once an origin is configured the header must match it exactly, a missing one is rejected too
			$origin = rtrim($request->getHeaderLine('Origin'), '/');
			if ($origin !== $this->allowedOrigin) {
				$this->logger->warning('Rejected connection from foreign origin', ['origin' => $origin]);
				return [new Response(403), ''];
			}
		}

		$token = $this->requestToken($request);
		if (!isset($this->networks[$token])) {
			$this->logger->warning('Unknown network requested', ['token' => $token]);
			return [new Response(404), ''];
		}

		$accept   = base64_encode(pack('H*', sha1($key . self::HANDSHAKE_GUID)));
		$response = (new Response(101))
			->withHeader('Upgrade', 'websocket')
			->withHeader('Connection', 'Upgrade')
			->withHeader('Sec-WebSocket-Accept', $accept);

		return [$response, $token];
	}

	/**
	 * Extract the network token from a `?net=` parameter or the last path segment.
	 */
	private function requestToken(ServerRequestInterface $request): string
	{
		parse_str($request->getUri()->getQuery(), $query);
		if (!empty($query['net'])) {
			return (string) $query['net'];
		}

		$segments = array_values(array_filter(
			explode('/', $request->getUri()->getPath()),
			fn (string $segment): bool => $segment !== '',
		));
		return (string) end($segments);
	}

	/**
	 * Open the outbound IRC socket for a session.
	 *
	 * The connect itself is blocking, bounded by irc_gateway.connect_timeout.
	 */
	private function connectIrc(ClientSession $session): bool
	{
		$network = $session->network;
		$tls     = !empty($network['tls']);
		$verify  = $network['verify'] ?? true;
		$target  = sprintf('%s://%s:%d', $tls ? 'tls' : 'tcp', $network['host'], (int) $network['port']);

		$context = stream_context_create();
		if ($tls) {
			stream_context_set_option($context, 'ssl', 'verify_peer', (bool) $verify);
			stream_context_set_option($context, 'ssl', 'verify_peer_name', (bool) $verify);
			stream_context_set_option($context, 'ssl', 'SNI_enabled', true);
			stream_context_set_option($context, 'ssl', 'peer_name', $network['host']);
			if (!$verify) {
				$this->logger->warning('IRC TLS certificate verification disabled', ['network' => $session->token]);
			}
		}

		$socket = @stream_socket_client($target, $errno, $errstr, $this->connectTimeout, STREAM_CLIENT_CONNECT, $context);
		if (!is_resource($socket)) {
			$this->logger->error('Could not connect to the IRC server', ['network' => $session->token, 'target' => $target, 'code' => $errno, 'message' => $errstr]);
			return false;
		}
		stream_set_blocking($socket, false);
		$session->irc = $socket;

		if (!empty($network['webirc_password'])) {
			$hostname = gethostbyaddr($session->clientIp) ?: $session->clientIp;
			$line     = sprintf(
				'WEBIRC %s %s %s %s',
				$network['webirc_password'],
				$network['webirc_name'] ?? 'friendica',
				$hostname,
				$session->clientIp,
			);
			if ($session->secure) {
				$line .= ' :secure';
			}
			$session->ircWriteBuffer .= $line . "\r\n";
		}

		$this->logger->info('IRC connection established', ['id' => $session->id, 'network' => $session->token, 'target' => $target]);
		return true;
	}

	/**
	 * Read WebSocket messages from a browser and queue the IRC lines they carry.
	 */
	private function readFromClient(string $id): void
	{
		$session = $this->sessions[$id] ?? null;
		if ($session === null) {
			return;
		}

		for ($i = 0; $i < self::DRAIN_LIMIT; $i++) {
			try {
				$message = $session->ws->pullMessage();
			} catch (ConnectionLevelInterface $e) {
				$this->teardown($id, 'browser connection lost: ' . $e->getMessage());
				return;
			} catch (WebSocketException $e) {
				$this->logger->debug('Browser read error', ['id' => $session->id, 'error' => $e->getMessage()]);
				return;
			} catch (Throwable $e) {
				$this->teardown($id, 'browser read error: ' . $e->getMessage());
				return;
			}

			if (!$session->ws->isConnected()) {
				$this->teardown($id, 'browser closed the connection');
				return;
			}

			$opcode = $message->getOpcode();
			if ($opcode === 'close') {
				$this->teardown($id, 'browser closed the connection');
				return;
			}
			if ($opcode !== 'text') {
				continue;
			}

			$content = $message->getContent();
			if (strlen($content) > $this->maxMessageBytes) {
				$this->teardown($id, 'oversized message from browser');
				return;
			}

			$session->touch();
			foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
				if ($line !== '') {
					$session->pendingToIrc[] = $line;
				}
			}

			if (count($session->pendingToIrc) > self::MAX_PENDING) {
				$this->teardown($id, 'browser is flooding');
				return;
			}

			$more = [$this->clients[$id]];
			$w    = $x = null;
			if (@stream_select($more, $w, $x, 0, 0) !== 1) {
				return;
			}
		}
	}

	/**
	 * Forward IRC lines to the browser, one line per text frame.
	 */
	private function readFromIrc(string $id): void
	{
		$session = $this->sessions[$id] ?? null;
		if ($session === null) {
			return;
		}

		$lines = $session->readFromIrc(self::READ_CHUNK);
		if ($lines === null) {
			$this->teardown($id, 'IRC server closed the connection');
			return;
		}

		foreach ($lines as $line) {
			try {
				$session->ws->text($line);
			} catch (Throwable $e) {
				$this->teardown($id, 'browser write failed: ' . $e->getMessage());
				return;
			}
			$this->incrementMessages();
		}
	}

	/**
	 * Idle pings, buffer limits and statistics.
	 */
	private function housekeeping(): void
	{
		$now = microtime(true);

		foreach ($this->sessions as $id => $session) {
			if ($session->pingSentAt !== null) {
				if ($now - $session->pingSentAt > self::PING_GRACE) {
					$this->teardown($id, 'keepalive timeout');
					continue;
				}
			} elseif ($now - $session->lastActivity > $this->idleTimeout) {
				try {
					$session->ws->ping('');
					$session->pingSentAt = $now;
				} catch (Throwable $e) {
					$this->teardown($id, 'keepalive ping failed: ' . $e->getMessage());
					continue;
				}
			}

			if (strlen($session->ircWriteBuffer) > $this->maxSendBufferBytes) {
				$this->teardown($id, 'IRC send buffer overflow');
				continue;
			}

			try {
				$session->ws->tick();
			} catch (Throwable) {
			}
		}

		if (count($this->sessions) !== $this->lastClientCount) {
			$this->lastClientCount = count($this->sessions);
			$this->keyValue->set('irc_gateway_clients', $this->lastClientCount);
		}
	}

	/**
	 * Close a session and both of its sockets.
	 */
	private function teardown(string $id, string $reason): void
	{
		$session = $this->sessions[$id] ?? null;
		if ($session === null) {
			return;
		}

		try {
			if ($session->ws->isConnected()) {
				$session->ws->close(1000, 'bye');
			}
			$session->ws->disconnect();
		} catch (Throwable) {
		}

		if (is_resource($session->irc)) {
			@fwrite($session->irc, "QUIT :Gateway closing\r\n");
			@fclose($session->irc);
		}

		unset($this->sessions[$id], $this->clients[$id]);
		$this->logger->notice('Client disconnected', ['id' => $session->id, 'reason' => $reason]);
	}

	/**
	 * Close every session and the listening socket.
	 */
	private function shutdown(): void
	{
		foreach (array_keys($this->sessions) as $id) {
			$this->teardown($id, 'gateway shutting down');
		}

		if (is_resource($this->listen)) {
			fclose($this->listen);
		}

		$this->keyValue->set('irc_gateway_clients', 0);
		$this->logger->notice('IRC gateway stopped');
	}

	/**
	 * Increment the message counter for the statistics page.
	 */
	private function incrementMessages(): void
	{
		$messages = (int) ($this->keyValue->get('irc_gateway_messages') ?? 0);
		if ($messages >= PHP_INT_MAX) {
			$messages = 0;
		}
		$this->keyValue->set('irc_gateway_messages', $messages + 1);
	}

	/**
	 * Resolve the real client address, honouring X-Forwarded-For from a trusted proxy.
	 */
	private function resolveClientIp(ServerRequestInterface $request, string $peerIp): string
	{
		if (in_array($peerIp, $this->trustedProxies)) {
			$forwarded = $request->getHeaderLine('X-Forwarded-For');
			if ($forwarded !== '') {
				return trim(explode(',', $forwarded)[0]);
			}
		}

		return $peerIp;
	}

	private function clientsForIp(string $ip): int
	{
		$count = 0;
		foreach ($this->clients as $socket) {
			if ($this->addressToIp((string) stream_socket_get_name($socket, true)) === $ip) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Strip the port from an "ip:port" socket name, keeping IPv6 addresses intact.
	 */
	private function addressToIp(string $address): string
	{
		if ($address === '') {
			return '';
		}

		if ($address[0] === '[') {
			return substr($address, 1, strpos($address, ']') - 1);
		}

		$colon = strrpos($address, ':');
		return $colon === false ? $address : substr($address, 0, $colon);
	}

	private function loadConfig(): void
	{
		$this->networks           = (array) ($this->config->get('irc_gateway', 'networks') ?: []);
		$this->trustedProxies     = (array) ($this->config->get('irc_gateway', 'trusted_proxies') ?: []);
		$this->maxClients         = (int) ($this->config->get('irc_gateway', 'max_clients') ?? 200);
		$this->maxClientsPerIp    = (int) ($this->config->get('irc_gateway', 'max_clients_per_ip') ?? 5);
		$this->idleTimeout        = (int) ($this->config->get('irc_gateway', 'idle_timeout') ?? 240);
		$this->connectTimeout     = (int) ($this->config->get('irc_gateway', 'connect_timeout') ?? 10);
		$this->floodLines         = (int) ($this->config->get('irc_gateway', 'flood_lines') ?? 8);
		$this->floodSeconds       = (int) ($this->config->get('irc_gateway', 'flood_seconds') ?? 4);
		$this->maxMessageBytes    = (int) ($this->config->get('irc_gateway', 'max_message_bytes') ?? 16384);
		$this->maxSendBufferBytes = (int) ($this->config->get('irc_gateway', 'max_sendbuf_bytes') ?? 262144);

		// Empty config falls back to this node's own origin, '*' turns the check off
		$origin = (string) ($this->config->get('irc_gateway', 'allowed_origin') ?: '');
		if ($origin === '') {
			$origin = $this->baseUrl->getScheme() . '://' . $this->baseUrl->getAuthority();
		}
		$this->allowedOrigin = $origin === '*' ? '' : rtrim($origin, '/');
	}
}
