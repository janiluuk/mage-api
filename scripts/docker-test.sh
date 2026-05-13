#!/usr/bin/env bash
set -euo pipefail

# Script to bring up Docker cluster and run tests
# Usage: ./scripts/docker-test.sh [test-filter]
# Example: ./scripts/docker-test.sh VideojobModelTest

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if docker compose is available (try both formats)
if command -v docker &> /dev/null; then
    if docker compose version &> /dev/null 2>&1; then
        DOCKER_COMPOSE="docker compose"
    elif command -v docker-compose &> /dev/null; then
        DOCKER_COMPOSE="docker-compose"
    else
        print_error "docker compose or docker-compose not found"
        exit 1
    fi
else
    print_error "docker not found"
    exit 1
fi

print_info "Using: $DOCKER_COMPOSE"

# Check if .env file exists, create from .env.example if not
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        print_warn ".env file not found, copying from .env.example"
        cp .env.example .env
    else
        print_warn ".env file not found, tests may fail if database configuration is needed"
    fi
fi

# Build the Docker image if needed
print_info "Building/updating Docker image..."
$DOCKER_COMPOSE build app

# Stop and remove existing containers if they exist (to avoid conflicts)
print_info "Cleaning up existing containers..."
$DOCKER_COMPOSE down 2>/dev/null || true
# Also try to remove containers by name in case docker-compose down didn't work
docker rm -f mage-api mage-api-db mage-api-redis 2>/dev/null || true

# Start required services (app and db for tests that might need it)
print_info "Starting Docker containers..."
$DOCKER_COMPOSE up -d app db redis

# Wait for containers to be ready
print_info "Waiting for containers to be ready..."
sleep 5

# Get the container name
CONTAINER_NAME="mage-api"

# Check if container is running
if ! docker ps --format "{{.Names}}" | grep -q "^${CONTAINER_NAME}$"; then
    print_error "Container $CONTAINER_NAME is not running"
    print_info "Attempting to start container..."
    docker start "$CONTAINER_NAME" 2>/dev/null || {
        print_error "Failed to start container. Please check Docker logs."
        exit 1
    }
    sleep 3
fi

# Check if SQLite extension is installed
print_info "Checking for SQLite PDO extension..."
if docker exec "$CONTAINER_NAME" php -m 2>/dev/null | grep -qi "pdo_sqlite"; then
    print_info "SQLite extension is available."
else
    print_warn "SQLite extension not found, installing..."
    docker exec -u root "$CONTAINER_NAME" sh -c "\
        apt-get update -qq && \
        apt-get install -y -qq libsqlite3-dev && \
        docker-php-ext-install -j\$(nproc) pdo_sqlite && \
        docker-php-ext-enable pdo_sqlite
    " || {
        print_error "Failed to install SQLite extension"
        exit 1
    }
    print_info "SQLite extension installed successfully."
fi

# Install composer dependencies if vendor directory doesn't exist
print_info "Checking composer dependencies..."
if ! docker exec "$CONTAINER_NAME" test -d vendor; then
    print_info "Installing composer dependencies..."
    docker exec "$CONTAINER_NAME" composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Clear application caches
print_info "Clearing application caches..."
docker exec "$CONTAINER_NAME" php artisan config:clear || true
docker exec "$CONTAINER_NAME" php artisan cache:clear || true

# Run the tests
print_info "Running tests..."
echo ""

# Set test environment variables
TEST_ENV="-e APP_ENV=testing -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e CACHE_DRIVER=array -e QUEUE_CONNECTION=sync"

if [ $# -eq 0 ]; then
    # Run all tests
    print_info "Running all tests..."
    if docker exec $TEST_ENV "$CONTAINER_NAME" php vendor/bin/phpunit; then
        echo ""
        print_info "All tests passed!"
        exit 0
    else
        echo ""
        print_error "Some tests failed!"
        exit 1
    fi
else
    # Run filtered tests
    FILTER="$1"
    print_info "Running tests with filter: $FILTER"
    if docker exec $TEST_ENV "$CONTAINER_NAME" php vendor/bin/phpunit --filter "$FILTER"; then
        echo ""
        print_info "Filtered tests passed!"
        exit 0
    else
        echo ""
        print_error "Some filtered tests failed!"
        exit 1
    fi
fi

