#!/bin/bash
# Entry point of the `wiki` service: a throwaway DokuWiki with fixtures for
# every case the plugin handles, served by Apache on port 80.
set -euo pipefail

wiki=/var/www/html
export DOKUWIKI_DIR=$wiki
bash "$wiki/lib/plugins/robot404/docker/seed-dokuwiki.sh" true

# Rewritten on every start so the wiki always matches what smoke.sh expects.
cat > "$wiki/conf/local.php" <<'EOC'
<?php
$conf['title'] = 'robot404 dev';
$conf['lang'] = 'en';
$conf['license'] = 'cc-by-sa';
$conf['useacl'] = 1;
$conf['superuser'] = '@admin';
$conf['hidepages'] = '^:hidden:';
$conf['indexdelay'] = 0;
EOC

# password is "admin"; this wiki is a throwaway dev container on loopback
printf 'admin:%s:Admin:admin@example.com:admin,user\n' \
    "$(php -r 'echo password_hash("admin", PASSWORD_DEFAULT);')" \
    > "$wiki/conf/users.auth.php"

cat > "$wiki/conf/acl.auth.php" <<'EOA'
*           @ALL   1
private:*   @ALL   0
*           @user  8
EOA

mkdir -p "$wiki/data/pages/hidden" "$wiki/data/pages/private"
echo '====== robot404 dev ======' > "$wiki/data/pages/start.txt"
echo '====== Hidden page ======' > "$wiki/data/pages/hidden/page.txt"
echo '====== Private start ======' > "$wiki/data/pages/private/start.txt"
echo '====== Private secret ======' > "$wiki/data/pages/private/secret.txt"

chown -R www-data:www-data "$wiki/conf" "$wiki/data"

exec apache2-foreground
