#!/bin/sh

# Wait for the app container to be ready
echo "Waiting for app container to be ready..."

until nc -z app 9000; do
  echo "App container is not ready yet - sleeping"
  sleep 1
done

echo "App container is ready!"

# Pre-generate wayfinder routes using the app container
echo "Generating wayfinder routes..."
docker exec bookkeeping-app php artisan wayfinder:generate --with-form

echo "Setup complete!"