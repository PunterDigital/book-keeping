#!/bin/sh

echo "Starting Laravel Bookkeeping Development Environment..."

# Start all services in background
echo "Starting services..."
docker-compose up -d mysql redis mailhog app webserver

# Wait for app container to be ready
echo "Waiting for app container to be ready..."
until docker exec bookkeeping-app php artisan --version > /dev/null 2>&1; do
  echo "App container is not ready yet - sleeping"
  sleep 2
done

echo "App container is ready!"

# Run initial setup
echo "Running initial setup..."
docker exec bookkeeping-app php artisan migrate --force
docker exec bookkeeping-app php artisan wayfinder:generate --with-form

echo "Starting Node development server..."
docker-compose up node

echo "Development environment ready!"
echo "- Application: http://localhost:8000"
echo "- Vite Dev Server: http://localhost:5173"
echo "- MailHog: http://localhost:8025"