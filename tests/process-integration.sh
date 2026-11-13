#!/bin/sh
set -eu
root=$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)
direct_port=${RETROWEB_DIRECT_TEST_PORT:-18099}
proxy_port=${RETROWEB_PROXY_TEST_PORT:-18098}
direct_data=$(mktemp -d /tmp/retroweb-direct-data.XXXXXX)
cleanup() { kill "$direct_pid" "$proxy_pid" 2>/dev/null || true; rm -rf "$direct_data"; }
trap cleanup EXIT INT TERM

RETROWEB_PUBLIC_ORIGIN=http://retro.example.test RETROWEB_PUBLIC_PROXY_PORT=8081 RETROWEB_DATA_DIR="$direct_data" php -S "127.0.0.1:$direct_port" -t "$root/public" >/tmp/retroweb-direct-test.log 2>&1 &
direct_pid=$!
RETROWEB_PROXY_BIND="127.0.0.1:$proxy_port" RETROWEB_PROXY_WORKERS=1 php "$root/bin/forward-proxy.php" >/tmp/retroweb-proxy-test.log 2>&1 &
proxy_pid=$!

i=0
while ! curl -fsS "http://127.0.0.1:$direct_port/" >/tmp/retroweb-home.html 2>/dev/null; do i=$((i+1)); [ "$i" -lt 20 ] || exit 1; sleep 0.1; done
grep -q '<h1>RetroWEB</h1>' /tmp/retroweb-home.html
grep -q '<h2>Features</h2>' /tmp/retroweb-home.html
grep -q 'bjorn@heirman.nl' /tmp/retroweb-home.html
iconv -f ISO-8859-1 -t UTF-8 /tmp/retroweb-home.html | grep -q 'Björn Heirman</a>'
LC_ALL=C grep -Fq '>BjornsBlog.nl</a> | <a href="/?url=https%3A%2F%2Fgithub.com%2FBjornB2%2Fretroweb">GitHub</a>' /tmp/retroweb-home.html
grep -q 'HTTP proxy server: <b>retro.example.test</b>' /tmp/retroweb-home.html
grep -q 'Port: <b>8081</b>' /tmp/retroweb-home.html
grep -q '<form method="get" action="http://retro.example.test/">' /tmp/retroweb-home.html
if grep -Eqi '(href|src|action)="[^"]*/beta(?:/|[?"#])' /tmp/retroweb-home.html; then exit 1; fi

curl -fsS -D /tmp/retroweb-settings.headers -A 'MacWeb/F2.0 libwww/2.17' "http://127.0.0.1:$direct_port/?settings=1&custom=1" >/tmp/retroweb-settings.html
grep -qi 'Content-Type: text/html; charset=Macintosh' /tmp/retroweb-settings.headers
grep -q 'Automatic (MacWeb 2.0)' /tmp/retroweb-settings.html
if grep -Eq '(href|src|action)="[^"]*&amp;[^"]*"' /tmp/retroweb-settings.html; then exit 1; fi

curl -fsS -A 'MacWeb/F2.0 libwww/2.17' "http://127.0.0.1:$direct_port/" >/tmp/retroweb-mac-home.html
iconv -f MACINTOSH -t UTF-8 /tmp/retroweb-mac-home.html | grep -q 'Björn Heirman</a>'

direct_ua="MacWeb/F2.0 libwww/2.17 direct-integration-$direct_pid"
curl -fsS --http1.0 -A "$direct_ua" -d 'device=Test+Mac&browser=MacWeb' "http://127.0.0.1:$direct_port/client-info.php" >/tmp/retroweb-direct-submission.html
grep -q '<h1>Submission received</h1>' /tmp/retroweb-direct-submission.html
grep -q 'awaiting review; you do not need to submit it again' /tmp/retroweb-direct-submission.html
curl -fsS --http1.0 -A "$direct_ua" -d 'device=Test+Mac&browser=MacWeb' "http://127.0.0.1:$direct_port/client-info.php" >/tmp/retroweb-direct-duplicate.html
grep -q 'already been received and is awaiting review' /tmp/retroweb-direct-duplicate.html
php -r 'require $argv[1]."/vendor/autoload.php"; $rows=(new RetroWEB\Infrastructure\Persistence\DeviceSubmissionRepository($argv[2]."/device-submissions-v2.sqlite"))->pending(); if(count($rows)!==1||(int)$rows[0]["confirmations"]!==1)exit(1);' "$root" "$direct_data"

code=$(curl -sS -o /tmp/retroweb-block.html -w '%{http_code}' "http://127.0.0.1:$direct_port/?url=http%3A%2F%2F127.0.0.1%2F")
[ "$code" = 400 ] && grep -q 'Private target is forbidden' /tmp/retroweb-block.html
proxy_headers=$(curl -sS --max-time 3 -D - -o /dev/null -x "http://127.0.0.1:$proxy_port" https://example.com/ 2>/dev/null || true)
printf '%s' "$proxy_headers" | grep -q 'HTTP/1.0 400 Bad Request'

test_ua="MacWeb/F2.0 libwww/2.17 integration-$direct_pid"
curl -fsS --http1.0 -A "$test_ua" -x "http://127.0.0.1:$proxy_port" 'http://example.com/.retroweb/client-info?source=https%3A%2F%2Fexample.com%2F' >/tmp/retroweb-forward-client.html
grep -q "Observed browser: $test_ua" /tmp/retroweb-forward-client.html

concurrency_dir=$(mktemp -d /tmp/retroweb-concurrency.XXXXXX)
pids=''; i=1
while [ "$i" -le 12 ]; do php "$root/tests/concurrency-worker.php" "$concurrency_dir" "$i" & pids="$pids $!"; i=$((i+1)); done
for pid in $pids; do wait "$pid"; done
php -r 'require $argv[1]."/vendor/autoload.php"; $rows=(new RetroWEB\Infrastructure\Persistence\AggregateStatistics($argv[2]."/statistics.sqlite"))->totals(); if(($rows[0]["count"]??0)!==12)exit(1);' "$root" "$concurrency_dir"
rm -rf "$concurrency_dir"
echo 'RetroWEB process integration passed.'
