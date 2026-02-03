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
- **Custom Jobs System**: Flexible job processing framework for specialized video/audio tasks
  - **Beat Match Music Video**: Synchronize video cuts to bass beats in audio
    - Adjustable cut intensity (1-3)
    - Direction control (forward, backward, random)
    - Speed factor adjustment (0.1-2.0)
    - Time range selection (start/end time)
  - **Audio Track Split**: Separate audio into multiple stems using UVR5
    - Multiple model support (MDX-Net, VR-Arch, Demucs)
    - Output format options (WAV, MP3, AAC, M4A, FLAC)
    - Vocal split mode (separate lead/background vocals)
    - Input from file upload or existing job_id
    - Project-based file selection
  - Extensible validator system for custom input validation
  - Flexible file input handling (direct upload, project_id, job_id)

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
- Docker (optional, for UVR5 audio separation via container)
- Python 3.8+ (optional, for UVR5 if not using Docker)

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

### UVR5 Audio Separation configuration
For audio track splitting, configure UVR5 in `.env`:
```
UVR5_DOCKER_IMAGE=upseem/uvr5-cli-no-ui:latest  # Docker image for UVR5
UVR5_USE_DOCKER=true                            # Use Docker (recommended) or local Python
UVR5_PYTHON_PATH=python3                        # Python path if not using Docker
```

The UVR5 Docker image will be pulled automatically on first use. Ensure Docker is running for audio track splitting jobs.

Queue names default to `high`, `medium`, and `low`. Override them with `HIGH_PRIORITY_QUEUE`, `MEDIUM_PRIORITY_QUEUE`, and
`LOW_PRIORITY_QUEUE` in your environment as needed.

## API Reference

### Authentication Endpoints

#### V1 API
- `POST /api/auth/register` — Register a new user account
- `POST /api/auth/login` — Authenticate and receive JWT token
- `POST /api/auth/logout` — Invalidate current session (requires auth)
- `POST /api/auth/forgot-password` — Request password reset email
- `POST /api/auth/reset-password` — Reset password with token
- `POST /api/auth/verify-email` — Verify user email with token
- `GET /api/auth/me` — Get current user profile (requires auth)
  - **Returns**: User information including `userRole` (0=REGISTERED, 1=ADMINISTRATOR, 2=SUPER_ADMINISTRATOR) and `isAdmin` (boolean) for admin menu visibility

#### V2 API
API v2 mirrors these endpoints at `/api/v2/*`:
- `POST /api/v2/login`
- `POST /api/v2/logout`
- `POST /api/v2/register`
- `POST /api/v2/password-forgot`
- `POST /api/v2/password-reset`
- `GET /api/v2/me` — Get current user profile
- `PATCH /api/v2/me` — Update current user profile

### Custom Jobs API

The Custom Jobs API provides a flexible framework for specialized video and audio processing tasks.

#### Process Custom Job
- `POST /api/v1/custom-jobs/process` — Process a custom job (requires auth)
  - **Parameters**:
    - `job_type` (required): Job type (`beat-match`, `audio-track-split`)
    - `input_type` (required): `files` (direct upload) or `project` (files from project)
    - `options` (required, object): Job-specific options (see below)
    - For `input_type=files`: `audio_file` (required file), `video_files` (array, required for beat-match)
    - For `input_type=project`: `project_id` (required integer)
  - **Beat Match Options**:
    - `cut_intensity` (optional, 1-3): Intensity of cuts
    - `direction` (optional): `forward`, `backward`, `random`
    - `speed_factor` (optional, 0.1-2.0): Video speed multiplier
    - `start_time` (optional, numeric): Start time in seconds
    - `end_time` (optional, numeric): End time in seconds (must be > start_time)
  - **Audio Track Split Options**:
    - `model` (optional, string): UVR5 model name (e.g., `MDX-Net-InstVoc_HQ_3`)
    - `output_format` (optional): `wav`, `mp3`, `aac`, `m4a`, `flac` (default: `wav`)
    - `vocal_split_mode` (optional, boolean): Enable vocal splitting
    - `job_id` (optional, integer): Use audio from existing job output
  - **Returns**: Job ID, status, and queue information

#### Get Job Status
- `GET /api/v1/custom-jobs/{id}/status` — Get custom job status (requires auth)
  - **Returns**: Status, progress, estimated time, URL, error information

### Video Job Endpoints

#### Upload & Generate
- `POST /api/upload` — Upload source media
  - **Parameters**: 
    - `attachment` (required): Video/image file (webm, mp4, mov, ogg, qt, gif, jpg, jpeg, png, webp, max 200MB)
    - `soundtrack` (optional): Audio file (mp3, aac, wav, max 50MB)
    - `type` (required): `vid2vid` or `deforum`
  - **Returns**: Job ID, status, and media URL

