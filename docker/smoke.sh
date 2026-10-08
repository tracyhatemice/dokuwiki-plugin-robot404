#!/bin/bash
# HTTP checks against the `wiki` service, run as `docker compose run --rm smoke`.
#
# These cover what the unit tests cannot: a refused robot ends the request, which
# would end the test run too. The fixtures come from docker/wiki.sh; settings are
# switched through conf/local.protected.php in the wiki volume, mounted at /wiki.
set -uo pipefail

root="${WIKI_URL:-http://wiki}"
protected=/wiki/conf/local.protected.php
bot='Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'
browser='Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36'

failed=0
response=$(mktemp)
headers=$(mktemp)
trap 'rm -f "$response" "$headers" "$protected"' EXIT

# plugin_conf <setting> <value>: override a plugin setting for the following checks
plugin_conf() {
    [ -f "$protected" ] || echo '<?php' > "$protected"
    echo "\$conf['plugin']['robot404']['$1'] = '$2';" >> "$protected"
}

# matches <pattern>: headers+body match the pattern, or with a leading ! do not
matches() {
    if [ "${1:0:1}" = '!' ]; then
        ! cat "$headers" "$response" | grep -qiE "${1:1}"
    else
        cat "$headers" "$response" | grep -qiE "$1"
    fi
}

# expect <status> <user agent> <query or /path> [<pattern, see matches>] [curl args...]
expect() {
    local status=$1 agent=$2 query=$3 pattern=${4:-} url got
    shift 4 2>/dev/null || shift $#
    case $query in
        /*) url=$root$query ;;
        *) url=$root/doku.php?$query ;;
    esac
    got=$(curl -gsS -o "$response" -D "$headers" -w '%{http_code}' -A "$agent" "$@" "$url")
    if [ "$got" != "$status" ]; then
        echo "FAIL $query (${agent%%/*}...): status $got, expected $status"
        failed=1
    elif [ -n "$pattern" ] && ! matches "$pattern"; then
        echo "FAIL $query (${agent%%/*}...): no match for /$pattern/"
        failed=1
    else
        echo "ok   $status $query${pattern:+  /$pattern/}"
    fi
}

echo "== robots"
expect 200 "$bot" 'id=start' 'name="robots" content="index,follow"'
expect 404 "$bot" 'id=start&do=login'
expect 404 "$bot" 'id=start&do=diff'
expect 404 "$bot" 'id=start&do[edit]=1'
expect 404 "$bot" 'id=hidden:page'
expect 404 "$bot" 'id=private:secret'
expect 404 "$bot" 'id=private:' '' -L  # core redirects to private:start first
expect 404 "$browser" 'id=start&do=login&isrobot404=1'

expect 200 "$bot" '/feed.php' '<channel'

echo "== robots, rss disallowed"
plugin_conf disableactions rss
expect 404 "$bot" '/feed.php'
expect 200 "$browser" '/feed.php' '<channel'
rm -f "$protected"

echo "== visitors"
expect 200 "$browser" 'id=start' 'name="robots" content="index,follow"'
expect 200 "$browser" 'id=start&do=login' '^X-Robots-Tag: noindex,nofollow'
expect 200 "$browser" 'id=hidden:page' 'name="robots" content="noindex,nofollow"'
expect 404 "$browser" 'id=private:secret' 'id="dw__login"'
expect 404 "$browser" 'id=private:' 'id="dw__login"' -L
expect 200 "$browser" 'id=start&do=login' 'id="dw__login"'

echo "== visitors, aclresponse plain"
plugin_conf aclresponse plain
expect 404 "$browser" 'id=private:secret' '!dw__login|Permission Denied'
expect 200 "$browser" 'id=private:secret&do=login' 'id="dw__login"'
expect 200 "$browser" 'id=private:secret' 'Private secret' -u admin:admin
rm -f "$protected"

echo "== users who may read"
expect 200 "$browser" 'id=private:secret' 'Private secret' -u admin:admin

[ "$failed" = 0 ] && echo "all checks passed"
exit "$failed"
