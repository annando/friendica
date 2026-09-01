# Installing Connectors

Friendica uses add-ons to connect to some networks, such as Tumblr or AT Protocol based systems like Bluesky, Eurosky or Blacksky.

All of these add-ons require an account on the target network.
In addition, you (or usually the server administrator) will need to obtain an API key to allow authenticated access to your Friendica server.

## Site configuration

Addons need to be installed by the site administrator before they can be used.
This is done through the site administration panel.

Some of the connectors also require an "API key" from the service you wish to connect to.
For Tumblr, this information can be found in the site administration pages, while for Twitter (X) each user has to create their own API key.
Other connectors, such as the AT Protocol, don't require an API key at all.

You can find more information about specific requirements on each addon's settings page, either on the admin page or the user page.

## AT Protocol Jetstream

To further improve connectivity via the AT Protocol, Admins can choose to enable 'Jetstream' connectivity.
Jetstream is a service that connects to an AT Protocol firehose.
With Jetstream, messages arrive in real time rather than having to be polled.
It also enables real-time processing of blocks or tracking activities performed by the user via an AT Protocol website or application.

To enable Jetstream processing, run `bin/console.php jetstream` from the command line.
You will need to define the process id file in local.config.php in the 'jetstream' section using the key 'pidfile'.

To keep track of the messages processed and the drift (the time difference between the date of the message and the date the system processed that message), some fields are added to the statistics endpoint.

## IRC gateway

The chat addon offers an IRC client, but browsers cannot open raw IRC sockets, so the client connects over WebSocket instead.
Friendica ships a gateway daemon that accepts those WebSocket connections and bridges each one to a TCP or TLS connection to an IRC network.
It replaces external gateways such as kiwiirc's webircgateway.

To run it, set `irc_gateway.pidfile` in local.config.php and start `bin/console.php ircgateway start`.

The daemon listens on the plain address given in `irc_gateway.listen` (`127.0.0.1:8765` by default); terminate `wss://` in your web server and proxy it there, the same way the XMPP WebSocket endpoint is handled.
Each IRC network the gateway may reach is listed in `irc_gateway.networks` under a short token:

```php
'irc_gateway' => [
	'pidfile'  => '/run/friendica/irc_gateway.pid',
	'networks' => [
		'libera' => ['host' => 'irc.libera.chat', 'port' => 6697, 'tls' => true],
	],
],
```

The browser selects a network by that token in the URL path, so the chat addon's `irc_websocket_url` becomes `wss://gateway.example.com/irc/libera`.
A client can never name an IRC host itself, only a configured token.
The remaining `irc_gateway` keys cap the number of clients, the idle timeout and the flood limits; see static/defaults.config.php for the full list.

The daemon only binds to `127.0.0.1` and only accepts connections from the addresses in `irc_gateway.trusted_proxies` (loopback by default), so it is not reachable from outside the host.
It also checks the browser's `Origin` header against `irc_gateway.allowed_origin`, which defaults to this node's own base URL, so by default only pages served from Friendica can open a connection.
The web server terminates `wss://` and forwards the request to it.

### nginx

```nginx
location /irc/ {
	proxy_pass http://127.0.0.1:8765/;
	proxy_http_version 1.1;
	proxy_set_header Upgrade $http_upgrade;
	proxy_set_header Connection "upgrade";
	proxy_set_header Host $host;
	proxy_set_header X-Forwarded-For $remote_addr;
	proxy_set_header X-Forwarded-Proto $scheme;
	proxy_read_timeout 3600s;
}
```

### Apache

Enable `proxy`, `proxy_http`, `proxy_wstunnel` and `headers`, then add this inside the `https` virtual host:

```apache
ProxyPass        /irc/  ws://127.0.0.1:8765/
ProxyPassReverse /irc/  ws://127.0.0.1:8765/
RequestHeader set X-Forwarded-Proto "https"
```

`mod_proxy` adds `X-Forwarded-For` on its own.
The gateway sees the web server as the peer, so add that address to `irc_gateway.trusted_proxies` when the web server runs on a different host, and it then reads the real client address from `X-Forwarded-For`.
If your instance sends a Content-Security-Policy header, `connect-src` has to allow the gateway origin or the browser blocks the connection before the handshake.
