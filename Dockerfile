FROM ghcr.io/nayleen/php:8.5@sha256:b74eae1ef38c5ee2a1a451d66ca07bcf4714e3c903c4b8ea061101b04fa7b374

COPY --link --chown=1000:1000 ./composer.* /app/src/

RUN --mount=type=cache,target=/app/var/composer,uid=1000 \
    composer install --no-dev --no-progress --no-scripts --prefer-dist --optimize-autoloader --strict-psr-autoloader

COPY --link --chown=1000:1000 ./ /app/src/

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD ["php", "/app/src/app.php", "healthcheck"]

CMD ["php", "app.php"]
