# Development image: PHP CLI with the built-in web server.
FROM php:8.4-cli

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions intl zip opcache \
    && apt-get update && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    PHP_CLI_SERVER_WORKERS=4
WORKDIR /app
EXPOSE 8000
CMD ["sh", "docker/start.sh"]
