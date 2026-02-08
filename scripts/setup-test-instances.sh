#!/usr/bin/env bash
# Script to set up test generator instances for E2E testing
# Usage: ./scripts/setup-test-instances.sh

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
    print_warn ".env.testing file not found"
    print_info "Creating .env.testing from .env.testing.example..."
    if [ -f .env.testing.example ]; then
        cp .env.testing.example .env.testing
        print_info "Please edit .env.testing with your test instance URLs"
    else
        print_error ".env.testing.example not found. Please create .env.testing manually."
        exit 1
    fi
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

print_info "Setting up test instances in database..."

# Create test instances using tinker
docker exec "$CONTAINER_NAME" php artisan tinker <<'EOF'
use App\Models\GeneratorInstance;
use App\Models\User;

// Create or get admin user
$adminEmail = env('TEST_ADMIN_EMAIL', 'admin@test.com');
$adminPassword = env('TEST_ADMIN_PASSWORD', 'test-password');

$admin = User::firstOrCreate(
    ['email' => $adminEmail],
    [
        'name' => 'Test Admin',
        'password' => bcrypt($adminPassword)
    ]
);

if (!$admin->hasRole('administrator')) {
    $admin->assignRole('administrator');
    echo "Admin user created/updated: {$adminEmail}\n";
}

// Create ComfyUI test instances
$comfyInstances = [
    [
        'url' => env('TEST_COMFYUI_INSTANCE_1_URL'),
        'name' => env('TEST_COMFYUI_INSTANCE_1_NAME', 'ComfyUI-Test-1'),
    ],
    [
        'url' => env('TEST_COMFYUI_INSTANCE_2_URL'),
        'name' => env('TEST_COMFYUI_INSTANCE_2_NAME', 'ComfyUI-Test-2'),
    ],
];

foreach ($comfyInstances as $instanceData) {
    if (!empty($instanceData['url'])) {
        $instance = GeneratorInstance::firstOrCreate(
            ['url' => $instanceData['url']],
            [
                'name' => $instanceData['name'],
                'type' => 'comfyui',
                'enabled' => true,
            ]
        );
        echo "ComfyUI instance: {$instance->name} ({$instance->url})\n";
    }
}

// Create Stable Diffusion Forge test instances
$sdForgeInstances = [
    [
        'url' => env('TEST_SD_FORGE_INSTANCE_1_URL'),
        'name' => env('TEST_SD_FORGE_INSTANCE_1_NAME', 'SD-Forge-Test-1'),
    ],
];

foreach ($sdForgeInstances as $instanceData) {
    if (!empty($instanceData['url'])) {
        $instance = GeneratorInstance::firstOrCreate(
            ['url' => $instanceData['url']],
            [
                'name' => $instanceData['name'],
                'type' => 'stable_diffusion_forge',
                'enabled' => true,
            ]
        );
        echo "SD Forge instance: {$instance->name} ({$instance->url})\n";
    }
}

echo "\nTest instances setup complete!\n";
EOF

print_info "Test instances have been set up successfully!"
print_info "You can now run E2E tests with: ./scripts/run-e2e-tests.sh"


