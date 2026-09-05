# EspoCRM (fluia fork) — fast & light, HTTP-only.
# Single image: nginx + php-fpm on Alpine. No TLS inside (terminated at
# Traefik / cluster ingress). Multi-stage: composer + node/grunt build,
# slim runtime (~150MB).
#
# Build:  docker build -t fluia/espocrm:slim .
# Run:    docker compose up -d   (see docker-compose.yml)

# Extension installer (first: referenced by the runtime stage below).
FROM mlocati/php-extension-installer:2 AS ext-installer

# ---------------------------------------------------------------- Stage 1: PHP deps
FROM composer:2 AS vendor

WORKDIR /src

COPY composer.json composer.lock ./
COPY application/ ./application/
COPY dev/ ./dev/

# Post-install hook (dev/vendor-cleanup.php) runs automatically and trims vendor/.
RUN composer install \
        --no-dev \
        --prefer-dist \
        --no-progress \
        --no-interaction \
        --ignore-platform-reqs


# ---------------------------------------------------------------- Stage 2: frontend build
# client/lib/* and client/css/* are gitignored build artifacts produced by grunt.
FROM node:20-alpine AS frontend

WORKDIR /src

COPY package.json package-lock.json Gruntfile.js jsconfig.json ./
COPY js/ ./js/
COPY frontend/ ./frontend/
COPY client/ ./client/
COPY html/ ./html/
COPY application/ ./application/

RUN npm ci --no-audit --no-fund \
    && npx grunt internal \
    && npm cache clean --force


# ---------------------------------------------------------------- Stage 3: runtime
FROM php:8.4-fpm-alpine

LABEL org.opencontainers.image.source="https://github.com/espocrm/espocrm"
LABEL org.opencontainers.image.description="EspoCRM, fluia CRM-only fork. HTTP-only, nginx+php-fpm."

ENV ESPOCRM_VERSION=10.0.7

WORKDIR /var/www/html

# nginx + supervisor + php extensions. pdo_mysql for MariaDB/MySQL.
# For PostgreSQL add: pdo_pgsql (one-line change below).
COPY --from=ext-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN apk add --no-cache nginx supervisor curl \
    && install-php-extensions pdo_mysql gd zip bcmath exif opcache pcntl fileinfo sockets \
    # www-data must own only the writable paths (see Permission.php writableMap)
    && mkdir -p data/logs data/cache client/custom custom/Espo/Custom custom/Espo/Modules

# App source (respects .dockerignore).
COPY . ./
# Built artifacts from the previous stages.
COPY --from=vendor /src/vendor ./vendor
COPY --from=frontend /src/client ./client

RUN set -eux; \
    # Drop build-only / dev-only weight from the final image.
    rm -rf tests dev frontend .github .idea .vscode \
        node_modules Gruntfile.js package.json package-lock.json \
        jsconfig.json tsconfig.json po.js lang.js diff.js \
        data/logs data/cache data/upload data/tmp; \
    mkdir -p data/logs data/cache; \
    # Pre-compress static assets once; nginx serves them via gzip_static.
    find client/lib client/css -type f \( -name '*.js' -o -name '*.css' \) \
        -exec gzip -9 -k {} \; ; \
    # Permissions: code read-only, writable dirs for www-data.
    find . -type d -exec chmod 755 {} +; \
    find . -type f -exec chmod 644 {} +; \
    chmod +x bin/command; \
    chown -R root:root application client vendor public html install *.php; \
    chown -R www-data:www-data data client/custom custom

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/opcache.ini /usr/local/etc/php/conf.d/zz-espocrm.ini
COPY docker/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-espocrm.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p /run/nginx /var/log/supervisor \
    && nginx -t

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=10s --start-period=15s --retries=3 \
    CMD curl -fsS -o /dev/null http://127.0.0.1/ || exit 1

ENTRYPOINT ["entrypoint.sh"]
