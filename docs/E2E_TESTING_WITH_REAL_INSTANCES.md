# End-to-End Testing with Real Generator Instances

This guide explains how to set up and run end-to-end tests using real generator instances (ComfyUI, Stable Diffusion Forge, etc.) to verify the complete instance management and monitoring functionality.

## Prerequisites

1. **Docker and Docker Compose** installed and running
2. **Real generator instances** accessible via network (local or remote)
3. **API access** to the instances (ComfyUI API, Stable Diffusion Forge API, etc.)
4. **Test IP addresses** or hostnames for your generator instances

## Setup

### 1. Configure Test Instances

Create a test configuration file for your real instances:

```bash
# Create test environment file
cp .env.testing.example .env.testing
```

Edit `.env.testing` with your real instance details:

```env
# Test Generator Instances
TEST_COMFYUI_INSTANCE_1_URL=http://192.168.1.100:8188
TEST_COMFYUI_INSTANCE_1_NAME=ComfyUI-Test-1
TEST_COMFYUI_INSTANCE_2_URL=http://192.168.1.101:8188
TEST_COMFYUI_INSTANCE_2_NAME=ComfyUI-Test-2

TEST_SD_FORGE_INSTANCE_1_URL=http://192.168.1.102:7860
TEST_SD_FORGE_INSTANCE_1_NAME=SD-Forge-Test-1

# Test API Configuration
TEST_API_URL=http://localhost:8000
TEST_ADMIN_EMAIL=admin@test.com
TEST_ADMIN_PASSWORD=test-password
```

### 2. Create Test Instance Setup Script

Create a script to set up test instances in the database:

```bash
# scripts/setup-test-instances.sh
#!/bin/bash
set -e

source .env.testing

docker exec mage-api-test php artisan tinker <<EOF
use App\Models\GeneratorInstance;
use App\Models\User;

// Create or get admin user
\$admin = User::firstOrCreate(
    ['email' => '${TEST_ADMIN_EMAIL}'],
    ['name' => 'Test Admin', 'password' => bcrypt('${TEST_ADMIN_PASSWORD}')]
);
\$admin->assignRole('administrator');

// Create ComfyUI test instances
GeneratorInstance::firstOrCreate(
    ['url' => '${TEST_COMFYUI_INSTANCE_1_URL}'],
    [
        'name' => '${TEST_COMFYUI_INSTANCE_1_NAME}',
        'type' => 'comfyui',
        'enabled' => true,
    ]
);

GeneratorInstance::firstOrCreate(
    ['url' => '${TEST_COMFYUI_INSTANCE_2_URL}'],
    [
        'name' => '${TEST_COMFYUI_INSTANCE_2_NAME}',
        'type' => 'comfyui',
        'enabled' => true,
    ]
);

// Create Stable Diffusion Forge test instance
GeneratorInstance::firstOrCreate(
    ['url' => '${TEST_SD_FORGE_INSTANCE_1_URL}'],
    [
        'name' => '${TEST_SD_FORGE_INSTANCE_1_NAME}',
        'type' => 'stable_diffusion_forge',
        'enabled' => true,
    ]
);

echo "Test instances created successfully!";
EOF
```

Make it executable:
```bash
chmod +x scripts/setup-test-instances.sh
```

## Running E2E Tests

### Option 1: Using Docker Test Container

```bash
# Start test container
docker compose --profile test up -d test

# Setup test instances
./scripts/setup-test-instances.sh

# Run e2e tests
docker exec mage-api-test php artisan test --filter InstanceE2ETest
```

### Option 2: Using Test Script

```bash
# Run the e2e test script
./scripts/run-e2e-tests.sh
```

## E2E Test Scenarios

### 1. Instance Health Check and Metrics Collection

Tests that verify:
- Instances are reachable
- Health status is correctly reported
- Metrics (GPU, CPU, memory) are collected
- Queue size and processing count are accurate

**Test File**: `tests/E2E/InstanceHealthE2ETest.php`

```php
public function test_can_collect_metrics_from_real_comfyui_instance(): void
{
    $instance = GeneratorInstance::where('type', 'comfyui')
        ->where('enabled', true)
        ->first();
    
    $this->assertNotNull($instance, 'No enabled ComfyUI instance found for testing');
    
    $service = app(InstanceMetricsService::class);
    $service->collectMetrics($instance);
    
    $instance->refresh();
    
    $this->assertNotNull($instance->last_health_check_at);
    $this->assertContains($instance->health_status, ['online', 'offline']);
}
```

### 2. Instance Management Operations

Tests that verify:
- Creating instances via API
- Updating instance configuration
- Toggling instance enabled/disabled status
- Deleting instances

**Test File**: `tests/E2E/InstanceManagementE2ETest.php`

### 3. Load Balancing

