#!/bin/sh
set -e

# Wait for database availability
echo "Waiting for database..."
until nc -z db 3306 2>/dev/null; do
  sleep 1
done
echo "Database is available."

# Install Composer dependencies (if missing)
if [ ! -d "vendor" ]; then
  echo "Installing Composer dependencies..."
  composer install --no-interaction
fi

# Migration (if bin/migrate is available)
if [ -f "bin/migrate" ]; then
  echo "Running migrations..."
  php bin/migrate || true
fi

exec "$@"
