<?php

use App\Http\Controllers\Api\V1\VideojobApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use LaravelJsonApi\Laravel\Routing\ResourceRegistrar;
use App\Http\Controllers\Api\V2\Auth\LoginController;
use App\Http\Controllers\VideojobController;
use App\Http\Controllers\AudioController;
use App\Http\Controllers\Api\V1\UploadController;
use App\Http\Controllers\Api\V1\GeneratorController;
use App\Http\Controllers\Api\V2\Auth\LogoutController;
use App\Http\Controllers\Api\V2\Auth\RegisterController;
use App\Http\Controllers\Api\V2\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V2\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V2\MeController;
use LaravelJsonApi\Laravel\Facades\JsonApiRoute;
use LaravelJsonApi\Laravel\Http\Controllers\JsonApiController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\SocialiteAuthController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\StatusController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\UserWalletController;
use App\Http\Controllers\Api\UserRatingController;
use App\Http\Controllers\Api\WalletTypeController;
use App\Http\Controllers\Api\SupportRequestController;
use App\Http\Controllers\Api\FinanceOperationsController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\Admin\FileAdminController;
use App\Http\Controllers\Api\GeneratorInstanceController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ComfyUIWorkflowController;
use App\Http\Controllers\Api\V1\VideoJobOperationsController;
use App\Http\Controllers\Api\V1\VideoJobAdvancedController;
use App\Http\Controllers\Api\V1\BatchController;
use App\Http\Controllers\Api\V1\PresetController;
use App\Http\Controllers\Api\V1\CustomJobController;
use App\Http\Controllers\Api\V1\StoryController;
use App\Http\Controllers\Api\V1\DeforumController;
use App\Http\Controllers\Api\V1\VideoEditorProjectController;
use App\Http\Controllers\Api\V1\VideoExportController;
use App\Http\Controllers\Api\FilmProductionController;
use LaravelJsonApi\Laravel\Routing\Relationships;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// ============================================================================
// JSON API Routes (v1 & v2)
// ============================================================================

JsonApiRoute::server('v1')
    ->prefix('v1')
    ->resources(function (ResourceRegistrar $server) {
        $server->resource('model-files', JsonApiController::class);
        $server->resource('generators', JsonApiController::class);
        $server->resource('video-jobs', VideojobApiController::class);
        
        $server->resource('categories', JsonApiController::class)->relationships(function ($relationships) {
            $relationships->hasMany('items');
        });
        
        $server->resource('users', JsonApiController::class)->relationships(function ($relationships) {
            $relationships->hasOne('role');
        });
        
        $server->resource('items', JsonApiController::class)->relationships(function ($relationships) {
            $relationships->hasMany('tags');
            $relationships->hasOne('category');
            $relationships->hasOne('user');
        });
        
        $server->resource('roles', JsonApiController::class)->relationships(function ($relationships) {
            $relationships->hasMany('permissions');
        });
        
        $server->resource('permissions', JsonApiController::class)->relationships(function ($relationships) { 
            $relationships->hasOne('role');
        })->only('index');

        // Note: Batches use REST API endpoints in v1 prefix group below, not JSON:API
        // $server->resource('batches', JsonApiController::class);
        
        $server->resource('tags', JsonApiController::class)->relationships(function ($relationships) {
            $relationships->hasMany('items');
        });
    });

Route::prefix('v2')->group(function () {
    Route::post('/login', LoginController::class)->name('login');
    Route::post('/logout', LogoutController::class)->middleware('auth:api');
    Route::post('/register', RegisterController::class);
    Route::post('/password-forgot', ForgotPasswordController::class);
    Route::post('/password-reset', ResetPasswordController::class)->name('password.reset');
});

// V1 auth routes (used by /api/auth/me tests)
Route::prefix('auth')->middleware('auth:api')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
});

