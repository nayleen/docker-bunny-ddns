FROM ghcr.io/nayleen/php:8.5@sha256:9398aa05578147e411b88338349fb813e826524496766a480e16f51208fa5970

COPY --link --chown=1000:1000 ./composer.* /app/src/

RUN --mount=type=cache,target=/app/var/composer,uid=1000 \
    composer install --no-dev --no-progress --no-scripts --prefer-dist --optimize-autoloader --strict-psr-autoloader

COPY --link --chown=1000:1000 ./ /app/src/

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD ["php", "/app/src/app.php", "healthcheck"]

CMD ["php", "app.php"]
