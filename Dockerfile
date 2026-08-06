FROM ghcr.io/nayleen/php:8.5@sha256:8dfdddae3916d8c258e5946028d6cc2a99e63984b63176e7df46179610038406

COPY --link --chown=1000:1000 ./composer.* /app/src/

RUN --mount=type=cache,target=/app/var/composer,uid=1000 \
    composer install --no-dev --no-progress --no-scripts --prefer-dist --optimize-autoloader --strict-psr-autoloader

COPY --link --chown=1000:1000 ./ /app/src/

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD ["php", "/app/src/app.php", "healthcheck"]

CMD ["php", "app.php"]