JsonApiRoute::server('v2')->prefix('v2')->resources(function (ResourceRegistrar $server) {
    $server->resource('users', JsonApiController::class)->relationships(function ($relationships) {
        $relationships->hasOne('role');
    });

    Route::get('me', [MeController::class, 'readProfile']);
    Route::patch('me', [MeController::class, 'updateProfile']);
});

// ============================================================================
// Audio Generation Routes
// ============================================================================
Route::prefix('audio')->group(function () {
    Route::get('/stream', [AudioController::class, 'stream']);
    Route::get('/status', [AudioController::class, 'status']);
    Route::get('/queue', [AudioController::class, 'queue']);
    Route::get('/config', [AudioController::class, 'config']);
});

// Legacy audio routes (for backward compatibility)
Route::get('/stream', [AudioController::class, 'stream']);
Route::get('/status', [AudioController::class, 'status']);
Route::get('/audio-queue', [AudioController::class, 'queue']);
Route::get('/config', [AudioController::class, 'config']);

// ============================================================================
// V1 Upload Routes
// ============================================================================

Route::prefix('v1')->middleware('auth:api')->group(function () {
    Route::post('/uploads/{resource}/{id}/{field}', UploadController::class);
    
    // Video job operations
    Route::post('/video-jobs/add-soundtrack', [VideoJobOperationsController::class, 'addSoundtrack']);
    Route::post('/video-jobs/extend', [VideoJobOperationsController::class, 'extend']);
    Route::post('/video-jobs/trim', [VideoJobOperationsController::class, 'trim']);
    
    // Advanced video job operations (variants, post-processing)
    Route::post('/video-jobs/{id}/variants', [VideoJobAdvancedController::class, 'createVariants']);
    Route::get('/video-jobs/{id}/variants', [VideoJobAdvancedController::class, 'getVariantsStatus']);
    Route::post('/video-jobs/{id}/variants/process', [VideoJobAdvancedController::class, 'processVariants']);
    Route::post('/video-jobs/{id}/post-process', [VideoJobAdvancedController::class, 'postProcess']);
    Route::get('/video-jobs/post-process/effects', [VideoJobAdvancedController::class, 'getAvailableEffects']);
    Route::post('/video-jobs/{id}/extend-with-params', [VideoJobAdvancedController::class, 'extendWithParams']);
    
    // Custom job processing
    Route::post('/custom-jobs/process', [CustomJobController::class, 'process']);
    Route::get('/custom-jobs/{id}/status', [CustomJobController::class, 'status']);
    
    // Batch processing routes
    Route::get('/batches', [BatchController::class, 'index']);
    Route::post('/batches', [BatchController::class, 'store']);
    Route::get('/batches/{id}', [BatchController::class, 'show']);
    Route::put('/batches/{id}', [BatchController::class, 'update']);
    Route::delete('/batches/{id}', [BatchController::class, 'destroy']);
    Route::post('/batches/{id}/jobs', [BatchController::class, 'addJobs']);
    Route::delete('/batches/{id}/jobs', [BatchController::class, 'removeJobs']);
    Route::post('/batches/{id}/process', [BatchController::class, 'process']);
    Route::get('/batches/{id}/status', [BatchController::class, 'status']);
    
    // Preset management routes
    Route::get('/presets/categories', [PresetController::class, 'categories']);
    Route::get('/presets', [PresetController::class, 'index']);
    Route::post('/presets', [PresetController::class, 'store']);
    Route::get('/presets/{id}', [PresetController::class, 'show']);
    Route::put('/presets/{id}', [PresetController::class, 'update']);
    Route::delete('/presets/{id}', [PresetController::class, 'destroy']);
    Route::post('/presets/{id}/use', [PresetController::class, 'markAsUsed']);
    Route::post('/presets/{id}/favorite', [PresetController::class, 'toggleFavorite']);
    Route::post('/presets/{id}/duplicate', [PresetController::class, 'duplicate']);
    
    // Story management routes
    Route::get('/story', [StoryController::class, 'index']);
    Route::post('/story/generate', [StoryController::class, 'generate']);
    Route::get('/story/{id}', [StoryController::class, 'show']);
    Route::put('/story/{id}', [StoryController::class, 'update']);
    Route::put('/story/{id}/jobs/order', [StoryController::class, 'updateJobOrder']);
    Route::post('/story/{id}/jobs', [StoryController::class, 'assignJobs']);
    Route::delete('/story/{id}/jobs', [StoryController::class, 'removeJobs']);
    
    // Story batch operations (legacy endpoints, kept for compatibility)
    Route::get('/story/batch/{batchId}', [StoryController::class, 'getBatchStatus']);
    Route::post('/story/batch/{batchId}/pause', [StoryController::class, 'pauseBatch']);
    Route::post('/story/batch/{batchId}/resume', [StoryController::class, 'resumeBatch']);
    Route::delete('/story/batch/{batchId}', [StoryController::class, 'cancelBatch']);
    Route::post('/story/batch/{batchId}/frames', [StoryController::class, 'persistFrame']);
    Route::post('/story/share', [StoryController::class, 'createShareLink']);
    
    // Deforum Live Control routes
    Route::post('/deforum/live', [DeforumController::class, 'sendLiveUpdate']);
    Route::get('/deforum/live/status', [DeforumController::class, 'getLiveStatus']);
    
    // Video Editor Project routes
    Route::apiResource('video-editor-projects', VideoEditorProjectController::class);
    
    // Video Export routes
    Route::post('/video-export', [VideoExportController::class, 'store']);
    Route::get('/video-export/{id}', [VideoExportController::class, 'show']);
    Route::get('/video-export/{id}/stream', [VideoExportController::class, 'stream']); // SSE endpoint
    Route::delete('/video-export/{id}', [VideoExportController::class, 'destroy']);
});

