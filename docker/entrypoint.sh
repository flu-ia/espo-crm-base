#!/bin/sh
# Entrypoint: ensure writable paths, optionally rebuild, then run services.
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

# Rebuild caches on boot when requested (e.g. after mounting custom/ changes).
if [ "${REBUILD_ON_BOOT:-false}" = "true" ] && [ -f data/config.php ]; then
    echo "Rebuilding application cache..."
    su www-data -s /bin/sh -c "php rebuild.php" || echo "WARNING: rebuild failed."
fi

exec /usr/bin/supervisord -c /etc/supervisord.conf
