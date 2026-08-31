Upgrade from 2026.08 to 2026.11
===============================

All notable changes to **Friendica** will be documented in this file.
As an *Addon or Theme maintainer* you can inform yourself about all breaking changes and deprecations.

This project [promises Backward Compatibility](help/developer/index#backward-compatibility).

Mandatory (Breaking changes)
----------------------------

This section contains backward compatibility breaks, make sure your code is compatible with these entries before upgrading.

- `phrity/websocket` was updated from 1.7 to 3.x. Addons using the `\WebSocket\` classes must follow its API changes.

   `WebSocket\Client::receive()` now returns a `WebSocket\Message\Message` object instead of a string, `send()` takes a `Message` (use `text()` for strings), the `WebSocket\ConnectionException` class was replaced by the `WebSocket\Exception\*` hierarchy, logger and timeout are set through a `WebSocket\Configuration` passed to the constructor, and `CloseHandler` / `PingResponder` middleware have to be added explicitly to keep answering pings and close frames.

- A new `bin/console.php ircgateway` command runs the IRC-over-WebSocket gateway for the chat addon, configured through the new `irc_gateway` config section.

Optional (Deprecations)
-----------------------

This section contains deprecation notices. This changes will become mandatory in a future release.