- `POST /api/generate` — Start AI generation for uploaded job
  - **Common parameters**: `videoId`, `type`, `modelId`, `prompt`, `seed` (optional)
  - **Vid2Vid specific**: `cfgScale` (2-10), `denoising` (0.1-1.0), `frameCount`, `negative_prompt`, `controlnet`
  - **Deforum specific**: `preset`, `length` (1-20), `frameCount`, `extendFromJobId` (to continue from previous job)
  - **Returns**: Job details with progress tracking info

- `POST /api/finalize` — Approve and enqueue job for final processing
  - **Parameters**: `videoId`, optional generation parameters to override
  - **Returns**: Updated job status and queue position

#### Job Management
- `POST /api/cancelJob/{videoId}` — Cancel pending or processing job
- `GET /api/queue` — List all jobs for authenticated user (requires auth)
- `GET /api/video-jobs/{videoId}/status` — Get detailed job status (requires auth) including:
  - Status, progress, estimated time remaining
  - Queue position and wait time
  - Full generation parameters
  - Model, prompt, and technical settings

#### Video Job Operations (V1 API)
- `POST /api/v1/video-jobs/add-soundtrack` — Add audio track to completed video job (requires auth)
  - **Parameters**: `video_job_id`, `soundtrack` (file), optional `start_seconds`, `end_seconds`, `output_name`
  - Adds audio overlay to finished video with optional time windowing
  
- `POST /api/v1/video-jobs/extend` — Create continuation of Deforum video job (requires auth)
  - **Parameters**: `video_job_id`, optional `length`, `prompt`, `negative_prompt`, `seed`, `denoising`
  - Creates new job that extends from completed Deforum animation
  
- `POST /api/v1/video-jobs/trim` — Trim/clip completed video job (requires auth)
  - **Parameters**: `video_job_id`, `start_seconds`, `end_seconds`, optional `output_name`
  - Creates new trimmed video job from specified time range

#### Processing Status
- `GET /api/video-jobs/processing/status` — Live overview of jobs (requires auth)
  - Processing jobs with real-time progress
  - Queued jobs with position
  - Global system statistics
  
- `GET /api/video-jobs/processing/queue` — Normalized queue feed for current user (requires auth)
  - All user jobs in processing and approved states
  - Queue position and ETA for each job

### ComfyUI Workflow Processing

Process ComfyUI workflows with custom inputs. All endpoints require authentication.

#### Workflow Submission
- `POST /api/comfyui/workflow/process` — Submit and process a ComfyUI workflow (requires auth)
  - **Parameters**:
    - `workflow` (required): Workflow data as JSON object or
    - `workflow_file` (optional): JSON file containing workflow
    - `inputs` (optional): Object with workflow parameters to override
      - `prompt` (optional): Text prompt (max 2000 chars)
      - `negative_prompt` (optional): Negative prompt (max 2000 chars)
      - `image` (optional): Input image file (max 20MB)
      - `seed` (optional): Random seed (-1 for random)
      - `steps` (optional): Number of steps (1-150)
      - `cfg` (optional): CFG scale (1-30)
      - `denoise` (optional): Denoise strength (0-1)
      - `width` (optional): Output width (64-2048)
      - `height` (optional): Output height (64-2048)
    - `wait_for_completion` (optional): Wait for workflow to complete (boolean)
    - `max_wait_seconds` (optional): Max wait time in seconds (10-600, default 300)
  - **Returns**: Prompt ID and status (202 for queued, 200 for completed if waiting)

#### Workflow Status & Results
- `GET /api/comfyui/workflow/status/{promptId}` — Check workflow processing status (requires auth)
  - **Returns**: Status, outputs (images/videos), and history data

- `POST /api/comfyui/workflow/cancel/{promptId}` — Cancel a processing workflow (requires auth)
  - **Returns**: Cancellation confirmation

- `GET /api/comfyui/image` — Download output image from ComfyUI (requires auth)
  - **Parameters**: `filename` (required), `subfolder` (optional), `type` (optional: output|input|temp)
  - **Returns**: Binary image data

### Batch Processing API

Manage and process multiple video jobs as a batch. All endpoints require authentication.

- `GET /api/v1/batches` — List all batches for current user
  - Query params: `status` (optional), `per_page` (optional)
  
- `POST /api/v1/batches` — Create new batch
  - **Parameters**: `name` (required), `description` (optional), `settings` (optional)
  
