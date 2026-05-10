#!/bin/sh
set -e

echo "Starting FleetOps Backend..."

# Create .env file from environment variables if it doesn't exist
if [ ! -f .env ]; then
    echo "Creating .env file from environment variables..."
    cat > .env << EOF
APP_NAME=FleetOps
APP_ENV=${APP_ENV:-production}
APP_KEY=
APP_DEBUG=${APP_DEBUG:-false}
APP_URL=${APP_URL:-http://localhost}

LOG_CHANNEL=stack
LOG_LEVEL=info

DB_CONNECTION=${DB_CONNECTION:-sqlsrv}
DB_HOST=${DB_HOST:-sqlserver}
DB_PORT=${DB_PORT:-1433}
DB_DATABASE=${DB_DATABASE:-fleetops}
DB_USERNAME=${DB_USERNAME:-sa}
DB_PASSWORD=${DB_PASSWORD}
DB_ENCRYPT=${DB_ENCRYPT:-optional}
DB_TRUST_SERVER_CERTIFICATE=${DB_TRUST_SERVER_CERTIFICATE:-true}

SESSION_DRIVER=database
SESSION_LIFETIME=120

CACHE_STORE=database
QUEUE_CONNECTION=database

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local

MAIL_MAILER=log
MAIL_FROM_ADDRESS="noreply@fleetops.com"
MAIL_FROM_NAME="FleetOps"
EOF
fi

# Generate application key if not set
if ! grep -q "APP_KEY=base64:" .env; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

# Wait for SQL Server to be ready
echo "Waiting for SQL Server connection..."
MAX_RETRIES=30
RETRY_COUNT=0
# We connect to master (default) to check if the server is up
until /opt/mssql-tools18/bin/sqlcmd -S "$DB_HOST" -U "$DB_USERNAME" -P "$DB_PASSWORD" -C -Q "SELECT 1" >/dev/null 2>&1; do
    RETRY_COUNT=$((RETRY_COUNT + 1))
    if [ $RETRY_COUNT -ge $MAX_RETRIES ]; then
        echo "SQL Server connection failed after $MAX_RETRIES attempts"
        exit 1
    fi
    echo "SQL Server not ready, waiting... (attempt $RETRY_COUNT/$MAX_RETRIES)"
    sleep 2
done
echo "SQL Server connection established!"

# Create database if it doesn't exist
echo "Ensuring database '$DB_DATABASE' exists..."
/opt/mssql-tools18/bin/sqlcmd -S "$DB_HOST" -U "$DB_USERNAME" -P "$DB_PASSWORD" -C -Q "IF NOT EXISTS (SELECT name FROM sys.databases WHERE name = '$DB_DATABASE') CREATE DATABASE [$DB_DATABASE]"
echo "Database '$DB_DATABASE' is ready!"

# Run migrations
echo "Running migrations..."
php artisan migrate --force

# Clear all caches
echo "Clearing application caches..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Cache configuration for better performance
echo "Optimizing application..."
php artisan config:cache
php artisan route:cache

echo "FleetOps Backend is ready!"

# Start PHP-FPM
exec php-fpm