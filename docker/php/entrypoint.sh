#!/usr/bin/env bash

set -euo pipefail

APP_DIR="/app/app"

if [ -d "$APP_DIR" ]; then
    cd "$APP_DIR"
fi

if [ -f "$APP_DIR/vendor/autoload.php" ]; then
    echo "Waiting for database connection..."
    until php -r '
        require __DIR__ . "/vendor/autoload.php";
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
    php vendor/bin/doctrine-migrations migrate --no-interaction || true
fi

exec "$@"
