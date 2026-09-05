#!/bin/sh
# Entrypoint: ensure writable paths, optionally auto-install, then run services.
set -e

cd /var/www/html

mkdir -p data/logs data/cache client/custom custom/Espo/Custom custom/Espo/Modules
chown -R www-data:www-data data client/custom custom 2>/dev/null || true

# Wait for DB if a host is configured (compose sets ESPOCRM_DATABASE_HOST).
# NOTE: /bin/sh here is busybox ash (no /dev/tcp), so the check runs via php.
if [ -n "${ESPOCRM_DATABASE_HOST:-}" ]; then
    echo "Waiting for database at ${ESPOCRM_DATABASE_HOST}:${ESPOCRM_DATABASE_PORT:-3306}..."
    for i in $(seq 1 60); do
        if php -r '$h = getenv("ESPOCRM_DATABASE_HOST"); $p = getenv("ESPOCRM_DATABASE_PORT") ?: "3306"; exit(@fsockopen($h, (int) $p) ? 0 : 1);'; then
            echo "Database reachable."
            break
        fi
        sleep 2
        if [ "$i" = "60" ]; then
            echo "WARNING: database not reachable, starting anyway."
        fi
    done
fi

# Automated initial installation on first boot if config.php does not exist
if [ ! -f data/config.php ] && [ -n "${ESPOCRM_DATABASE_HOST:-}" ]; then
    echo "Fresh setup: running automated EspoCRM installation..."
    su www-data -s /bin/sh -c "php bin/command config:populate" || true

    su www-data -s /bin/sh -c "php bin/command config:set database.host \"${ESPOCRM_DATABASE_HOST}\""
    su www-data -s /bin/sh -c "php bin/command config:set database.port \"${ESPOCRM_DATABASE_PORT:-3306}\""
    su www-data -s /bin/sh -c "php bin/command config:set database.dbname \"${ESPOCRM_DATABASE_NAME:-espocrm}\""
    su www-data -s /bin/sh -c "php bin/command config:set database.user \"${ESPOCRM_DATABASE_USER:-espocrm}\""
    su www-data -s /bin/sh -c "php bin/command config:set database.password \"${ESPOCRM_DATABASE_PASSWORD:-espocrm_password}\""
    su www-data -s /bin/sh -c "php bin/command config:set database.platform \"${ESPOCRM_DATABASE_PLATFORM:-Mysql}\""

    [ -n "${ESPOCRM_SITE_URL:-}" ] && su www-data -s /bin/sh -c "php bin/command config:set siteUrl \"${ESPOCRM_SITE_URL}\""
    [ -n "${ESPOCRM_CONFIG_LANGUAGE:-}" ] && su www-data -s /bin/sh -c "php bin/command config:set language \"${ESPOCRM_CONFIG_LANGUAGE}\""
    [ -n "${ESPOCRM_CONFIG_TIME_ZONE:-}" ] && su www-data -s /bin/sh -c "php bin/command config:set timeZone \"${ESPOCRM_CONFIG_TIME_ZONE}\""
    [ -n "${ESPOCRM_CONFIG_DATE_FORMAT:-}" ] && su www-data -s /bin/sh -c "php bin/command config:set dateFormat \"${ESPOCRM_CONFIG_DATE_FORMAT}\""
    [ -n "${ESPOCRM_CONFIG_TIME_FORMAT:-}" ] && su www-data -s /bin/sh -c "php bin/command config:set timeFormat \"${ESPOCRM_CONFIG_TIME_FORMAT}\""
    [ -n "${ESPOCRM_CONFIG_DEFAULT_CURRENCY:-}" ] && su www-data -s /bin/sh -c "php bin/command config:set defaultCurrency \"${ESPOCRM_CONFIG_DEFAULT_CURRENCY}\""
    [ -n "${ESPOCRM_CONFIG_BASE_CURRENCY:-}" ] && su www-data -s /bin/sh -c "php bin/command config:set baseCurrency \"${ESPOCRM_CONFIG_BASE_CURRENCY}\""
    [ -n "${ESPOCRM_CONFIG_APPLICATION_NAME:-}" ] && su www-data -s /bin/sh -c "php bin/command config:set applicationName \"${ESPOCRM_CONFIG_APPLICATION_NAME}\""

    su www-data -s /bin/sh -c "php bin/command config:set isInstalled true --type=bool"

    echo "Building database schema and application cache..."
    su www-data -s /bin/sh -c "php rebuild.php"

    if [ -n "${ESPOCRM_ADMIN_USERNAME:-}" ]; then
        echo "Creating admin user: ${ESPOCRM_ADMIN_USERNAME}..."
        su www-data -s /bin/sh -c "php bin/command create-admin-user \"${ESPOCRM_ADMIN_USERNAME}\"" || true
        if [ -n "${ESPOCRM_ADMIN_PASSWORD:-}" ]; then
            printf "%s\n" "${ESPOCRM_ADMIN_PASSWORD}" | su www-data -s /bin/sh -c "php bin/command set-password \"${ESPOCRM_ADMIN_USERNAME}\"" || true
        fi
    fi
fi

# Rebuild caches on boot when requested (e.g. after mounting custom/ changes).
if [ "${REBUILD_ON_BOOT:-false}" = "true" ] && [ -f data/config.php ]; then
    echo "Rebuilding application cache..."
    su www-data -s /bin/sh -c "php rebuild.php" || echo "WARNING: rebuild failed."
fi

# Auto-provision API User if ESPOCRM_API_KEY is configured and CRM is installed.
if [ -n "${ESPOCRM_API_KEY:-}" ] && [ -f data/config.php ]; then
    echo "Checking / provisioning API user..."
    API_USER_NAME="${ESPOCRM_API_USER_NAME:-api-user}"
    API_ROLE_NAME="${ESPOCRM_API_ROLE_NAME:-API Full Access}"
    su www-data -s /bin/sh -c "php bin/command create-api-user \"${API_USER_NAME}\" --api-key=\"${ESPOCRM_API_KEY}\" --role=\"${API_ROLE_NAME}\"" || echo "WARNING: API user provisioning skipped or failed."
fi

exec /usr/bin/supervisord -c /etc/supervisord.conf
