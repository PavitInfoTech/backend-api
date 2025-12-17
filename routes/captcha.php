<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CaptchaController;

Route::post('/captcha/verify', [CaptchaController::class, 'verify']);
