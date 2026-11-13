# Installation

## Application

Install PHP 8.0 or newer with cURL, DOM, GD, mbstring, OpenSSL and SQLite3, then
install the locked Composer dependencies:

```sh
composer install --no-dev --optimize-autoloader
php bin/verify.php
```

The web-server document root must point to the repository's `public/` directory,
not the repository root. Create the runtime directories and grant only the
service account access to them:

```sh
mkdir -p var/cache/images var/cache/snapshots var/data
chown -R www-data:www-data var/cache var/data
chmod -R u=rwX,g=,o= var/cache var/data
```

Use the account name of your PHP/web-server service if it is not `www-data`.
Source files should remain read-only for that account.

For a quick local check:

```sh
php -S 127.0.0.1:8080 -t public
```

## Apache example

Enable PHP and URL rewriting, then use a virtual host similar to this:

```apache
<VirtualHost *:80>
    ServerName retro.example.org
    DocumentRoot /srv/retroweb/public

    SetEnv RETROWEB_PUBLIC_ORIGIN http://retro.example.org
    SetEnv RETROWEB_PUBLIC_PROXY_PORT 8081

    <Directory /srv/retroweb/public>
        AllowOverride FileInfo
        Require all granted
        Options -Indexes
    </Directory>
</VirtualHost>
```

## Nginx example

The supplied `.htaccess` file is Apache-specific. A minimal Nginx equivalent is:

```nginx
server {
    listen 80;
    server_name retro.example.org;
    root /srv/retroweb/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        try_files $uri =404;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param RETROWEB_PUBLIC_ORIGIN http://retro.example.org;
        fastcgi_param RETROWEB_PUBLIC_PROXY_PORT 8081;
        fastcgi_pass unix:/run/php/php-fpm.sock;
    }

    location ~ /\. { deny all; }
}
```

Adjust the PHP-FPM socket for your operating system. Never use the repository
root as the web root.

## Public URL

The legacy interface generates absolute HTTP URLs. Set these environment
variables when the installation differs from the defaults:

- `RETROWEB_PUBLIC_ORIGIN`: public HTTP origin, for example
  `http://retro.example.org` (no trailing slash);
- `RETROWEB_PUBLIC_BASE_PATH`: optional URL path prefix, for example `/retro`;
- `RETROWEB_PUBLIC_PROXY_PORT`: advertised forwarding-proxy port.

The default origin is `http://retroweb.bjrno.com`, which serves the project's
public instance. Self-hosted installations should always set their own origin.

### Environment reference

| Variable | Default | Purpose |
| --- | --- | --- |
| `RETROWEB_PUBLIC_ORIGIN` | `http://retroweb.bjrno.com` | Absolute HTTP origin used in legacy-safe links and forms. It must not contain credentials, a path, query or fragment. |
| `RETROWEB_PUBLIC_BASE_PATH` | empty | Optional single path segment such as `/retro`. Leave empty when `public/` is served at the origin root. |
| `RETROWEB_PUBLIC_PROXY_PORT` | `8081` | Port advertised to visitors for the forwarding proxy. |
| `RETROWEB_PROXY_BIND` | `127.0.0.1:8081` | TCP address and port on which the forwarding-proxy process listens. |
| `RETROWEB_PROXY_WORKERS` | `4` | Number of forwarding-proxy workers; values are clamped to 1–8. |

The public origin intentionally supports HTTP only because the target clients
may not support current TLS. Put any modern administrative interface on a
separate, properly secured origin.

## Forwarding proxy

Start the proxy on loopback first:

```sh
RETROWEB_PROXY_BIND=127.0.0.1:8081 \
RETROWEB_PROXY_WORKERS=4 \
php bin/forward-proxy.php
```

The proxy also uses `RETROWEB_PUBLIC_ORIGIN` and
`RETROWEB_PUBLIC_BASE_PATH`. `RETROWEB_PROXY_WORKERS` is limited to 1–8.

A generic systemd unit can supervise it:

```ini
[Unit]
Description=RetroWEB forwarding proxy
After=network-online.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/srv/retroweb
Environment=RETROWEB_PUBLIC_ORIGIN=http://retro.example.org
Environment=RETROWEB_PROXY_BIND=127.0.0.1:8081
Environment=RETROWEB_PROXY_WORKERS=4
ExecStart=/usr/bin/php /srv/retroweb/bin/forward-proxy.php
Restart=on-failure
PrivateTmp=true
NoNewPrivileges=true

[Install]
WantedBy=multi-user.target
```

