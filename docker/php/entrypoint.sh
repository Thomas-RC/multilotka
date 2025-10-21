#!/usr/bin/env bash

set -euo pipefail

PROJECT_ROOT="/app"
APP_DIR="/app/app"

# Automatyczna instalacja zależności Composer (vendor w głównym katalogu)
if [ -f "$PROJECT_ROOT/composer.json" ]; then
    cd "$PROJECT_ROOT"
    echo "Checking Composer dependencies..."

    if [ ! -d "vendor" ]; then
        echo "Vendor directory not found. Running composer install..."
        composer install --no-interaction --prefer-dist
    else
        echo "Vendor directory exists. Verifying installation..."
        composer install --no-interaction --prefer-dist
    fi
fi

# Przygotowanie katalogów storage
if [ -d "$APP_DIR" ]; then
    STORAGE_DIR="$APP_DIR/storage"
    UPLOADS_DIR="$STORAGE_DIR/uploads"

    mkdir -p "$UPLOADS_DIR"
    chown -R www-data:www-data "$STORAGE_DIR"
    chmod -R 775 "$STORAGE_DIR"
fi

# Uruchomienie migracji (vendor jest w głównym katalogu)
if [ -f "$PROJECT_ROOT/vendor/autoload.php" ]; then
    cd "$APP_DIR"

    echo "Waiting for database connection..."
    until php -r '
        require __DIR__ . "/../vendor/autoload.php";
        Multilotka\Core\Environment::bootstrap();
        try {
            Multilotka\Core\DatabaseConnection::create()->connect();
            exit(0);
        } catch (Throwable) {
            exit(1);
        }
    '; do
        echo "Database unavailable - sleeping"
        sleep 2
    done

    echo "Running Doctrine migrations..."
    php ../vendor/bin/doctrine-migrations migrate --no-interaction || true
fi

exec "$@"
