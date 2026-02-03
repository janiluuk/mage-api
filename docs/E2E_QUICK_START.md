# E2E Testing Quick Start Guide

Quick reference for setting up and running E2E tests with real generator instances.

## Prerequisites

- Docker and Docker Compose installed
- At least one real generator instance (ComfyUI or Stable Diffusion Forge) accessible via network
- Instance IP address or hostname

## Quick Setup (5 minutes)

### 1. Create Test Configuration

```bash
cd mage-api
cp .env.testing.example .env.testing
```

Edit `.env.testing` and add your instance URLs:

```env
TEST_COMFYUI_INSTANCE_1_URL=http://YOUR_INSTANCE_IP:8188
TEST_COMFYUI_INSTANCE_1_NAME=My-ComfyUI-Instance
```

### 2. Set Up Test Instances

```bash
./scripts/setup-test-instances.sh
```

This will:
- Start the test container
- Create test instances in the database
- Set up admin user for testing

### 3. Run E2E Tests

```bash
./scripts/run-e2e-tests.sh
```

Or run specific E2E test:

```bash
./scripts/run-e2e-tests.sh InstanceHealthE2ETest
```

## Common Commands

```bash
# Start test container
docker compose --profile test up -d test

# Run all tests
./docker-test

# Run specific test class
./docker-test InstanceStatusApiTest

# Run E2E tests
./scripts/run-e2e-tests.sh

# Check test container logs
docker logs laravel-api-test

# Access test container shell
docker exec -it laravel-api-test bash
```

## Troubleshooting

### Instance Not Reachable

```bash
# Test connectivity
docker exec laravel-api-test curl -v http://YOUR_INSTANCE_IP:8188/system_stats

# Check if instance is online
docker exec laravel-api-test ping -c 3 YOUR_INSTANCE_IP
```

### Container Issues

```bash
# Rebuild test container
docker compose --profile test build test
docker compose --profile test up -d test

# Check container status
docker ps | grep laravel-api-test
```

### Database Issues

```bash
# Reset test database
docker exec laravel-api-test php artisan migrate:fresh
```

## Test Instance Requirements

### ComfyUI
- **Port**: 8188 (default)
- **Health Check**: `GET /system_stats` should return JSON
- **Queue Endpoint**: `GET /queue` should return queue status

### Stable Diffusion Forge
- **Port**: 7860 (default)
- **Health Check**: API should respond to basic HTTP requests
- **Status Endpoint**: Should return instance status

## Example Test Instance URLs

```env
# Local network instance
TEST_COMFYUI_INSTANCE_1_URL=http://192.168.1.100:8188

# Remote instance (if accessible)
TEST_COMFYUI_INSTANCE_1_URL=http://instance.example.com:8188

# Docker network instance
TEST_COMFYUI_INSTANCE_1_URL=http://comfyui-container:8188
```

## Network Configuration

If your instances are on the same local network, you may need to configure Docker networking:

```yaml
# docker-compose.yml (test service)
services:
  test:
    # ... other config ...
    network_mode: "host"  # Linux only - allows access to host network
```

For Docker Desktop (Mac/Windows):
```yaml
extra_hosts:
  - "host.docker.internal:host-gateway"
```

## Next Steps

- See [E2E_TESTING_WITH_REAL_INSTANCES.md](E2E_TESTING_WITH_REAL_INSTANCES.md) for detailed documentation
- See [TEST_COVERAGE_SUMMARY.md](TEST_COVERAGE_SUMMARY.md) for test coverage details

## Need Help?

- Check the main [README.md](../README.md)
- Review [E2E_TESTING_WITH_REAL_INSTANCES.md](E2E_TESTING_WITH_REAL_INSTANCES.md) for detailed instructions
- Open an issue if you encounter problems

