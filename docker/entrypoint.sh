#!/usr/bin/env bash
set -e

cd /var/www/html

mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

if [ "${DB_CONNECTION:-mysql}" = "mysql" ] && [ -n "${DB_HOST:-}" ]; then
    echo "[NEXORA] Esperando conexión a MySQL (${DB_HOST}:${DB_PORT:-3306})..."
    for i in $(seq 1 30); do
        if php -r "try { new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: 3306) . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); exit(0); } catch (Throwable \$e) { exit(1); }"; then
            echo "[NEXORA] MySQL disponible."
            break
        fi
        sleep 2
    done
fi

php artisan storage:link --force || true
php artisan migrate --force
php artisan db:seed --class=DatabaseSeeder --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
