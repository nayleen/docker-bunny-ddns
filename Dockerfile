FROM ghcr.io/nayleen/php:8.5@sha256:7599fd9244eeec7d82a4a6b9324e53ab933ecfc247f97a7927cbfa9234517a4c

COPY --link --chown=1000:1000 ./composer.* /app/src/

RUN --mount=type=cache,target=/app/var/composer,uid=1000 \
    composer install --no-dev --no-progress --no-scripts --prefer-dist --optimize-autoloader --strict-psr-autoloader

COPY --link --chown=1000:1000 ./ /app/src/

CMD ["php", "app.php"]
