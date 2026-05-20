#!/bin/sh
set -e

echo "Running migrations..."
php bin/hyperf.php migrate --force

echo "Starting Hyperf server..."
exec php bin/hyperf.php start
