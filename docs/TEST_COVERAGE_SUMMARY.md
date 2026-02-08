# Test Coverage Summary

This document summarizes all test coverage for the instance management and monitoring system.

## Test Coverage Overview

### App-Side Tests (mage-app)

**File**: `tests/unit/services/instanceAdminService.spec.js`

#### ✅ Completed Test Suites

1. **getStatus()** - 2 tests
   - ✓ Fetch comprehensive instance status
   - ✓ Handle errors when fetching status

2. **getMetricsHistory()** - 2 tests
   - ✓ Fetch historical metrics for an instance
   - ✓ Handle errors when fetching metrics history

3. **getJobHistory()** - 2 tests
   - ✓ Fetch job history for an instance
   - ✓ Handle errors when fetching job history

4. **getAdminApiRoot()** - 7 tests
   - ✓ Return API root from VITE_API_URL
   - ✓ Handle VITE_API_URL with trailing slash
   - ✓ Return API root from VITE_API_BASE_URL
   - ✓ Handle VITE_API_BASE_URL with trailing slash
   - ✓ Handle VITE_API_BASE_URL without /v1
   - ✓ Prefer VITE_API_URL over VITE_API_BASE_URL
   - ✓ Return empty string when no env vars are set

5. **listInstances()** - 3 tests
   - ✓ Fetch all generator instances
   - ✓ Handle errors when listing instances
   - ✓ Handle empty response

6. **createInstance()** - 2 tests
   - ✓ Create a new generator instance
   - ✓ Handle validation errors when creating instance

7. **updateInstance()** - 2 tests
   - ✓ Update an existing generator instance
   - ✓ Handle errors when updating instance

8. **toggleInstance()** - 2 tests
   - ✓ Toggle instance enabled status
   - ✓ Handle errors when toggling instance

9. **deleteInstance()** - 2 tests
   - ✓ Delete a generator instance
   - ✓ Handle errors when deleting instance

**Total**: 24 tests, all passing ✅

### API-Side Tests (mage-api)

**File**: `tests/Feature/InstanceStatusApiTest.php`

#### ✅ Completed Test Suites

1. **Status Endpoint** - 3 tests
   - ✓ Can get instance status without authentication (401)
   - ✓ Can get instance status as admin
   - ✓ Status includes ffmpeg information

2. **Metrics History Endpoint** - 4 tests
   - ✓ Can get metrics history without authentication (401)
   - ✓ Can get metrics history as admin
   - ✓ Metrics history returns empty array when no data
   - ✓ Metrics history returns 404 for nonexistent instance

3. **Job History Endpoint** - 6 tests
   - ✓ Can get job history without authentication (401)
   - ✓ Can get job history as admin
   - ✓ Job history returns empty array when no completed jobs
   - ✓ Job history returns 404 for nonexistent instance
   - ✓ Job history limits to 50 jobs
   - ✓ Job history orders by completed_at descending

**Total**: 13 tests, all passing ✅

### E2E Tests (mage-api)

**File**: `tests/E2E/InstanceHealthE2ETest.php`

#### ✅ Available E2E Test Suites

1. **Instance Health Collection** - 5 tests
   - ✓ Can collect metrics from real ComfyUI instance
   - ✓ Can collect metrics from real SD Forge instance
   - ✓ Instance status endpoint returns real data
   - ✓ Metrics history endpoint returns data after collection
   - ✓ Multiple instances can be monitored

**Note**: E2E tests require real instances to be configured. See [E2E_TESTING_WITH_REAL_INSTANCES.md](E2E_TESTING_WITH_REAL_INSTANCES.md)

## Running Tests

### App-Side Tests

```bash
cd mage-app
./docker-test tests/unit/services/instanceAdminService.spec.js
```

### API-Side Tests

```bash
cd mage-api
./docker-test InstanceStatusApiTest
```

### All Tests

```bash
# App tests
cd mage-app && ./docker-test

# API tests
cd mage-api && ./docker-test
```

## Test Statistics

- **App-Side Unit Tests**: 24 tests
- **API Feature Tests**: 13 tests
- **E2E Tests**: 5 tests (requires real instances)
- **Total**: 42 tests

## Coverage Areas

### ✅ Fully Covered

- Instance status retrieval
- Metrics history collection
- Job history tracking
- CRUD operations (Create, Read, Update, Delete)
- Instance toggling (enable/disable)
- API root URL construction
- Error handling
- Authentication/authorization
- Data filtering and pagination
- Sorting and ordering

### 🔄 Partially Covered (E2E Required)

- Real instance connectivity
- Actual metrics collection from live instances
- Load balancing with real instances
- Job processing with real instances

## Next Steps

1. **Add more E2E tests** for:
   - Load balancing scenarios
   - Job processing workflows
   - Instance failover
   - Performance testing

2. **Integration tests** for:
   - Complete job lifecycle
   - Multi-instance coordination
   - Error recovery

3. **Performance tests** for:
   - Metrics collection performance
   - Concurrent request handling
   - Database query optimization

## Test Maintenance

- Tests are automatically run in CI/CD
- All tests must pass before merging PRs
- E2E tests run on a schedule (not on every commit)
- Test data is cleaned up automatically using RefreshDatabase trait


