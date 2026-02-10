#!/bin/sh
set -e

cd /var/www

# Install PHP dependencies if vendor is missing
if [ ! -f vendor/autoload.php ]; then
    echo "==> Installing PHP dependencies (this may take a minute)..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Ensure storage directories exist and are writable
mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Run database migrations (retry up to 5 times in case DB is still initializing)
echo "==> Running database migrations..."
for i in 1 2 3 4 5; do
    if php artisan migrate --force --no-interaction 2>&1; then
        echo "==> Migrations completed successfully."
        break
    fi
    echo "    Migration attempt $i failed, retrying in 5s..."
    sleep 5
done

# Clear and cache config for performance
php artisan config:clear 2>/dev/null || true

echo "==> Starting PHP-FPM..."
exec php-fpm
