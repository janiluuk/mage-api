# Mage API

Laravel 10 backend for Vimage/Mage that now ships a file management stack alongside the existing video generation, catalog, and messaging workflows.

## Highlights
- **Secure file vaults** for every authenticated user with a 1 GB configurable quota, upload validation, and per-project organization.
- **Archive handling**: upload, unzip, and import files between projects without leaving the platform.
- **Media tooling**: merge video segments, transcode audio/video into common delivery formats, and keep derived versions linked to the original upload.
- **Soundtrack support**: attach audio tracks to generated or existing videos with optional trim windows that match the video length.
- **Quota awareness everywhere**: APIs surface usage, the admin panel lists storage burn per user, and operations stop gracefully when space runs out.
- **Administration**: existing user/support/finance management plus new storage overviews for moderators.

## Requirements
- PHP 8.2+, Composer 2
- Node.js 18+ and npm
- MySQL/MariaDB and Redis
- Beanstalkd (queue)
- FFmpeg binaries on the host (for merging and transcoding)

## Setup
```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan jwt:secret
php artisan migrate --seed
npm run build
```

Docker users can start the stack with `docker-compose up -d` and run the same artisan commands inside the `app` container. Queue workers should target the configured names (`HIGH_PRIORITY_QUEUE`, `MEDIUM_PRIORITY_QUEUE`, `LOW_PRIORITY_QUEUE`).

### File service configuration
Add/adjust these keys in `.env` to tune storage behavior:
```
FILES_DISK=local           # Storage disk for user uploads
FILES_BASE_DIR=user-files  # Where user assets are nested inside the disk
FILES_QUOTA_BYTES=1073741824  # 1 GB quota per user by default
```

## API quick reference
### Auth
`POST /api/auth/register`, `POST /api/auth/login`, `POST /api/auth/logout`, `POST /api/auth/reset-password`, `GET /api/auth/me`. API v2 mirrors these at `/api/v2/*`.

### File management
All routes require JWT auth (`auth:api`).

- `GET /api/files` — Paginated list of the current user's uploads (`project_id` filter supported).
- `POST /api/files` — Upload a file with optional `project_id` and `meta` payload.
- `DELETE /api/files/{id}` — Remove a file and its stored artifact.
- `POST /api/files/{id}/unzip` — Expand a `.zip` archive into individual tracked files.
- `POST /api/files/merge` — Merge multiple video files (`file_ids[]`) into a single MP4.
- `POST /api/files/{id}/import` — Copy a file into another `project_id` container.
- `POST /api/files/{id}/transcode` — Transcode audio/video to `mp4`, `mov`, `webm`, `mp3`, or `aac` with optional `width`/`height`.
- `POST /api/files/{id}/attach-audio` — Mix an uploaded audio file into a video with optional `start_seconds` and `end_seconds`.
- `GET /api/files/quota` — Return `limit`, `used`, and `remaining` bytes for the user.

### Video jobs
`POST /api/upload`, `POST /api/generate`, `POST /api/finalize`, `POST /api/cancelJob/{videoId}`, `GET /api/queue`, `GET /api/video-jobs/processing/status`, `GET /api/video-jobs/processing/queue`. Upload accepts an optional `soundtrack` file plus `soundtrack_start_seconds`/`soundtrack_end_seconds` to trim the soundtrack to the rendered video length.

### Commerce, messaging, and support
Categories, products, orders, wallets, chats, messages, and support requests remain available under `/api/*` as in previous releases.

### Administration
Routes under `/api/administration/*` are protected by `AuthorizationChecker` and `IsAdministratorChecker`.

- Users: list, reset passwords, update account details.
- Finance: review/change finance operation status, review orders, manage wallet types.
- Support: search/update support requests and messages.
- **Storage**: `/api/administration/files/overview` paginates users with quota usage; `/api/administration/files/users/{userId}` returns per-user file lists and storage stats.

## Tooling
- Run tests: `./vendor/bin/phpunit`
- Generate API docs (Scribe): `php artisan scribe:generate`
- Clear logs: `php artisan log:clear`
- FFmpeg utilities are available through the file management endpoints and the existing video helpers.