Route::post('/upload', [VideojobController::class, 'upload'])->middleware('api');
Route::post('/generate', [VideojobController::class, 'generate'])->middleware('api');
Route::post('/finalize', [VideojobController::class, 'finalize'])->middleware('api');
Route::post('/cancelJob/{videoId}', [VideojobController::class, 'cancelJob'])->middleware('api');
Route::get('/queue', [VideojobController::class, 'getVideoJobs'])->middleware('auth:api');
Route::get('/status/{id}', [VideojobController::class, 'status'])->middleware('auth:api');
Route::middleware('auth:api')->prefix('video-jobs')->group(function () {
    Route::get('/processing/status', [VideojobController::class, 'processingStatus']);
    Route::get('/processing/queue', [VideojobController::class, 'processingQueue']);
    Route::get('/{videoId}/status', [VideojobController::class, 'status']);
    Route::patch('/{videoId}/audio', [VideojobController::class, 'attachAudio']);
});

Route::middleware('auth:api')->prefix('files')->group(function () {
    Route::get('', [FileController::class, 'index']);
    Route::post('', [FileController::class, 'store']);
    Route::delete('{id}', [FileController::class, 'destroy']);
    Route::post('{id}/unzip', [FileController::class, 'unzip']);
    Route::post('merge', [FileController::class, 'merge']);
    Route::post('{id}/import', [FileController::class, 'import']);
    Route::post('{id}/transcode', [FileController::class, 'transcode']);
    Route::post('{id}/attach-audio', [FileController::class, 'attachAudio']);
    Route::get('quota', [FileController::class, 'quota']);
    
    // Tag-related endpoints
    Route::get('by-tags', [FileController::class, 'byTags']);
    Route::get('by-tag/{tagId}', [FileController::class, 'byTag']);
    Route::post('{id}/tags', [FileController::class, 'attachTags']);
    Route::put('{id}/tags', [FileController::class, 'syncTags']);
    Route::delete('{id}/tags/{tagId}', [FileController::class, 'detachTag']);
});

// ============================================================================
// User-Facing Resource Routes
// ============================================================================

