# RetroWEB

RetroWEB makes modern websites usable in classic web browsers. It fetches a
page over modern HTTPS, extracts the useful content, converts it to legacy HTML
and optionally converts images to small GIF files. Large pages can be split to
fit the memory limits of older computers, PDAs and mobile devices.

A publicly hosted instance is available at
[retroweb.bjrno.com](http://retroweb.bjrno.com/). Its HTTP forwarding proxy is
available on `retroweb.bjrno.com:8081` for browsers that cannot access modern
HTTPS sites themselves.

## Important limitations

RetroWEB deliberately trades modern browser security for compatibility with
old, HTTP-only clients. Do not use it for logins, payments or private data.
Password fields, file uploads, authentication, generic downloads and CONNECT
tunnels are blocked. Only GET, HEAD and constrained URL-encoded POST requests
are supported.

Running a public proxy also creates abuse and bandwidth risks. Review the rate
limits, traffic limits and kill switch before exposing an installation to the
internet.

## Requirements

- PHP 8.0 or newer;
- PHP extensions: cURL, DOM, GD, mbstring, OpenSSL and SQLite3;
- Composer;
- writable `var/cache` and `var/data` directories.

## Quick start

```sh
composer install
composer check
php -S 127.0.0.1:8080 -t public
```

Open `http://127.0.0.1:8080/`. See [docs/INSTALL.md](docs/INSTALL.md) for a
generic web-server and forwarding-proxy setup.

For a self-hosted installation, set `RETROWEB_PUBLIC_ORIGIN` to its public HTTP
origin. RetroWEB defaults to the origin of the hosted public instance.

## Checks

```sh
composer check
composer integration
```

The normal check verifies the runtime, lints the PHP files, checks architecture
boundaries and runs the network-free test suite. The integration check starts
temporary loopback web and proxy processes.

## Project structure

- `public/`: web entry points; configure this as the web document root;
- `src/`: application, domain, rendering, persistence and transport code;
- `config/profiles/`: legacy browser profiles and detection signatures;
- `bin/forward-proxy.php`: HTTP forwarding-proxy process;
- `bin/cleanup.php`: retention cleanup;
- `tests/`: network-free and loopback integration tests;
- `var/`: ignored runtime cache, settings, keys and SQLite databases.

## Privacy and security

See [PRIVACY.md](PRIVACY.md) for stored operational data and retention guidance,
and [SECURITY.md](SECURITY.md) for responsible vulnerability reporting.

## Browser submissions

RetroWEB has no administration backend. Submitted device and browser reports
remain private until an operator reviews them from the command line, updates the
profile/signature configuration when needed, tests the change and explicitly
approves or rejects the submission. See
[Managing browser submissions](docs/INSTALL.md#managing-browser-submissions).

## Support

This project is published as-is. No individual support, managed installation or
hosting is provided. The public instance at `retroweb.bjrno.com` is a personal
service and carries no availability guarantee.

## License

Copyright © 2026 Björn Heirman. Released under the [MIT License](LICENSE).
