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
            // For local development or when `API_PREFIX_FALLBACK=true` is set, also register the
            // /api prefix so developers can call routes via the /api prefix even when the app
            // uses a subdomain in production (e.g., `api.example.com` -> `/auth/login`).
            $apiDomain = env('API_DOMAIN');
            $shouldFallbackToPrefix = app()->environment(['local', 'testing'])
                || app()->runningUnitTests()
                || (app()->runningInConsole() && app()->environment('testing'))
                || filter_var(env('API_PREFIX_FALLBACK', false), FILTER_VALIDATE_BOOLEAN);

            if (! empty($apiDomain)) {
                \Illuminate\Support\Facades\Route::middleware('api')->domain($apiDomain)->group($api);

                // Enable /api prefix fallback in local/testing/unit-test contexts or when explicitly requested.
                if ($shouldFallbackToPrefix) {
                    \Illuminate\Support\Facades\Route::middleware('api')->prefix('api')->group($api);
                }
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
        // Use custom Authenticate middleware that returns JSON 401 instead of redirecting
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle all API exceptions with consistent JSON format
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            $apiDomain = env('API_DOMAIN');
            $isApiRequest = $request->expectsJson() || $request->is('api/*') || ($apiDomain && $request->getHost() === $apiDomain) || $request->is('*');
            if ($isApiRequest) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage() ?: 'Unauthenticated.',
                    'errors' => null,
                    'code' => 401,
                    'timestamp' => now()->toIso8601String(),
                ], 401);
            }
        });

        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            $apiDomain = env('API_DOMAIN');
            $isApiRequest = $request->expectsJson() || $request->is('api/*') || ($apiDomain && $request->getHost() === $apiDomain);
            if ($isApiRequest) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'The given data was invalid.',
                    'errors' => $e->errors(),
                    'code' => 422,
                    'timestamp' => now()->toIso8601String(),
                ], 422);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            $apiDomain = env('API_DOMAIN');
            $isApiRequest = $request->expectsJson() || $request->is('api/*') || ($apiDomain && $request->getHost() === $apiDomain);
            if ($isApiRequest) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage() ?: 'Forbidden.',
                    'errors' => null,
                    'code' => 403,
                    'timestamp' => now()->toIso8601String(),
                ], 403);
            }
        });

        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, $request) {
            $apiDomain = env('API_DOMAIN');
            $isApiRequest = $request->expectsJson() || $request->is('api/*') || ($apiDomain && $request->getHost() === $apiDomain);
            if ($isApiRequest) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Resource not found.',
                    'errors' => null,
                    'code' => 404,
                    'timestamp' => now()->toIso8601String(),
                ], 404);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            $apiDomain = env('API_DOMAIN');
            $isApiRequest = $request->expectsJson() || $request->is('api/*') || ($apiDomain && $request->getHost() === $apiDomain);
            if ($isApiRequest) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Route not found.',
                    'errors' => null,
                    'code' => 404,
                    'timestamp' => now()->toIso8601String(),
                ], 404);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException $e, $request) {
            $apiDomain = env('API_DOMAIN');
            $isApiRequest = $request->expectsJson() || $request->is('api/*') || ($apiDomain && $request->getHost() === $apiDomain);
            if ($isApiRequest) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Method not allowed.',
                    'errors' => null,
                    'code' => 405,
                    'timestamp' => now()->toIso8601String(),
                ], 405);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, $request) {
            $apiDomain = env('API_DOMAIN');
            $isApiRequest = $request->expectsJson() || $request->is('api/*') || ($apiDomain && $request->getHost() === $apiDomain);
            if ($isApiRequest) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage() ?: 'HTTP error',
                    'errors' => null,
                    'code' => $e->getStatusCode(),
                    'timestamp' => now()->toIso8601String(),
                ], $e->getStatusCode());
            }
        });
    })->create();
