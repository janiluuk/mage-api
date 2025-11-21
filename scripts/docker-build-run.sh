#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

if [ ! -f .env ]; then
  cp .env.example .env
fi

echo "Building images..."
docker-compose build

echo "Starting containers..."
docker-compose up -d

echo "Installing Composer dependencies..."
docker-compose exec app composer install --no-interaction

echo "Generating app keys..."
docker-compose exec app php artisan key:generate --force

echo "Generating JWT secret..."
docker-compose exec app php artisan jwt:secret --force

echo "Running database migrations and seeds..."
docker-compose exec app php artisan migrate --seed

echo "Docker environment is ready."
