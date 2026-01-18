<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\VideojobController;
use App\Http\Controllers\Admin\FileBrowserController;
use App\Http\Controllers\Admin\BeatMatchVideoController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/csrf-token', function() {
    return response()->json([
        'csrfToken' => csrf_token(),
    ]);
});
Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
Route::post('/upload', [VideojobController::class, 'upload'])->middleware('auth:api');
Route::post('/cancelJob/{id}', [VideojobController::class, 'cancelJob'])->middleware('auth:api');
Route::post('/generate', [VideojobController::class, 'generate'])->middleware('auth:api');
Route::post('/finalize', [VideojobController::class, 'finalize']);
Route::get('/status/{id}', [VideojobController::class, 'status']);
Route::get('/queue', [VideojobController::class, 'getVideoJobs'])->middleware('auth:api');
Route::view('/deforumation-qt', 'deforumation-qt')->name('deforumation.qt');
ctf0\MediaManager\MediaRoutes::routes();

Route::middleware(['AuthorizationChecker', 'IsAdministratorChecker'])
    ->group(function () {
        Route::get('/administration/files', [FileBrowserController::class, 'index'])
            ->name('admin.files');
        Route::get('/administration/beat-match-video', [BeatMatchVideoController::class, 'index'])
            ->name('admin.beat-match-video');
        Route::post('/administration/beat-match-video/process', [BeatMatchVideoController::class, 'process'])
            ->name('admin.beat-match-video.process');
        Route::get('/administration/beat-match-video/status/{id}', [BeatMatchVideoController::class, 'status'])
            ->name('admin.beat-match-video.status');
    });

                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                