// Categories
Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'getCategories']);
    Route::get('/{id}', [CategoryController::class, 'getCategoryById']);
    
    Route::middleware('AuthorizationChecker')->group(function () {
        Route::get('/by-user-id/{userId?}', [CategoryController::class, 'getCategoriesWithProductsForUser']);
    });
});


Route::prefix('administration')->group(function () {
    Route::middleware(['AuthorizationChecker', 'IsAdministratorChecker'])->group(function () {
        Route::get('/users', [UserController::class, 'getAllUsers']);
        Route::post('/support-requests', [SupportRequestController::class, 'getSupportRequestsByCriteria']);
        Route::patch('/admin-reset-user-password', [UserController::class, 'adminResetUserPassword']);
        Route::patch('/change-user-data', [UserController::class, 'changeUserData']);
        Route::get('/finance-operations/get-all', [FinanceOperationsController::class, 'getAllFinanceOperations']);
        Route::get('/orders', [OrderController::class, 'getAllOrders']);
        Route::patch('/orders/change-order-status', [OrderController::class, 'changeOrderStatus']);
        Route::patch('/change-password', [UserController::class, 'changePassword']);
        Route::get('/users/{userId}/data-stats', [UserController::class, 'getUserDataStats']);
        Route::delete('/users/purge-data', [UserController::class, 'purgeUserData']);
        Route::get('/stats', [\App\Http\Controllers\Api\V1\StatsController::class, 'getStats']);
        
        // Generator instance management routes
        Route::get('/generator-instances', [GeneratorInstanceController::class, 'index']);
        Route::post('/generator-instances', [GeneratorInstanceController::class, 'store']);
        Route::get('/generator-instances/{id}', [GeneratorInstanceController::class, 'show']);
        Route::put('/generator-instances/{id}', [GeneratorInstanceController::class, 'update']);
        Route::patch('/generator-instances/{id}', [GeneratorInstanceController::class, 'update']);
        Route::delete('/generator-instances/{id}', [GeneratorInstanceController::class, 'destroy']);
        Route::patch('/generator-instances/{id}/toggle', [GeneratorInstanceController::class, 'toggle']);

        // Instance status and monitoring routes
        Route::get('/instances/status', [\App\Http\Controllers\Admin\InstanceStatusController::class, 'status']);
        Route::get('/instances/{id}/metrics-history', [\App\Http\Controllers\Admin\InstanceStatusController::class, 'metricsHistory']);
        Route::get('/instances/{id}/job-history', [\App\Http\Controllers\Admin\InstanceStatusController::class, 'jobHistory']);
    });
});

Route::prefix('administration/files')->group(function () {
    Route::middleware(['AuthorizationChecker', 'IsAdministratorChecker'])->group(function () {
        Route::get('/overview', [FileAdminController::class, 'index']);
        Route::get('/users/{userId}', [FileAdminController::class, 'filesForUser']);
        Route::put('/users/{userId}/quota', [FileAdminController::class, 'updateQuota']);
    });
});

// Wallet Types
Route::prefix('wallet-types')->group(function () {
    Route::get('/', [WalletTypeController::class, 'getWalletTypes']);
});

// User Wallets
Route::prefix('user-wallets')->middleware('AuthorizationChecker')->group(function () {
    Route::get('/', [UserWalletController::class, 'getUserWalletsForCurrentUser']);
    Route::get('/by-wallet-type-id/{walletTypeId}', [UserWalletController::class, 'getUserWalletsByWalletTypeId']);
    Route::post('/', [UserWalletController::class, 'save']);
    Route::put('/', [UserWalletController::class, 'update']);
    Route::delete('/{userWalletId}', [UserWalletController::class, 'delete']);
});

