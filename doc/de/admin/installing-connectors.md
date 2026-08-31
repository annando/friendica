# Konnektoren installieren

Friendica verwendet Konnektoren, um sich mit einigen Netzwerken zu verbinden, wie Tumblr oder den auf dem AT Protokoll basierenden Systemen wie Bluesky, Eurosky oder Blacksky.

Alle diese Konnektoren erfordern einen Account im Zielnetzwerk.
Außerdem musst du (oder die Server-Administration) in der Regel einen API-Schlüssel erhalten, um die Verbindung zu ermöglichen.

**Seitenkonfiguration**

Konnektoren müssen von der Server-Administration installiert werden, bevor sie verwendet werden können.
Dies geschieht über die Server-Verwaltung.

Einige der Konnektoren erfordern auch einen „API-Schlüssel“ des Dienstes, mit dem du dich verbinden möchtest.
Für Tumblr findet man diese Informationen auf den Seiten der Server-Verwaltung, während für Twitter (X) jede Person einen eigenen API-Schlüssel erstellen muss.
Andere Konnektoren, wie das AT Protokoll, benötigen überhaupt keinen API-Schlüssel.

Weitere Informationen zu den spezifischen Anforderungen findest du auf der Einstellungsseite des jeweiligen Addons, entweder auf der Verwaltungsseite oder auf der Benutzerseite.

## AT Protokoll Jetstream

Um die Konnektivität über das AT Protokoll weiter zu verbessern, kann die „Jetstream“-Konnektivität aktiviert werden.
Jetstream ist ein Dienst, der sich mit einer AT Protokoll-Firehose verbindet.
Mit Jetstream kommen die Nachrichten in Echtzeit an und müssen nicht erst abgefragt werden.
Es ermöglicht auch die Echtzeitverarbeitung von Blöcken oder Tracking-Aktivitäten, die über eine AT Protokoll-Website oder -Anwendung durchgeführt werden.

Um die Jetstream-Verarbeitung zu aktivieren, führe `bin/console.php daemon' über die Befehlszeile aus.
Du musst vorher die Prozess-ID-Datei in local.config.php im Abschnitt „jetstream“ mit dem Schlüssel „pidfile“ definieren.

Um die verarbeiteten Nachrichten und die Drift (die Zeitdifferenz zwischen dem Datum der Nachricht und dem Datum, an dem das System diese Nachricht verarbeitet hat) zu verfolgen, wurden dem Statistik-Endpunkt einige Felder hinzugefügt.

## IRC-Gateway

Das Chat-Addon bietet einen IRC-Client an, aber Browser können keine rohen IRC-Verbindungen öffnen, daher verbindet sich der Client stattdessen über WebSocket.
Friendica bringt einen Gateway-Daemon mit, der diese WebSocket-Verbindungen annimmt und jede davon auf eine TCP- oder TLS-Verbindung zu einem IRC-Netzwerk überbrückt.
Er ersetzt externe Gateways wie das webircgateway von kiwiirc.

Zum Betrieb setzt du `irc_gateway.pidfile` in der local.config.php und startest `bin/console.php ircgateway start`.

Der Daemon lauscht auf der unverschlüsselten Adresse aus `irc_gateway.listen` (Vorgabe `127.0.0.1:8765`); `wss://` wird im Webserver terminiert und dorthin weitergereicht, genau wie beim XMPP-WebSocket-Endpunkt.
Jedes IRC-Netzwerk, das das Gateway erreichen darf, wird in `irc_gateway.networks` unter einem kurzen Token eingetragen:

```php
'irc_gateway' => [
	'pidfile'  => '/run/friendica/irc_gateway.pid',
	'networks' => [
		'libera' => ['host' => 'irc.libera.chat', 'port' => 6697, 'tls' => true],
	],
],
```

Der Browser wählt ein Netzwerk über dieses Token im URL-Pfad, aus `irc_websocket_url` im Chat-Addon wird also `wss://gateway.example.com/irc/libera`.
Ein Client kann niemals selbst einen IRC-Host angeben, nur ein konfiguriertes Token.
Die übrigen `irc_gateway`-Schlüssel begrenzen die Anzahl der Clients, den Leerlauf-Timeout und die Flood-Limits; die vollständige Liste steht in der static/defaults.config.php.

Der Daemon bindet nur an `127.0.0.1` und nimmt nur Verbindungen von den Adressen in `irc_gateway.trusted_proxies` an (Vorgabe: Loopback), ist von außerhalb des Hosts also nicht erreichbar.
Zusätzlich prüft er den `Origin`-Header des Browsers gegen `irc_gateway.allowed_origin`, was standardmäßig die eigene Basis-URL des Knotens ist, sodass von Haus aus nur von Friendica ausgelieferte Seiten eine Verbindung öffnen können.
Darüber hinaus muss jede Verbindung ein kurzlebiges Token mitbringen, das das Chat-Addon für den angemeldeten Nutzer signiert, sodass sich ein nicht angemeldeter Besucher nicht verbinden kann (`irc_gateway.require_token`, standardmäßig an; das gemeinsame Geheimnis wird aus dem privaten Schlüssel des Knotens abgeleitet und braucht keine Konfiguration).
Der Webserver terminiert `wss://` und reicht die Anfrage dorthin weiter.

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

`proxy`, `proxy_http`, `proxy_wstunnel` und `headers` aktivieren, dann im `https`-VirtualHost ergänzen:

```apache
ProxyPass        /irc/  ws://127.0.0.1:8765/
ProxyPassReverse /irc/  ws://127.0.0.1:8765/
RequestHeader set X-Forwarded-Proto "https"
```

`mod_proxy` setzt `X-Forwarded-For` von sich aus.
Das Gateway sieht den Webserver als Peer, trage dessen Adresse in `irc_gateway.trusted_proxies` ein, wenn der Webserver auf einem anderen Host läuft; die echte Client-Adresse wird dann aus `X-Forwarded-For` gelesen.
Falls deine Instanz einen Content-Security-Policy-Header schickt, muss `connect-src` die Gateway-Origin erlauben, sonst blockt der Browser die Verbindung vor dem Handshake.
