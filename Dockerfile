FROM ghcr.io/nayleen/php:8.5@sha256:30ac3d81ea3ccadbb0c0a38901efe470eac5d19b1d6cee02dbdd730a4ce95167

COPY --link --chown=1000:1000 ./composer.* /app/src/

RUN --mount=type=cache,target=/app/var/composer,uid=1000 \
    composer install --no-dev --no-progress --no-scripts --prefer-dist --optimize-autoloader --strict-psr-autoloader

COPY --link --chown=1000:1000 ./ /app/src/

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD ["php", "/app/src/app.php", "healthcheck"]

CMD ["php", "app.php"]
