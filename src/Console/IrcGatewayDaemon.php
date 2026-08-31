<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Friendica\Console;

use Asika\SimpleConsole\Console;
use Friendica\App\Mode;
use Friendica\Core\Config\Capability\IManageConfigValues;
use Friendica\Core\KeyValueStorage\Capability\IManageKeyValuePairs;
use Friendica\Protocol\IRC\Gateway;
use Friendica\System\Daemon as SysDaemon;
use RuntimeException;

/**
 * Console command for interacting with the IRC gateway daemon
 */
final class IrcGatewayDaemon extends Console
{
	public function __construct(
		private readonly Mode $mode,
		private readonly IManageConfigValues $config,
		private readonly IManageKeyValuePairs $keyValue,
		private readonly SysDaemon $daemon,
		private readonly Gateway $gateway,
		?array $argv = null,
	) {
		parent::__construct($argv);
	}

	protected function getHelp(): string
	{
		return <<<HELP
ircgateway - Interact with the IRC gateway daemon
Synopsis
	bin/console ircgateway start [-h|--help|-?] [-v] [-f]
	bin/console ircgateway stop [-h|--help|-?] [-v]
	bin/console ircgateway status [-h|--help|-?] [-v]

Description
	Bridges browser WebSocket connections to IRC networks for the chat addon

Options
	-h|--help|-?    Show help information
	-v              Show more debug information.
	-f|--foreground Runs the daemon in the foreground

Examples
	bin/console ircgateway start -f
		Starts the daemon in the foreground

	bin/console ircgateway status
		Gets the status of the daemon
HELP;
	}

	protected function doExecute(): int
	{
		if ($this->mode->isInstall()) {
			throw new RuntimeException("Friendica isn't properly installed yet");
		}

		$this->config->reload();

		if (empty($this->config->get('irc_gateway', 'pidfile'))) {
			throw new RuntimeException(
				<<< TXT
					Please set irc_gateway.pidfile in config/local.config.php. For example:

						'irc_gateway' => [
							'pidfile' => '/path/to/irc_gateway.pid',
						],
					TXT,
			);
		}

		$pidfile = $this->config->get('irc_gateway', 'pidfile');

		$daemonMode = $this->getArgument(0);
		$foreground = (bool) ($this->getOption(['f', 'foreground']) ?? false);

		if (empty($daemonMode)) {
			throw new RuntimeException("Please use either 'start', 'stop' or 'status'");
		}

		$this->daemon->init($pidfile);

		if ($daemonMode === 'status') {
			if ($this->daemon->isRunning()) {
				$this->out(sprintf("Daemon process %s is running (%s)", $this->daemon->getPid(), $this->daemon->getPidfile()));
			} else {
				$this->out(sprintf("Daemon process %s isn't running (%s)", $this->daemon->getPid(), $this->daemon->getPidfile()));
			}
			return 0;
		}

		if ($daemonMode === 'stop') {
			if (!$this->daemon->isRunning()) {
				$this->out(sprintf("Daemon process %s isn't running (%s)", $this->daemon->getPid(), $this->daemon->getPidfile()));
				return 0;
			}

			if ($this->daemon->stop()) {
				$this->keyValue->set('irc_gateway_daemon_mode', false);
				$this->out(sprintf("Daemon process %s was killed (%s)", $this->daemon->getPid(), $this->daemon->getPidfile()));
				return 0;
			}

			return 1;
		}

		if ($this->daemon->isRunning()) {
			$this->out(sprintf("Daemon process %s is already running (%s)", $this->daemon->getPid(), $this->daemon->getPidfile()));
			return 1;
		}

		if ($daemonMode === 'start') {
			$this->out('Starting IRC gateway daemon');

			$this->daemon->start(fn () => $this->gateway->listen(), $foreground);

			return 0;
		}

		$this->err('Invalid command');
		$this->out($this->getHelp());
		return 1;
	}
}
