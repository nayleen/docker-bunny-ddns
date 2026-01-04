FROM ghcr.io/nayleen/php:8.5

COPY --link --chown=1000:1000 ./composer.* /app/src/

RUN --mount=type=cache,target=/app/var/composer,uid=1000 \
    composer install --classmap-authoritative --no-dev --no-scripts

COPY --link --chown=1000:1000 ./ /app/src/

CMD ["php", "app.php"]
