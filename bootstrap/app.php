<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        function () {
            $web = __DIR__ . '/../routes/web.php';
            $api = __DIR__ . '/../routes/api.php';
            $health = '/up';

            // If API_DOMAIN is present, register API routes on that domain without the `api` prefix
            $apiDomain = env('API_DOMAIN');
            if (!empty($apiDomain)) {
                \Illuminate\Support\Facades\Route::middleware('api')->domain($apiDomain)->group($api);
            } else {
                // Fallback to the default /api prefix for local/dev/testing.
                \Illuminate\Support\Facades\Route::middleware('api')->prefix('api')->group($api);
            }

            // Register web routes as usual
            \Illuminate\Support\Facades\Route::middleware('web')->group($web);

            // Keep the health endpoint behavior in line with the builder's default
            \Illuminate\Support\Facades\Route::get($health, function () {
                $exception = null;

                try {
                    \Illuminate\Support\Facades\Event::dispatch(new \Illuminate\Foundation\Events\DiagnosingHealth);
                } catch (\Throwable $e) {
                    if (app()->hasDebugModeEnabled()) {
                        throw $e;
                    }

                    report($e);

                    $exception = $e->getMessage();
                }

                return response(\Illuminate\Support\Facades\View::file(__DIR__ . '/../resources/health-up.blade.php', [
                    'exception' => $exception,
                ]), status: $exception ? 500 : 200);
            });
        },
        commands: __DIR__ . '/../routes/console.php',
        health: '/up'
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
