# Film Project API Namespace Documentation

## Overview

The Film Project API is properly namespaced under `/api/film-projects` with a dedicated controller and organized route structure.

## API Namespace

**Base Path:** `/api/film-projects`

**Controller:** `App\Http\Controllers\Api\FilmProjectController`

**Authentication:** All routes require JWT authentication via `auth:api` middleware

## Route Structure

### Route Naming Convention

All routes use named routes with the prefix `film-projects.` for easy reference:

```php
Route::prefix('film-projects')
    ->middleware('auth:api')
    ->name('film-projects.')
    ->group(function () {
        // Routes here
    });
```

### Available Routes

#### Project Management

| Method | Route | Name | Description |
|--------|-------|------|-------------|
| GET | `/api/film-projects` | `film-projects.index` | List all film projects |
| POST | `/api/film-projects` | `film-projects.store` | Create a new film project |
| GET | `/api/film-projects/{id}` | `film-projects.show` | Get a specific film project |
| PUT | `/api/film-projects/{id}` | `film-projects.update` | Update a film project |
| DELETE | `/api/film-projects/{id}` | `film-projects.destroy` | Delete a film project |

#### AI Features

| Method | Route | Name | Description |
|--------|-------|------|-------------|
| GET | `/api/film-projects/ai/models` | `film-projects.ai.models` | Get available AI models |
| POST | `/api/film-projects/{id}/generate/script` | `film-projects.generate.script` | Generate script for a project |

#### Sequence Management

| Method | Route | Name | Description |
|--------|-------|------|-------------|
| GET | `/api/film-projects/{projectId}/sequences` | `film-projects.sequences.index` | List sequences for a project |
| POST | `/api/film-projects/{projectId}/sequences` | `film-projects.sequences.store` | Create a new sequence |
| GET | `/api/film-projects/{projectId}/sequences/{sequenceId}` | `film-projects.sequences.show` | Get a specific sequence |
| PUT | `/api/film-projects/{projectId}/sequences/{sequenceId}` | `film-projects.sequences.update` | Update a sequence |
| DELETE | `/api/film-projects/{projectId}/sequences/{sequenceId}` | `film-projects.sequences.destroy` | Delete a sequence |

#### Shot Management

| Method | Route | Name | Description |
|--------|-------|------|-------------|
| GET | `/api/film-projects/{projectId}/sequences/{sequenceId}/shots` | `film-projects.sequences.shots.index` | List shots for a sequence |
| POST | `/api/film-projects/{projectId}/sequences/{sequenceId}/shots` | `film-projects.sequences.shots.store` | Create a new shot |
| GET | `/api/film-projects/{projectId}/sequences/{sequenceId}/shots/{shotId}` | `film-projects.sequences.shots.show` | Get a specific shot |
| PUT | `/api/film-projects/{projectId}/sequences/{sequenceId}/shots/{shotId}` | `film-projects.sequences.shots.update` | Update a shot |
| DELETE | `/api/film-projects/{projectId}/sequences/{sequenceId}/shots/{shotId}` | `film-projects.sequences.shots.destroy` | Delete a shot |
| POST | `/api/film-projects/{projectId}/sequences/{sequenceId}/shots/{shotId}/generate/scene` | `film-projects.sequences.shots.generate.scene` | Generate scene for a shot |

## Controller Structure

```php
namespace App\Http\Controllers\Api;

/**
 * Film Project API Controller
 * 
 * Handles all film project-related API endpoints.
 * Namespace: /api/film-projects
 * 
 * @namespace App\Http\Controllers\Api\FilmProject
 */
class FilmProjectController extends ApiController
{
    // Controller implementation
}
```

## Route Organization

Routes are organized hierarchically:

1. **Top-level routes** (projects, AI models)
2. **Nested routes** (sequences under projects)
3. **Deeply nested routes** (shots under sequences)

This structure provides:
- Clear API hierarchy
- Logical grouping
- Easy to understand relationships
- Consistent naming conventions

## Usage Examples

### Using Named Routes in Code

```php
// Generate URL
route('film-projects.show', ['id' => 1]);
// Returns: /api/film-projects/1

// Generate URL for nested route
route('film-projects.sequences.shots.index', [
    'projectId' => 1,
    'sequenceId' => 2
]);
// Returns: /api/film-projects/1/sequences/2/shots
```

### Frontend Service Integration

```javascript
// Base path constant
const BASE_PATH = '/film-projects';

// Example API calls
GET  /api/film-projects
POST /api/film-projects
GET  /api/film-projects/{id}
PUT  /api/film-projects/{id}
DELETE /api/film-projects/{id}
```

## Testing

All routes are covered by comprehensive tests in:
- `tests/Feature/FilmProjectApiTest.php`

Test groups:
- `@group film-projects`
- `@group api`

## Migration from Old Namespace

If migrating from the old `film-productions` namespace:

1. Update frontend service base path from `/film-productions` to `/film-projects`
2. Update all API calls to use the new namespace
3. Update route references in tests
4. Update documentation

## Best Practices

1. **Always use named routes** when generating URLs in code
2. **Follow the hierarchical structure** when adding new endpoints
3. **Maintain consistent naming** with the `film-projects.` prefix
4. **Document new routes** in this file
5. **Add tests** for all new endpoints

