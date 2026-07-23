FROM ghcr.io/nayleen/php:8.5@sha256:6d6cc8222c22ccf48f799345741bb9ffb539c4802e6135e05ad4fa47de592782

COPY --link --chown=1000:1000 ./composer.* /app/src/

RUN --mount=type=cache,target=/app/var/composer,uid=1000 \
    composer install --no-dev --no-progress --no-scripts --prefer-dist --optimize-autoloader --strict-psr-autoloader

COPY --link --chown=1000:1000 ./ /app/src/

CMD ["php", "app.php"]