- `GET /api/v1/batches/{id}` — Get batch details with associated jobs
- `PUT /api/v1/batches/{id}` — Update batch (cannot update while processing)
- `DELETE /api/v1/batches/{id}` — Delete batch (cannot delete while processing)

- `POST /api/v1/batches/{id}/jobs` — Add video jobs to batch
  - **Parameters**: `video_job_ids` (required array of integers)
  
- `DELETE /api/v1/batches/{id}/jobs` — Remove video jobs from batch
  - **Parameters**: `video_job_ids` (required array of integers)
  
- `POST /api/v1/batches/{id}/process` — Start processing all jobs in batch
- `GET /api/v1/batches/{id}/status` — Get real-time batch status and progress

### Preset Management API

Save and manage generation presets for quick reuse. All endpoints require authentication.

- `GET /api/v1/presets` — List accessible presets (own + public)
  - Query params: `category`, `type`, `favorites_only`, `own_only`, `per_page`
  
- `POST /api/v1/presets` — Create new preset
  - **Parameters**: `name` (required), `type` (required: video/image/animation), `settings` (required), `description`, `category`, `is_public`, `is_favorite`
  
- `GET /api/v1/presets/{id}` — Get preset details
- `PUT /api/v1/presets/{id}` — Update preset (own presets only)
- `DELETE /api/v1/presets/{id}` — Delete preset (own presets only)

- `POST /api/v1/presets/{id}/use` — Mark preset as used (increments usage count)
- `POST /api/v1/presets/{id}/favorite` — Toggle favorite status
- `POST /api/v1/presets/{id}/duplicate` — Duplicate preset to own collection
  - **Parameters**: `name` (optional, defaults to "{original name} (Copy)")
  
- `GET /api/v1/presets/categories` — Get list of all preset categories

### GPU Credits & E-commerce

#### Categories & Products
- `GET /api/categories` — List all product categories
- `GET /api/categories/{id}` — Get specific category details
- `GET /api/categories/by-user-id/{userId?}` — Get categories with products for user (auth)
- `GET /api/products` — List products by category (query param: `categoryId`)
- `GET /api/products/{productId}` — Get product details
- `GET /api/products/get-products-for-user` — Get products for authenticated user (auth)
- `POST /api/products` — Create new product (auth)
- `PUT /api/products/{productId}` — Update product (auth)
- `PATCH /api/products/{productId}` — Toggle product active status (auth)
- `DELETE /api/products/{productId}` — Delete product (auth)

#### Orders
- `POST /api/orders` — Create new order (auth)
- `GET /api/orders/{orderId}` — Get order details (auth)
- `GET /api/orders/purchases` — Get user's purchases (auth)
- `GET /api/orders/sales` — Get user's sales (auth)
- `PATCH /api/orders/confirm-order` — Confirm order by ID (auth)

#### Payments
- `POST /api/payment/create-intent` — Create a Stripe payment intent (auth)
- `POST /api/webhooks/stripe` — Stripe webhook receiver

#### Wallets & Finance
- `GET /api/user-wallets` — Get wallets for current user (auth)
- `GET /api/user-wallets/by-wallet-type-id/{walletTypeId}` — Get wallets by type (auth)
- `POST /api/user-wallets` — Create wallet (auth)
- `PUT /api/user-wallets` — Update wallet (auth)
- `DELETE /api/user-wallets/{userWalletId}` — Delete wallet (auth)
- `GET /api/wallet-types` — List available wallet types
- `GET /api/finance-operations` — Get finance operations for current user (auth)
- `POST /api/finance-operations` — Create finance operation (auth)
- `GET /api/finance-operations/{financeOperationsId}` — Get operation details (auth)
- `PUT /api/finance-operations/{financeOperationsId}` — Cancel operation (auth)
- `PATCH /api/finance-operations/change-finance-operation-status` — Update operation status (auth)

### Communication

#### Chats & Messages
- `GET /api/chats/get-chats-by-current-user` — Get user's chats (auth)
- `GET /api/chats/{chatId}` — Get chat details (auth)
- `GET /api/chats/get-chat-by-user-id/{userId}` — Get chat with specific user (auth)
- `POST /api/chats` — Create new chat (auth)
- `GET /api/messages/get-messages-by-chat-id/{chatId}` — Get chat messages (auth)
- `POST /api/messages` — Send message (auth)

#### Support System
- `POST /api/support-request` — Submit support request
- `POST /api/support-requests` — Search support requests for user (auth)
- `GET /api/support-request/{id}` — Get support request details (auth)
- `GET /api/support-request-messages/{id}` — Get support request messages (auth)
- `POST /api/send-support-request-message` — Reply to support request (auth)
- `PATCH /api/support-request/status-update` — Update support request status (auth)

