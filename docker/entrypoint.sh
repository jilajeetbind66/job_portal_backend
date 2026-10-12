#!/bin/bash
set -e

# Default port to 10000 if not set (Render Web Service standard)
PORT=${PORT:-10000}
echo "Configuring Nginx to listen on port ${PORT}..."

# Substitute ${PORT} in nginx config template
envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/sites-available/default
ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

# Storage link
php artisan storage:link || true

# Auto migrate if AUTO_MIGRATE is set to true
if [ "$AUTO_MIGRATE" = "true" ]; then
    echo "Running database migrations..."
    php artisan migrate --force || true
fi

# Optimization caching if in production and APP_KEY is provided
if [ -n "$APP_KEY" ]; then
    echo "Caching Laravel configuration and routes..."
    php artisan config:cache || true
    php artisan route:cache || true
fi

echo "Starting Nginx and PHP-FPM..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

