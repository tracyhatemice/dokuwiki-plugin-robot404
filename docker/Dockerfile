# PHP 8.2 is the oldest version current DokuWiki supports (composer.json
# requires "php": ">=8.2"), so the plugin is developed and tested against it.
FROM php:8.2-apache

# Same php.ini as DokuWiki's CI (setup-php). Without any php.ini, PHP prints
# notices to stdout, which counts as output: DokuWiki's test bootstrap then can
# no longer start a session and TestRequest fails on the missing $_SESSION.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Xdebug is only here for xdebug_get_headers(): under the CLI SAPI it is the
# only way DokuWiki's TestRequest can see the status code and headers a request
# sent. It stays off unless a service sets XDEBUG_MODE.
RUN pecl install xdebug-3.4.5 \
    && docker-php-ext-enable xdebug \
    && echo 'xdebug.mode=off' > /usr/local/etc/php/conf.d/zz-xdebug-mode.ini

# seed-dokuwiki.sh copies the fork in here as the (unprivileged) host user.
# The plugin bind mounts land below lib/plugins, so Docker creates those
# directories itself and they must already be writable for everyone.
RUN mkdir -p /dokuwiki/lib/plugins \
    && chmod 0777 /dokuwiki /dokuwiki/lib /dokuwiki/lib/plugins
