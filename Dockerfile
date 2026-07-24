FROM ghcr.io/nayleen/php:8.5@sha256:760572f992f80cf0025b7a2d676a25de2ebd79efd60eb2e660a1ac91afcc7f1d

COPY --link --chown=1000:1000 ./composer.* /app/src/

RUN --mount=type=cache,target=/app/var/composer,uid=1000 \
    composer install --no-dev --no-progress --no-scripts --prefer-dist --optimize-autoloader --strict-psr-autoloader

COPY --link --chown=1000:1000 ./ /app/src/

CMD ["php", "app.php"]
