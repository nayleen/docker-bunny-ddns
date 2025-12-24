FROM ghcr.io/nayleen/php:8.4

COPY --link --chown=1000:1000 ./composer.* /app/src/

RUN --mount=type=cache,target=/app/var/composer,uid=1000 \
    composer install --no-dev --no-scripts --optimize-autoloader

COPY --link --chown=1000:1000 ./ /app/src/

CMD ["php", "-dopcache.enable_cli=0", "/app/src/app.php"]
