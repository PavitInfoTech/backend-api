<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\SettingsController;

// Setup routes (public)
Route::get('/setup', [SetupController::class, 'index'])->name('setup.index');
Route::post('/setup/env', [SetupController::class, 'storeEnv'])->name('setup.store-env');
Route::post('/setup/admin', [SetupController::class, 'storeAdminSetup'])->name('setup.store-admin');

// Admin login routes
Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login']);
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

// Admin settings routes (protected)
Route::middleware('auth.admin')->prefix('settings')->name('settings.')->group(function () {
    Route::get('/', [SettingsController::class, 'index'])->name('index');
    
    // Mail settings
    Route::get('/mail', [SettingsController::class, 'mailSettings'])->name('mail');
    Route::post('/mail', [SettingsController::class, 'updateMailSettings'])->name('update-mail');
    
    // Auth settings
    Route::get('/auth', [SettingsController::class, 'authSettings'])->name('auth');
    Route::post('/auth', [SettingsController::class, 'updateAuthSettings'])->name('update-auth');
    
    // API Keys
    Route::get('/api', [SettingsController::class, 'apiSettings'])->name('api');
    Route::post('/api', [SettingsController::class, 'storeApiKey'])->name('store-api-key');
    Route::delete('/api/{id}', [SettingsController::class, 'deleteApiKey'])->name('delete-api-key');
    
    // Admin credentials
    Route::get('/admin-credentials', [SettingsController::class, 'adminCredentials'])->name('admin-credentials');
    Route::post('/admin-credentials', [SettingsController::class, 'storeAdminCredential'])->name('store-admin-credential');
    Route::put('/admin-credentials/{credential}', [SettingsController::class, 'updateAdminCredential'])->name('update-admin-credential');
    Route::delete('/admin-credentials/{credential}', [SettingsController::class, 'deleteAdminCredential'])->name('delete-admin-credential');

    // Cache management
    Route::get('/cache', [SettingsController::class, 'cacheManagement'])->name('cache');
    Route::post('/cache/clear', [SettingsController::class, 'clearCache'])->name('cache-clear');
    Route::post('/cache/config-clear', [SettingsController::class, 'clearConfigCache'])->name('cache-config-clear');
    Route::post('/cache/route-clear', [SettingsController::class, 'clearRouteCache'])->name('cache-route-clear');
});

// Admin dashboard redirect
Route::get('/admin', function () {
    return redirect()->route('settings.index');
})->name('admin.dashboard');

// Welcome page
Route::get('/', function () {
    return view('welcome');
});