Bind to loopback when a firewall or TCP reverse proxy exposes the service.
RetroWEB is an HTTP forward proxy only: it does not support HTTPS `CONNECT`.

Only expose it publicly after configuring a firewall, process supervision,
connection limits and monitoring. The file `var/data/forward-proxy.disabled`
acts as an emergency kill switch while present.

Enable and inspect the example systemd service with:

```sh
systemctl daemon-reload
systemctl enable --now retroweb-forward-proxy.service
systemctl status retroweb-forward-proxy.service
journalctl -u retroweb-forward-proxy.service
```

## Built-in limits

The defaults are deliberately conservative and are currently defined in code:

- source HTML: 4 MiB, at most five redirects;
- source images: 8 MiB, at most five redirects;
- decoded images: at most 12,000 pixels per dimension and 16 million pixels;
- direct form bodies: 64 KiB; forwarding-proxy requests: 80 KiB;
- converted image cache: one hour, 2,000 files and 256 MiB;
- rendered snapshots: 15 minutes;
- upstream cookies: one hour;
- saved browser settings: seven days;
- device submissions: three attempts per client address per hour.

Password fields, authentication, file uploads, unsafe target addresses, generic
downloads and HTTPS `CONNECT` are rejected independently of these limits.

## Retention

Run this command periodically, for example from a systemd timer or cron:

```sh
php bin/cleanup.php
```

A daily cron entry is sufficient for the default retention periods:

```cron
15 3 * * * cd /srv/retroweb && /usr/bin/php bin/cleanup.php
```

Keep `var/` outside release archives when using immutable releases, and preserve
it separately across upgrades. Never commit its generated databases or keys.

## Backups and upgrades

Back up `var/data` while the web and proxy processes are stopped, or use the
SQLite online-backup mechanism. Cached files below `var/cache` do not need to be
backed up.

For an upgrade:

1. stop the forwarding proxy and temporarily stop writes through the web UI;
2. back up `var/data`;
3. install the new source and run `composer install --no-dev --optimize-autoloader`;
4. keep or relink the existing `var` directory;
5. run `php bin/verify.php` and `composer check`;
6. restart the services and verify both the web UI and forwarding proxy.

Do not copy generated keys or databases into a source release or Git repository.

## Managing browser submissions

RetroWEB deliberately has no web-based administration backend. Submitted device
and browser corrections remain private in `var/data/device-submissions-v2.sqlite`
until an operator reviews them from the command line.

List pending submissions:

```sh
php bin/device-submissions.php list
```

Each row contains the private submission ID, device, browser, observed
User-Agent and the legacy confirmation count. Do not publish the full User-Agent
without checking that it contains no identifying information.

### Configuring a browser profile

Profiles are ordinary PHP arrays in `config/profiles/builtin.php`. There is no
database editor or administration form for profile defaults. A profile entry
has this shape:

```php
'example_browser' => [
    'version' => 1,
    'label' => 'Example Browser on Example Device',
    'html' => 'html32',
    'encoding' => 'Windows-1252',
    'viewport' => [320, 240],
    'image' => [240, 180],
    'budget_kb' => 125,
    'delivery' => 'paginated',
    'image_mode' => 'inline',
    'capabilities' => ['tables' => true, 'forms' => true],
    'workarounds' => [],
],
```

The fields mean:

| Field | Meaning |
| --- | --- |
| array key | Stable lowercase profile ID. Use letters, numbers and underscores; do not rename an ID after it has been published. |
| `version` | Profile revision. Increment it when changing compatibility, dimensions, budgets, capabilities or workarounds. |
| `label` | Human-readable name shown in the manual profile selector. |
| `html` | Output level: `html20`, `html32` or `html401-basic`. Choose the oldest level that renders reliably. |
| `encoding` | Output character encoding. Supported values are `UTF-8`, `ISO-8859-1`, `Windows-1252` and `Macintosh`. |
| `viewport` | Browser content width and height in pixels, excluding device or browser chrome where possible. |
| `image` | Maximum converted image width and height. Keep these at or below the viewport and leave room for margins or scrollbars. |
| `budget_kb` | Approximate memory budget for one rendered page, from 10 to 2,048 KiB. RetroWEB paginates before the estimated HTML and decoded-image cost exceeds this value. |
| `delivery` | `paginated` for constrained devices or `unsplit` only when the browser can reliably load the complete output. |
| `image_mode` | `inline` to show converted GIFs or `links` to replace them with links. Use `links` for very constrained browsers. |
| `capabilities.tables` | Whether table markup is retained. Set `false` when the browser renders tables incorrectly or uses too much memory. |
| `capabilities.forms` | Whether safe, non-sensitive forms may be retained. Password fields and other sensitive forms remain blocked globally. |
| `workarounds` | Narrow browser-specific output fixes. Leave empty unless a tested workaround already exists in the renderer. |

