# Privacy

RetroWEB needs limited operational state for settings, abuse prevention and
aggregate statistics. A default installation may store:

- signed browser settings and short-lived server-side settings;
- cached converted images and rendered-page snapshots;
- aggregate page, profile, result, timing and traffic counters;
- keyed hashes derived from client addresses for rate limiting and approximate
  visit counts;
- device and browser corrections submitted through the client-information page,
  including the observed User-Agent, until they are reviewed.

RetroWEB is designed not to retain requested page URLs, search terms, form
contents or raw client IP addresses in its application databases. Web-server,
firewall or hosting-provider logs are separate and must be configured by the
operator.

Runtime databases, generated keys and caches live below `var/` and are excluded
from Git. Do not publish that directory's contents. Run `php bin/cleanup.php`
regularly and choose web-server log retention appropriate for your deployment.

Operators are responsible for providing any privacy notice required for their
own public instance and jurisdiction. The public instance at
`retroweb.bjrno.com` is operated separately from this source repository.
