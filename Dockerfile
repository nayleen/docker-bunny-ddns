FROM ghcr.io/nayleen/php:8.5@sha256:a278125f4685bfa9bae950487ead0da317def987fdfba220bc22ecc6981471af

COPY --link --chown=1000:1000 ./composer.* /app/src/

RUN --mount=type=cache,target=/app/var/composer,uid=1000 \
    composer install --no-dev --no-progress --no-scripts --prefer-dist --optimize-autoloader --strict-psr-autoloader

COPY --link --chown=1000:1000 ./ /app/src/

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD ["php", "/app/src/app.php", "healthcheck"]

CMD ["php", "app.php"]
