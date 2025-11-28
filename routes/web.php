<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return view('welcome');
});
Route::post('/run-mig', function () {
    // This route is now allowed in all environments.
    // Ensure it's properly protected (auth, role checks, IP whitelist, etc.) when used in production.
    Artisan::call('migrate', ['--force' => true]);

    return response(Artisan::output(), 200);
})->middleware('auth');