Do not derive memory settings from screen size alone. Start conservatively and
test on the actual browser or device:

1. measure the usable content viewport;
2. confirm the highest reliable HTML level and output encoding;
3. start with small inline images, or `links` on memory-constrained devices;
4. load text-heavy, image-heavy, table and form fixtures;
5. increase `budget_kb` gradually until larger pages work without browser
   crashes, truncated documents or failed image decoding;
6. reduce the last successful value to leave a safety margin;
7. record the observed device/browser evidence in the signature's `provenance`.

Browser settings selected through the RetroWEB settings page are per-client
overrides. They do not change `builtin.php` and should not be used as a
substitute for tested profile defaults.

### Configuring detection

Detection rules are ordered entries in `config/profiles/signatures.php`. Use an
anchored, device-specific regular expression whenever possible:

```php
[
    'profile' => 'example_browser',
    'status' => 'verified',
    'pattern' => '/^ExampleBrowser\/2\.0 \(ExampleDevice\)$/i',
    'provenance' => 'physical device submission, 2026-11-12',
],
```

Use `verified` only after testing the exact browser/device combination. Use
`unverified` for catalogue evidence that has not been reproduced physically.
Avoid broad expressions such as a vendor name alone: an ambiguous match falls
back to the generic profile by design.

For a new or incorrectly detected browser:

1. inspect the submitted User-Agent and reproduce the current detection locally;
2. add or adjust the browser profile in `config/profiles/builtin.php`;
3. add the narrowest reliable User-Agent rule to
   `config/profiles/signatures.php`;
4. add a regression case to `tests/run.php` or a dedicated profile test;
5. run `composer check` and `composer integration`;
6. deploy the tested source while preserving `var/`;
7. approve the submission only after the deployed detector selects the intended
   profile.

Approve or reject a reviewed submission with:

```sh
php bin/device-submissions.php approve ID
php bin/device-submissions.php reject ID
```

Approval runs the current profile detector against the privately stored
User-Agent and publishes only the device, browser, selected profile and
verification status in the community catalogue. It does not publish the full
User-Agent. Rejection keeps the report out of the public catalogue.

### AI-assisted maintenance

This maintenance can be performed manually, or delegated to a coding assistant
that can edit and test the repository. Give it only the relevant submission and
a disposable branch or worktree, require a reviewed diff and passing tests, and
keep deployment and final approval as separate actions. If an assistant also
deploys over SSH, use a temporary least-privilege account or narrowly scoped
deployment key. Do not paste a reusable root password or private key into a
prompt, repository, issue or log.

## Post-installation checks

Run these checks before exposing the service:

```sh
composer check
curl -fsS http://retro.example.org/ | grep '<h1>RetroWEB</h1>'
curl -fsS --http1.0 -x http://127.0.0.1:8081 \
  http://example.com/ | grep 'RetroWEB search'
```

Also verify that the service account can create files below `var/data` and
`var/cache`, while the web server cannot serve the repository root or `.git`.

## Troubleshooting

- **`Cannot create` or `Not writable`**: rerun `php bin/verify.php` as the
  service account and correct ownership of `var/cache` and `var/data`.
- **Proxy starts but pages fail**: confirm outbound DNS, HTTP and HTTPS access;
  RetroWEB validates DNS answers and rejects private or reserved destinations.
- **Wrong links or form actions**: check `RETROWEB_PUBLIC_ORIGIN` and
  `RETROWEB_PUBLIC_BASE_PATH` in both the PHP web process and proxy service.
- **Wrong advertised proxy port**: set `RETROWEB_PUBLIC_PROXY_PORT`; this is
  independent of the local `RETROWEB_PROXY_BIND` address.
- **HTTP 429 responses**: inspect traffic and rate limits, and check whether
  `var/data/forward-proxy.disabled` exists.
- **Changes appear stale**: clear `var/cache/snapshots` and converted-image
  cache files, then restart the forwarding proxy. Do not delete generated keys
  or SQLite databases while troubleshooting.
