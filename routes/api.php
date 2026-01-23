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
use App\Http\Controllers\Api\SdInstanceController;
use App\Http\Controllers\Api\PaymentController;
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
        
        $server->resource('tags', JsonApiController::class)->relationships(function ($relationships) {
            $relationships->hasMany('items');
        });
    });

JsonApiRoute::server('v2')->prefix('v2')->resources(function (ResourceRegistrar $server) {
    // JSON:API resources for v2 go here.
});

JsonApiRoute::server('v2')->prefix('v2')->routes(function ($api) {
    $api->get('me', [MeController::class, 'readProfile']);
    $api->patch('me', [MeController::class, 'updateProfile']);
});

// ============================================================================
// Utility Routes
// ============================================================================

Route::get('/csrf-token', function () {
    return response()->json([
        'csrfToken' => csrf_token(),
    ]);
});

Route::get('/status/{serviceName?}', [StatusController::class, 'status']);

// ============================================================================
// Audio Generation Routes (ComfyUI Integration)
// ============================================================================

Route::prefix('audio')->group(function () {
    Route::get('/stream', [AudioController::class, 'stream']);
    Route::get('/status', [AudioController::class, 'status']);
    Route::get('/queue', [AudioController::class, 'queue']);
    Route::get('/config', [AudioController::class, 'config']);
});

// ============================================================================
// Video Job Routes (Legacy)
// ============================================================================

Route::prefix('video-jobs')->group(function () {
    // Public routes
    Route::post('/upload', [VideojobController::class, 'upload'])->middleware('api');
    Route::post('/generate', [VideojobController::class, 'generate'])->middleware('api');
    Route::post('/finalize', [VideojobController::class, 'finalize'])->middleware('api');
    Route::post('/cancel/{videoId}', [VideojobController::class, 'cancelJob'])->middleware('api');
    
    // Authenticated routes
    Route::middleware('auth:api')->group(function () {
        Route::get('/queue', [VideojobController::class, 'getVideoJobs']);
        Route::get('/processing/status', [VideojobController::class, 'processingStatus']);
        Route::get('/processing/queue', [VideojobController::class, 'processingQueue']);
        Route::patch('/{videoId}/audio', [VideojobController::class, 'attachAudio']);
    });
});

// ============================================================================
// Authentication Routes
// ============================================================================

// V1 Auth Routes
Route::prefix('auth')->group(function () {
    // Public routes
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/verified-email', [AuthController::class, 'emailVerification']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'sendLinkForgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    
    // Authenticated routes
    Route::middleware('auth:api')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

// V2 Auth Routes
Route::prefix('v2')->middleware('json.api')->group(function () {
    Route::post('/login', LoginController::class)->name('login');
    Route::post('/logout', LogoutController::class)->middleware('auth:api');
    Route::post('/register', RegisterController::class);
    Route::post('/password-forgot', ForgotPasswordController::class);
    Route::post('/password-reset', ResetPasswordController::class)->name('password.reset');
});

// Social Authentication Routes
Route::prefix('{providerName}')->group(function () {
    Route::get('/auth', [SocialiteAuthController::class, 'authUserFromSocialite']);
    Route::get('/callback', [SocialiteAuthController::class, 'addUserFromSocialite']);
});

// ============================================================================
// V1 Upload Routes
// ============================================================================

Route::prefix('v1')->middleware('auth:api')->group(function () {
    Route::post('/uploads/{resource}/{id}/{field}', UploadController::class);
});

// ============================================================================
// Administration Routes (Admin Only)
// ============================================================================

Route::prefix('administration')->middleware(['AuthorizationChecker', 'IsAdministratorChecker'])->group(function () {
    // User Management
    Route::get('/users', [UserController::class, 'getAllUsers']);
    Route::patch('/admin-reset-user-password', [UserController::class, 'adminResetUserPassword']);
    Route::patch('/change-user-data', [UserController::class, 'changeUserData']);
    Route::get('/users/{userId}/data-stats', [UserController::class, 'getUserDataStats']);
    Route::delete('/users/purge-data', [UserController::class, 'purgeUserData']);
    Route::patch('/change-password', [UserController::class, 'changePassword']);
    
    // Support Request Management
    Route::post('/support-requests', [SupportRequestController::class, 'getSupportRequestsByCriteria']);
    
    // Finance Operations
    Route::get('/finance-operations/get-all', [FinanceOperationsController::class, 'getAllFinanceOperations']);
    
    // Order Management
    Route::get('/orders', [OrderController::class, 'getAllOrders']);
    Route::patch('/orders/change-order-status', [OrderController::class, 'changeOrderStatus']);
    
    // SD Instance Management
    Route::get('/sd-instances', [SdInstanceController::class, 'index']);
    Route::post('/sd-instances', [SdInstanceController::class, 'store']);
    Route::get('/sd-instances/{id}', [SdInstanceController::class, 'show']);
    Route::put('/sd-instances/{id}', [SdInstanceController::class, 'update']);
    Route::patch('/sd-instances/{id}', [SdInstanceController::class, 'update']);
    Route::delete('/sd-instances/{id}', [SdInstanceController::class, 'destroy']);
    Route::patch('/sd-instances/{id}/toggle', [SdInstanceController::class, 'toggle']);
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

// Products
Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'getProductsByCategoryId']);
    Route::get('/{productId}', [ProductController::class, 'edit']);
    Route::patch('/{productId}', [ProductController::class, 'toggleActive']);
    
    Route::middleware('AuthorizationChecker')->group(function () {
        Route::get('/get-products-for-user', [ProductController::class, 'getProductsForUser']);
        Route::post('/', [ProductController::class, 'create']);
        Route::put('/{productId}', [ProductController::class, 'update']);
        Route::delete('/{productId}', [ProductController::class, 'delete']);
    });
});

// Properties
Route::prefix('properties')->group(function () {
    Route::get('/', [PropertyController::class, 'getPropertyByCategoryId']);
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

// ============================================================================
// Public Content Routes
// ============================================================================

// Questions (FAQ)
Route::prefix('questions')->group(function () {
    Route::get('/', [QuestionController::class, 'getAll']);
    Route::get('/{questionSlug}', [QuestionController::class, 'getBySlug']);
});
