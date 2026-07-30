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

            // Every API endpoint has one stable public path: /api/...
            // API_DOMAIN may still be used for deployment DNS or CORS, but it never
            // changes the route prefix or exposes API routes at the domain root.
            \Illuminate\Support\Facades\Route::middleware('api')->prefix('api')->group($api);

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
        // The setup wizard must use file sessions until migrations create the
        // database sessions table. This middleware runs before StartSession.
        $middleware->prepend(\App\Http\Middleware\EnsureSetupSessionDriver::class);

        // Use custom Authenticate middleware that returns JSON 401 instead of redirecting
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'auth.admin' => \App\Http\Middleware\AdminAuthMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle all API exceptions with consistent JSON format
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            $isApiRequest = $request->expectsJson() || $request->is('api/*');
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
            $isApiRequest = $request->expectsJson() || $request->is('api/*');
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
            $isApiRequest = $request->expectsJson() || $request->is('api/*');
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
            $isApiRequest = $request->expectsJson() || $request->is('api/*');
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
            $isApiRequest = $request->expectsJson() || $request->is('api/*');
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
            $isApiRequest = $request->expectsJson() || $request->is('api/*');
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
            $isApiRequest = $request->expectsJson() || $request->is('api/*');
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
