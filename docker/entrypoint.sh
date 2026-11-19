#!/bin/sh
# Runs on every container start, then hands off to Apache (CMD).
set -e

DATA_DIR=/data
DB_FILE="${DB_DATABASE:-$DATA_DIR/database.sqlite}"

# 1. SQLite database on the named volume, so data survives restarts and rebuilds.
mkdir -p "$(dirname "$DB_FILE")"
[ -f "$DB_FILE" ] || touch "$DB_FILE"

# 2. APP_KEY encrypts sessions and cookies. If it changed on every start, every user
#    would be logged out, so generate it once, keep it on the volume, and reuse it.
#    An APP_KEY passed in from the environment takes precedence.
if [ -z "${APP_KEY:-}" ]; then
    KEY_FILE="$DATA_DIR/app_key"
    if [ ! -s "$KEY_FILE" ]; then
        php artisan key:generate --show --no-ansi > "$KEY_FILE"
        chmod 600 "$KEY_FILE"
    fi
    APP_KEY="$(cat "$KEY_FILE")"
    export APP_KEY
fi

# 3. Apply any pending migrations. --force is required outside the local environment.
php artisan migrate --force --no-interaction

# 4. Cache config, routes, views and events. Building the config cache here, from the
#    CLI that can see the container's environment variables, also means PHP under
#    Apache doesn't need to read those variables itself.
php artisan optimize --no-ansi

# Apache runs PHP as www-data, which must be able to write the database and caches.
chown -R www-data:www-data "$DATA_DIR" storage bootstrap/cache

exec "$@"
