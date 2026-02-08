#!/usr/bin/env bash
# Script to run E2E tests with real generator instances
# Usage: ./scripts/run-e2e-tests.sh [test-filter]

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

print_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if .env.testing exists
if [ ! -f .env.testing ]; then
    print_error ".env.testing file not found!"
    print_info "Please run ./scripts/setup-test-instances.sh first"
    exit 1
fi

# Source the test environment
source .env.testing

# Check if test container is running
CONTAINER_NAME="laravel-api-test"
if ! docker ps --format "{{.Names}}" | grep -q "^${CONTAINER_NAME}$"; then
    print_info "Starting test container..."
    docker compose --profile test up -d test
    sleep 3
fi

# Verify SQLite is available
print_info "Verifying SQLite extension..."
if docker exec "$CONTAINER_NAME" php -r "extension_loaded('pdo_sqlite') || exit(1);" 2>/dev/null; then
    print_info "✓ SQLite extension is available"
else
    print_error "✗ SQLite extension not found!"
    exit 1
fi

# Setup test instances if needed
print_info "Ensuring test instances are set up..."
./scripts/setup-test-instances.sh

# Run E2E tests
print_info "Running E2E tests..."
echo ""

TEST_ENV="-e APP_ENV=testing -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e CACHE_DRIVER=array -e QUEUE_CONNECTION=sync"

if [ $# -eq 0 ]; then
    # Run all E2E tests
    print_info "Running all E2E tests..."
    if docker exec $TEST_ENV "$CONTAINER_NAME" php artisan test --filter E2E; then
        echo ""
        print_info "All E2E tests passed!"
        exit 0
    else
        echo ""
        print_error "Some E2E tests failed!"
        exit 1
    fi
else
    # Run filtered tests
    FILTER="$1"
    print_info "Running E2E tests with filter: $FILTER"
    if docker exec $TEST_ENV "$CONTAINER_NAME" php artisan test --filter "$FILTER"; then
        echo ""
        print_info "Filtered E2E tests passed!"
        exit 0
    else
        echo ""
        print_error "Some E2E tests failed!"
        exit 1
    fi
fi


