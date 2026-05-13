#!/usr/bin/env bash
set -euo pipefail

# Script to run PHPUnit tests in Docker
# Usage: ./scripts/run-tests.sh [filter]

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

# Check if docker compose is available (try both formats)
if command -v docker &> /dev/null; then
    if docker compose version &> /dev/null; then
        DOCKER_COMPOSE="docker compose"
    elif command -v docker-compose &> /dev/null; then
        DOCKER_COMPOSE="docker-compose"
    else
        echo "Error: docker compose or docker-compose not found"
        exit 1
    fi
else
    echo "Error: docker not found"
    exit 1
fi

# Check if containers are running, if not start them
CONTAINER_STATUS=$($DOCKER_COMPOSE ps app 2>/dev/null | grep -i "mage-api" || echo "")
if [ -z "$CONTAINER_STATUS" ] || ! echo "$CONTAINER_STATUS" | grep -q "Up"; then
    echo "Starting Docker containers..."
    $DOCKER_COMPOSE up -d app 2>&1 | grep -v "already in use" || true
    echo "Waiting for containers to be ready..."
    sleep 3
fi

# Get the container name (use docker ps to find the actual running container)
CONTAINER_NAME="mage-api"
if ! docker ps --format "{{.Names}}" | grep -q "^${CONTAINER_NAME}$"; then
    # Try to start it if it exists but is stopped
    docker start "$CONTAINER_NAME" 2>/dev/null || true
    sleep 2
fi

# Check if SQLite extension is installed, if not install it
echo "Checking for SQLite PDO extension..."
if docker exec "$CONTAINER_NAME" php -m 2>/dev/null | grep -qi "pdo_sqlite\|sqlite"; then
    echo "SQLite extension is available."
elif docker exec "$CONTAINER_NAME" php -r "extension_loaded('pdo_sqlite') || exit(1);" 2>/dev/null; then
    echo "SQLite extension is available."
else
    echo "Installing SQLite extension..."
    # Install SQLite dependencies and extension
    docker exec -u root "$CONTAINER_NAME" sh -c "\
        apt-get update -qq && \
        apt-get install -y -qq libsqlite3-dev && \
        docker-php-ext-install -j\$(nproc) pdo_sqlite 2>&1 || \
        (apt-get install -y -qq php-sqlite3 php-pdo && php -m | grep -i sqlite || true) \
    "
fi

# Ensure migrations are run (tests use RefreshDatabase, but we need to ensure they exist)
# Note: Tests use RefreshDatabase trait which will handle migrations automatically
echo "Test database will be set up automatically by RefreshDatabase trait..."

# Run the tests using docker exec directly (more reliable than docker-compose exec)
# Set test environment variables to ensure SQLite in-memory database is used
echo "Running tests..."
if [ $# -eq 0 ]; then
    # Run all tests
    docker exec -e DB_DATABASE=:memory: -e DB_CONNECTION=sqlite "$CONTAINER_NAME" php artisan test
else
    # Run filtered tests
    docker exec -e DB_DATABASE=:memory: -e DB_CONNECTION=sqlite "$CONTAINER_NAME" php artisan test --filter "$1"
fi

