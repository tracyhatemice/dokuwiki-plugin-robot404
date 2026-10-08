#!/bin/bash
# Copy the DokuWiki fork into the container, then run the given command.
#
#   seed-dokuwiki.sh <command> [args...]
#
# The fork is mounted read-only at /dokuwiki-src and copied to $DOKUWIKI_DIR
# (default /dokuwiki), so tests, caches and Docker's bind-mount points never
# touch the checkout on the host. Only the plugins DokuWiki itself ships (the
# ones its .gitignore whitelists) are copied: whatever else sits in the fork's
# lib/plugins (other work in progress, symlinks to host paths) stays out, and
# this plugin comes in through its own bind mount.
set -euo pipefail

src=/dokuwiki-src
dst=${DOKUWIKI_DIR:-/dokuwiki}
marker="$dst/.robot404-seeded"

[ -f "$src/doku.php" ] || { echo "seed-dokuwiki: no DokuWiki at $src (set DOKUWIKI_PATH)" >&2; exit 1; }

# The marker is written only after a complete copy, so an interrupted seed is
# redone on the next start instead of leaving a half-copied wiki behind.
#
# Entry by entry with a plain `cp -R`: $dst, lib and lib/plugins already exist
# (image, bind-mount points) and belong to root, and tar or `cp -a` would fail
# trying to restore their mode and timestamps.
if [ ! -f "$marker" ]; then
    shopt -s dotglob
    for entry in "$src"/*; do
        case "${entry##*/}" in .git | lib) continue ;; esac
        cp -R "$entry" "$dst/"
    done
    for entry in "$src"/lib/*; do
        [ "${entry##*/}" = plugins ] || cp -R "$entry" "$dst/lib/"
    done
    sed -n 's#^!/lib/plugins/##p' "$src/.gitignore" | while read -r entry; do
        if [ -e "$src/lib/plugins/$entry" ]; then
            cp -R "$src/lib/plugins/$entry" "$dst/lib/plugins/"
        fi
    done
    touch "$marker"
fi

exec "$@"