### Administration
Routes under `/api/administration/*` are protected by `AuthorizationChecker` and `IsAdministratorChecker`.

All administration endpoints require authentication and administrator role. Base path: `/api/administration`

#### User Management
- `GET /api/administration/users` — List all users
- `PATCH /api/administration/admin-reset-user-password` — Reset user password
- `PATCH /api/administration/change-user-data` — Update user account details
- `PATCH /api/administration/change-password` — Change user password
- `GET /api/administration/users/{userId}/data-stats` — User data statistics
- `DELETE /api/administration/users/purge-data` — Purge user data

#### Finance & Orders
- `GET /api/administration/finance-operations/get-all` — Get all finance operations
- `GET /api/administration/orders` — List all orders
- `PATCH /api/administration/orders/change-order-status` — Update order status

#### Support Administration
- `POST /api/administration/support-requests` — Search support requests with criteria

#### Generator Instance Management (Stable Diffusion Forge / ComfyUI)
- `GET /api/administration/generator-instances` — List instances
- `POST /api/administration/generator-instances` — Create instance
- `GET /api/administration/generator-instances/{id}` — Get instance details
- `PUT /api/administration/generator-instances/{id}` — Update instance
- `PATCH /api/administration/generator-instances/{id}` — Update instance
- `DELETE /api/administration/generator-instances/{id}` — Delete instance
- `PATCH /api/administration/generator-instances/{id}/toggle` — Toggle instance

Model files can be tagged with an `instanceType` (e.g., `stable_diffusion_forge`, `comfyui`) so jobs route to the right backend,
such as `dreamshaper` → Stable Diffusion Forge and `flux` → ComfyUI.

### Utility Endpoints

- `GET /api/status/{serviceName?}` — Check service status
- `GET /api/csrf-token` — Get CSRF token
- `GET /api/{providerName}/auth` — Initiate social auth
- `GET /api/{providerName}/callback` — Social auth callback

### Miscellaneous Endpoints

- `GET /api/questions` — List FAQ questions
- `GET /api/questions/{questionSlug}` — Get a question by slug
- `GET /api/properties` — Get properties by category (query param: `categoryId`)
- `GET /api/user-ratings/{userId?}` — Get user rating (auth)

## Rate Limiting & Security

All API endpoints are protected by rate limiting to prevent abuse:
- **Default rate limit**: 60 requests per minute per user (authenticated) or IP address (unauthenticated)
- **Custom rate limits**: Applied to specific endpoints as needed

Rate limit information is included in response headers:
- `X-RateLimit-Limit` - Maximum requests allowed
- `X-RateLimit-Remaining` - Requests remaining in current window

When the rate limit is exceeded, you'll receive a `429 Too Many Requests` response with a `retry_after` value in seconds.

### Security Features
- **JWT Authentication**: Required for most endpoints
- **CSRF Protection**: Built-in Laravel CSRF protection for web routes
- **Input Validation**: Comprehensive validation on all endpoints
- **Authorization Checks**: Ensures users can only access/modify their own resources
- **SQL Injection Prevention**: Parameterized queries via Eloquent ORM
- **XSS Protection**: Automatic output escaping

## Testing

### Running Tests

#### Unit and Feature Tests

Run all tests using Docker:
```bash
./docker-test
```

Run specific test class:
```bash
./docker-test InstanceStatusApiTest
```

Or using PHPUnit directly (requires local PHP setup):
```bash
./vendor/bin/phpunit
```

#### End-to-End Tests with Real Instances

For comprehensive E2E testing with real generator instances:

1. **Configure test instances**:
   ```bash
   cp .env.testing.example .env.testing
   # Edit .env.testing with your real instance URLs
   ```

2. **Set up test instances in database**:
   ```bash
   ./scripts/setup-test-instances.sh
   ```

3. **Run E2E tests**:
   ```bash
   ./scripts/run-e2e-tests.sh
   ```

For detailed E2E testing instructions, see [docs/E2E_TESTING_WITH_REAL_INSTANCES.md](docs/E2E_TESTING_WITH_REAL_INSTANCES.md)

### Test Structure

- **Unit Tests**: `tests/Unit/` - Test individual classes and methods
- **Feature Tests**: `tests/Feature/` - Test API endpoints and integrations
- **E2E Tests**: `tests/E2E/` - Test with real generator instances

All tests use SQLite in-memory database for fast execution.

## Common Tooling

- **Run tests**: `./docker-test` or `./vendor/bin/phpunit`
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
