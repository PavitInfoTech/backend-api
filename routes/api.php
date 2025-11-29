<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group.
|
*/

Route::middleware('auth:sanctum')->group(function () {
    // Profile
    Route::get('/user', [\App\Http\Controllers\Api\ProfileController::class, 'show']);
    Route::put('/user', [\App\Http\Controllers\Api\ProfileController::class, 'update']);
    Route::post('/user/avatar', [\App\Http\Controllers\Api\ProfileController::class, 'uploadAvatar']);
    Route::delete('/user', [\App\Http\Controllers\Api\ProfileController::class, 'destroy']);
    // Authenticated password change for logged-in users (requires current password)
    Route::post('/auth/password/change', [\App\Http\Controllers\Api\AuthController::class, 'changePassword']);

    // AI (Gorq)
    Route::post('/ai/generate', [\App\Http\Controllers\Api\AIController::class, 'generate'])->middleware('throttle:ai');
    Route::get('/ai/jobs/{id}/status', [\App\Http\Controllers\Api\AIController::class, 'jobStatus']);

    // Maps
    Route::post('/maps/pin', [\App\Http\Controllers\Api\MapsController::class, 'createPin']);

    // Social linking (authenticated flows) — attach social provider to an existing account
    Route::get('/auth/link/google/redirect', [\App\Http\Controllers\Api\AuthController::class, 'linkToGoogle']);
    Route::get('/auth/link/google/callback', [\App\Http\Controllers\Api\AuthController::class, 'handleLinkGoogleCallback']);
    Route::get('/auth/link/github/redirect', [\App\Http\Controllers\Api\AuthController::class, 'linkToGithub']);
    Route::get('/auth/link/github/callback', [\App\Http\Controllers\Api\AuthController::class, 'handleLinkGithubCallback']);

    // Unlink a social provider (authenticated)
    Route::post('/auth/unlink', [\App\Http\Controllers\Api\AuthController::class, 'unlinkProvider']);

    // API fallback — return structured JSON for unmatched API routes
    Route::fallback(function () {
        Log::info('API fallback triggered', ['uri' => request()->path()]);
        return response()->json([
            'status' => 'error',
            'message' => 'Route not found',
            'errors' => null,
            'code' => 404,
            'timestamp' => now()->toIso8601String(),
        ], 404);
    });
});

// Health / ping endpoint — simple status check returning JSON
Route::get('/ping', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'OK',
        'data' => ['status' => 'ok'],
        'code' => 200,
        'timestamp' => now()->toIso8601String(),
    ], 200);
})->name('ping');

// Public profiles
Route::get('/users/{id}/public', [\App\Http\Controllers\Api\ProfileController::class, 'publicProfile']);

// Auth (public)
Route::post('/auth/register', [\App\Http\Controllers\Api\AuthController::class, 'register']);
Route::post('/auth/login', [\App\Http\Controllers\Api\AuthController::class, 'login']);
Route::post('/auth/logout', [\App\Http\Controllers\Api\AuthController::class, 'logout']);
Route::post('/auth/password/forgot', [\App\Http\Controllers\Api\AuthController::class, 'sendPasswordReset']);
Route::post('/auth/password/reset', [\App\Http\Controllers\Api\AuthController::class, 'resetPassword']);

Route::post('/auth/verify/send', [\App\Http\Controllers\Api\AuthController::class, 'sendVerification']);
Route::get('/auth/verify/{token}', [\App\Http\Controllers\Api\AuthController::class, 'verifyEmail']);

// OAuth API-based token exchanges
Route::post('/auth/google/token', [\App\Http\Controllers\Api\AuthController::class, 'googleTokenLogin']);
Route::post('/auth/github/token', [\App\Http\Controllers\Api\AuthController::class, 'githubTokenLogin']);

// OAuth
Route::get('/auth/google/redirect', [\App\Http\Controllers\Api\AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [\App\Http\Controllers\Api\AuthController::class, 'handleGoogleCallback']);
Route::get('/auth/github/redirect', [\App\Http\Controllers\Api\AuthController::class, 'redirectToGithub']);
Route::get('/auth/github/callback', [\App\Http\Controllers\Api\AuthController::class, 'handleGithubCallback']);

// Public mail endpoints
Route::post('/mail/contact', [\App\Http\Controllers\Api\MailController::class, 'contact']);
Route::post('/mail/newsletter', [\App\Http\Controllers\Api\MailController::class, 'newsletter']);
Route::post('/mail/password-reset', [\App\Http\Controllers\Api\MailController::class, 'passwordReset']);

// Dev tools: run migrations via HTTP POST. Use X-RUN-MIG-TOKEN header (env RUN_MIG_TOKEN) and set ALLOW_RUN_MIG=true in .env.
Route::post('/admin/migrate', [\App\Http\Controllers\Api\DevToolsController::class, 'runMigration'])->middleware('throttle:10,1');

// API fallback — return structured JSON for unmatched API routes
Route::fallback(function () {
    return response()->json([
        'status' => 'error',
        'message' => 'Route not found',
        'errors' => null,
        'code' => 404,
        'timestamp' => now()->toIso8601String(),
    ], 404);
});