Tests that verify:
- Jobs are distributed across multiple instances
- Load balancing selects instances based on queue size
- Failed instances are skipped

**Test File**: `tests/E2E/InstanceLoadBalancingE2ETest.php`

### 4. Job Processing

Tests that verify:
- Jobs are assigned to available instances
- Job status is tracked correctly
- Job history is recorded

**Test File**: `tests/E2E/InstanceJobProcessingE2ETest.php`

## Test Instance Requirements

### ComfyUI Instances

- **API Endpoint**: Must respond to `/system_stats` endpoint
- **Queue Endpoint**: Must respond to `/queue` endpoint
- **Health Check**: Must respond to basic HTTP requests
- **Port**: Typically 8188 (default ComfyUI port)

### Stable Diffusion Forge Instances

- **API Endpoint**: Must respond to `/api/v1/status` or similar
- **Health Check**: Must respond to basic HTTP requests
- **Port**: Typically 7860 (default SD Forge port)

## Network Configuration

### Local Network Instances

If instances are on the same network:

```bash
# Ensure Docker can reach local network
# Add to docker-compose.yml test service:
network_mode: "host"  # Linux only
# OR
extra_hosts:
  - "host.docker.internal:host-gateway"  # Docker Desktop
```

### Remote Instances

For remote instances:

1. **Ensure firewall rules** allow connections from test container
2. **Use public IPs or hostnames** in test configuration
3. **Consider VPN** for secure connections

### Docker Network Configuration

```yaml
# docker-compose.yml
services:
  test:
    # ... other config ...
    networks:
      - default
      - host_network  # For accessing host network
    extra_hosts:
      - "instance1.local:192.168.1.100"
      - "instance2.local:192.168.1.101"
```

## Continuous Integration

### GitHub Actions Example

```yaml
# .github/workflows/e2e-tests.yml
name: E2E Tests with Real Instances

on:
  schedule:
    - cron: '0 2 * * *'  # Daily at 2 AM
  workflow_dispatch:

jobs:
  e2e-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Set up Docker
        uses: docker/setup-buildx-action@v2
      
      - name: Start test container
        run: docker compose --profile test up -d test
      
      - name: Setup test instances
        env:
          TEST_COMFYUI_INSTANCE_1_URL: ${{ secrets.TEST_COMFYUI_INSTANCE_1_URL }}
          TEST_COMFYUI_INSTANCE_2_URL: ${{ secrets.TEST_COMFYUI_INSTANCE_2_URL }}
        run: ./scripts/setup-test-instances.sh
      
      - name: Run E2E tests
        run: docker exec mage-api-test php artisan test --filter E2E
```

## Troubleshooting

### Instance Not Reachable

```bash
# Test connectivity from container
docker exec mage-api-test curl -v http://192.168.1.100:8188/system_stats

# Check network configuration
docker exec mage-api-test ping -c 3 192.168.1.100
```

### Permission Issues

```bash
# Ensure test container has proper permissions
docker exec -u root mage-api-test chown -R www-data:www-data /var/www
```

### Database Issues

```bash
# Reset test database
docker exec mage-api-test php artisan migrate:fresh --seed
```

## Best Practices

1. **Use Separate Test Instances**: Don't use production instances for testing
2. **Clean Up After Tests**: Remove test instances and data after test runs
3. **Monitor Resource Usage**: E2E tests can be resource-intensive
4. **Use Test Tags**: Tag e2e tests separately from unit/feature tests
5. **Schedule Regular Runs**: Run e2e tests on a schedule, not on every commit

## Test Data Management

### Creating Test Instances Programmatically

```php
// tests/E2E/TestCase.php
protected function setUp(): void
{
    parent::setUp();
    
    // Create test instances from environment
    $this->createTestInstances();
}

protected function createTestInstances(): void
{
    $instances = [
        [
            'name' => 'E2E-ComfyUI-1',
            'url' => env('TEST_COMFYUI_INSTANCE_1_URL'),
            'type' => 'comfyui',
            'enabled' => true,
        ],
        // ... more instances
    ];
    
    foreach ($instances as $instanceData) {
        if ($instanceData['url']) {
            GeneratorInstance::firstOrCreate(
                ['url' => $instanceData['url']],
                $instanceData
            );
        }
    }
}
```

## Security Considerations

1. **Never commit** real instance URLs or credentials
2. **Use environment variables** for all sensitive data
3. **Rotate test credentials** regularly
4. **Isolate test network** from production
5. **Monitor test instance usage** to prevent abuse

## Next Steps

1. Set up your test instances
2. Configure `.env.testing` with your instance URLs
3. Run `./scripts/setup-test-instances.sh`
4. Execute e2e tests: `./scripts/run-e2e-tests.sh`
5. Review test results and fix any issues

For questions or issues, see the main [README.md](../README.md) or open an issue.