// Finance Operations
Route::prefix('finance-operations')->middleware('AuthorizationChecker')->group(function () {
    Route::get('/', [FinanceOperationsController::class, 'getFinanceOperationsForCurrentUser']);
    Route::post('/', [FinanceOperationsController::class, 'create']);
    Route::get('/{financeOperationsId}', [FinanceOperationsController::class, 'getFinanceOperationById']);
    Route::put('/{financeOperationsId}', [FinanceOperationsController::class, 'changeFinanceOperationStatusToCancel']);
    Route::patch('/change-finance-operation-status', [FinanceOperationsController::class, 'changeFinanceOperationStatus']);
});

// Orders
Route::prefix('orders')->middleware('AuthorizationChecker')->group(function () {
    Route::get('/purchases', [OrderController::class, 'getPurchasesForCurrentUser']);
    Route::get('/sales', [OrderController::class, 'getSalesForCurrentUser']);
    Route::post('/', [OrderController::class, 'create']);
    Route::get('/{orderId}', [OrderController::class, 'getOrderById']);
    Route::patch('/confirm-order', [OrderController::class, 'confirmOrderById']);
});

// User Ratings
Route::prefix('user-ratings')->middleware('AuthorizationChecker')->group(function () {
    Route::get('/{userId?}', [UserRatingController::class, 'getUserRatingByUserId']);
});

// ============================================================================
// Communication Routes (Chats & Messages)
// ============================================================================

// Messages
Route::prefix('messages')->middleware('AuthorizationChecker')->group(function () {
    Route::get('/get-messages-by-chat-id/{chatId}', [MessageController::class, 'getMessagesByChatId']);
    Route::post('/', [MessageController::class, 'addMessage']);
});

// Chats
Route::prefix('chats')->middleware('AuthorizationChecker')->group(function () {
    Route::get('/get-chats-by-current-user', [ChatController::class, 'getChatsByCurrentUser']);
    Route::get('/get-chat-by-user-id/{userId}', [ChatController::class, 'getChatByUserId']);
    Route::post('/', [ChatController::class, 'create']);
    Route::get('/{chatId}', [ChatController::class, 'getChatById']);
});

// ============================================================================
// Story Creator Routes
// ============================================================================
Route::prefix('story')->middleware('AuthorizationChecker')->group(function () {
    Route::post('/generate', [\App\Http\Controllers\Api\StoryGenerationController::class, 'start']);
    Route::post('/share', [\App\Http\Controllers\Api\StoryGenerationController::class, 'share']);
    Route::get('/batch/{batchId}', [\App\Http\Controllers\Api\StoryGenerationController::class, 'status']);
    Route::get('/batch/{batchId}/config', [\App\Http\Controllers\Api\StoryGenerationController::class, 'getConfig']);
    Route::patch('/batch/{batchId}', [\App\Http\Controllers\Api\StoryGenerationController::class, 'updateConfig']);
    Route::post('/batch/{batchId}/pause', [\App\Http\Controllers\Api\StoryGenerationController::class, 'pause']);
    Route::post('/batch/{batchId}/resume', [\App\Http\Controllers\Api\StoryGenerationController::class, 'resume']);
    Route::delete('/batch/{batchId}', [\App\Http\Controllers\Api\StoryGenerationController::class, 'cancel']);
    Route::post('/batch/{batchId}/frames', [\App\Http\Controllers\Api\StoryGenerationController::class, 'persistFrame']);
});

// ============================================================================
// Support Request Routes
// ============================================================================

Route::prefix('support-requests')->group(function () {
    // Public route
    Route::post('/', [SupportRequestController::class, 'sendSupportRequest']);
    
    // Authenticated routes
    Route::middleware('AuthorizationChecker')->group(function () {
        Route::post('/search', [SupportRequestController::class, 'getSupportRequestsByCriteriaForUser']);
        Route::get('/{id}', [SupportRequestController::class, 'getSupportRequest']);
        Route::get('/{id}/messages', [SupportRequestController::class, 'getAllSupportRequestMessages']);
        Route::post('/{id}/messages', [SupportRequestController::class, 'sendSupportRequestMessage']);
        Route::patch('/{id}/status', [SupportRequestController::class, 'updateSupportStatusRequest']);
    });
});

