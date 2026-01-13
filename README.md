# Mage AI Studio API Backend API

Laravel 10 API that powers video production, AI studio experiences, and GPU resource credit workflows for creative content generation.

## Features

### 🔐 Authentication & Authorization
- **JWT-based authentication** with token management
- **Social login integration** (Discord, and other OAuth providers via Laravel Socialite)
- **Password recovery** with email-based reset flows
- **Email verification** for new user accounts
- **Role-based access control** with administrator and user roles
- Secure middleware for route protection

### 🎥 Video Processing & AI Generation
- **Vid2Vid transformation**: Convert existing videos using AI models
  - Custom prompts and negative prompts
  - Adjustable CFG scale (2-10) and denoising strength (0.1-1.0)
  - ControlNet support for advanced control
  - Configurable frame counts and seed values
- **Deforum animation**: Create AI-generated video sequences
  - Preset-based generation with customizable parameters
  - Frame-by-frame animation control
  - Job extension capability (continue from existing animations)
  - Length and FPS configuration
- **Media upload system**: Support for multiple formats (WebM, MP4, MOV, GIF, images)
- **Soundtrack integration**: Attach audio tracks (MP3, AAC, WAV) to generated videos
- **Queue management**: Priority-based job queuing (high/medium/low)
- **Real-time progress tracking**: Monitor job status, progress, and estimated completion time
- **Job lifecycle control**: Upload → Generate → Finalize → Process flow
- **Advanced encoding system**:
  - File system watching for automatic output detection
  - Async processing with real-time progress updates
  - Configurable concurrent job processing
  - Encoding progress parser for multiple formats
  - Better error recovery and robustness
- **ComfyUI workflow processing**: Execute custom ComfyUI workflows
  - Upload workflow JSON files or send workflow data directly
  - Dynamic input injection (prompts, images, seeds, parameters)
  - Real-time status monitoring and result retrieval
  - Support for image and video outputs
  - Workflow validation and error handling

### 💰 GPU Credits & E-commerce
- **Product catalog**: Categories and GPU credit packages
- **Order management**: Create, track, and confirm purchases
- **User wallets**: Multi-wallet support with different wallet types
- **Finance operations**: Credit enrollment, write-offs, and transaction history
- **Order lifecycle**: Purchase creation, payment processing, and confirmation
- **Promo code system**: Support for discount codes

### 💬 Communication & Support
- **Direct messaging**: User-to-user chat functionality
- **Support ticket system**: Submit and track support requests
- **Support request messages**: Threaded conversation within tickets
- **Status tracking**: Monitor support request resolution progress

### 🛠️ Administration Panel
- **User management**: View all users, reset passwords, update account details
- **Finance oversight**: Review and modify finance operations, manage wallet types
- **Order administration**: View all orders, change order status
- **Support operations**: Search and update support requests
- **Content moderation**: Monitor and manage user-generated content

## Requirements
- PHP 8.2+, Composer 2
- Node.js 20.19+ or 22.12+ and npm
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

All administration endpoints require authentication and administrator role. Base path: `/api/administration`

#### User Management
- `GET /api/administration/users` — List all users
- `PATCH /api/administration/admin-reset-user-password` — Reset user password
- `PATCH /api/administration/change-user-data` — Update user account details
- `PATCH /api/administration/change-password` — Change user password

#### Finance & Orders
- `GET /api/administration/finance-operations/get-all` — Get all finance operations
- `GET /api/administration/orders` — List all orders
- `PATCH /api/administration/orders/change-order-status` — Update order status

#### Support Administration
- `POST /api/administration/support-requests` — Search support requests with criteria

### Utility Endpoints

- `GET /api/status/{serviceName?}` — Check service status
- `GET /api/csrf-token` — Get CSRF token
- `GET /api/{providerName}/auth` — Initiate social auth
- `GET /api/{providerName}/callback` — Social auth callback

## Common Tooling

- **Run tests**: `./vendor/bin/phpunit`
- **Generate API docs** (Scribe): `php artisan scribe:generate`
- **Clear logs**: `php artisan log:clear`
- **Code formatting** (Pint): `./vendor/bin/pint`
- **Watch video output**: `php artisan video:watch-output` — Monitor encoding output directories

### Video Processing Commands

- **Start file watcher daemon**: `php artisan video:watch-output --interval=5`
  - Monitors output directories for completed video encodings
  - Automatically updates job statuses
  - Run as a background service for production

Xdebug is available in the Docker environment; point your IDE to the running container and use `php artisan tinker` for quick
REPL-style checks.

## DeforumationQT Web Console

Visit `/deforumation-qt` to use the JavaScript port of DeforumationQT. Paste your JWT token, steer deforum payloads via `/api/generate`, and monitor processing/queue status in real time using the new endpoints above.

## Architecture Overview

This API follows a clean architecture pattern with:

- **Controllers**: Handle HTTP requests and responses
- **Actions**: Encapsulate business logic for single operations
- **Repositories**: Abstract database queries with criteria pattern
- **Services**: Complex business operations (video processing, etc.)
- **Jobs**: Background queue processing for long-running tasks
- **Middleware**: Request validation and authorization
- **Presenters**: Format data for API responses

## Contributing

When contributing to this repository:

1. Follow PSR-12 coding standards
2. Write tests for new features
3. Update API documentation
4. Ensure all tests pass before submitting PR
5. Use descriptive commit messages

## License

MIT License