// Legacy support request routes (for backward compatibility)
Route::prefix('support-request')->group(function () {
    Route::post('/', [SupportRequestController::class, 'sendSupportRequest']);
});
Route::middleware('AuthorizationChecker')->group(function () {
    Route::post('/support-requests', [SupportRequestController::class, 'getSupportRequestsByCriteriaForUser']);
    Route::get('/support-request/{id}', [SupportRequestController::class, 'getSupportRequest']);
    Route::get('/support-request-messages/{id}', [SupportRequestController::class, 'getAllSupportRequestMessages']);
    Route::post('/send-support-request-message', [SupportRequestController::class, 'sendSupportRequestMessage']);
    Route::patch('/support-request/status-update', [SupportRequestController::class, 'updateSupportStatusRequest']);
});

// ============================================================================
// Payment Routes
// ============================================================================

Route::prefix('payment')->middleware('auth:api')->group(function () {
    Route::post('/create-intent', [PaymentController::class, 'createPaymentIntent']);
});

Route::post('/webhooks/stripe', [PaymentController::class, 'webhook'])
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

// ComfyUI Workflow routes
Route::prefix('/comfyui')->middleware('auth:api')->group(function () {
    Route::post('/workflow/process', [ComfyUIWorkflowController::class, 'process']);
    Route::get('/workflow/status/{promptId}', [ComfyUIWorkflowController::class, 'status']);
    Route::post('/workflow/cancel/{promptId}', [ComfyUIWorkflowController::class, 'cancel']);
    Route::get('/image', [ComfyUIWorkflowController::class, 'getImage']);
});

// ============================================================================
// Film Project Routes (namespace: film-projects)
// ============================================================================

Route::prefix('film-projects')->middleware('auth:api')->group(function () {
    // AI Models (must be before catch-all routes)
    Route::get('/ai/models', [FilmProductionController::class, 'getAvailableModels']);

    // Projects CRUD
    Route::get('/', [FilmProductionController::class, 'index']);
    Route::post('/', [FilmProductionController::class, 'store']);
    Route::get('/{id}', [FilmProductionController::class, 'show']);
    Route::put('/{id}', [FilmProductionController::class, 'update']);
    Route::delete('/{id}', [FilmProductionController::class, 'destroy']);

    // AI Script Generation
    Route::post('/{id}/generate/script', [FilmProductionController::class, 'generateScript']);

    // Sequences
    Route::get('/{projectId}/sequences', [FilmProductionController::class, 'getSequences']);
    Route::post('/{projectId}/sequences', [FilmProductionController::class, 'createSequence']);
    Route::get('/{projectId}/sequences/{sequenceId}', [FilmProductionController::class, 'getSequence']);
    Route::put('/{projectId}/sequences/{sequenceId}', [FilmProductionController::class, 'updateSequence']);
    Route::delete('/{projectId}/sequences/{sequenceId}', [FilmProductionController::class, 'deleteSequence']);

    // Shots
    Route::get('/{projectId}/sequences/{sequenceId}/shots', [FilmProductionController::class, 'getShots']);
    Route::post('/{projectId}/sequences/{sequenceId}/shots', [FilmProductionController::class, 'createShot']);
    Route::get('/{projectId}/sequences/{sequenceId}/shots/{shotId}', [FilmProductionController::class, 'getShot']);
    Route::put('/{projectId}/sequences/{sequenceId}/shots/{shotId}', [FilmProductionController::class, 'updateShot']);
    Route::delete('/{projectId}/sequences/{sequenceId}/shots/{shotId}', [FilmProductionController::class, 'deleteShot']);

    // AI Scene Generation
    Route::post('/{projectId}/sequences/{sequenceId}/shots/{shotId}/generate/scene', [FilmProductionController::class, 'generateScene']);
});
