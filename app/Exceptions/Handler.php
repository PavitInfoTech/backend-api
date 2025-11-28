<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Handler extends ExceptionHandler
{
    /**
     * Register any exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->renderable(function (Throwable $e, $request) {
            // Only handle API/JSON requests — keep normal HTML behavior for web pages
            $apiDomain = env('API_DOMAIN');
            $hostMatches = false;
            if (! empty($apiDomain)) {
                $parsed = preg_match('/^https?:\/\//', $apiDomain) ? parse_url($apiDomain, PHP_URL_HOST) : $apiDomain;
                $hostMatches = $parsed === $request->getHost();
            }

            if (! $request->expectsJson() && ! $request->is('api/*') && ! $hostMatches) {
                return null; // Let the default handler deal with it
            }

            // Validation errors
            if ($e instanceof ValidationException) {
                return $this->errorResponse('The given data was invalid.', 422, $e->errors());
            }

            if ($e instanceof AuthenticationException) {
                return $this->errorResponse($e->getMessage() ?: 'Unauthenticated.', 401);
            }

            if ($e instanceof AuthorizationException) {
                return $this->errorResponse($e->getMessage() ?: 'Forbidden.', 403);
            }

            if ($e instanceof ModelNotFoundException) {
                return $this->errorResponse('Resource not found.', 404);
            }

            if ($e instanceof NotFoundHttpException) {
                return $this->errorResponse('Route not found.', 404);
            }

            if ($e instanceof MethodNotAllowedHttpException) {
                return $this->errorResponse('Method not allowed.', 405);
            }

            if ($e instanceof HttpException) {
                return $this->errorResponse($e->getMessage() ?: 'HTTP error', $e->getStatusCode());
            }

            // General server error — return a minimal message; include debug trace only when APP_DEBUG is true.
            $status = 500;
            $message = app()->hasDebugModeEnabled() ? $e->getMessage() : 'Server Error';
            $response = $this->errorResponse($message, $status);

            if (app()->hasDebugModeEnabled()) {
                $response->setData(array_merge($response->getData(true), [
                    'exception' => get_class($e),
                    'trace' => collect($e->getTrace())->map(function ($frame) {
                        return Arr::except($frame, ['args']);
                    })->toArray(),
                ], []));
            }

            return $response;
        });
    }

    public function render($request, Throwable $e)
    {
        // If this is an API request or JSON request, return a consistent JSON structure
        $apiDomain = env('API_DOMAIN');
        $hostMatches = false;
        if (! empty($apiDomain)) {
            $parsed = preg_match('/^https?:\/\//', $apiDomain) ? parse_url($apiDomain, PHP_URL_HOST) : $apiDomain;
            $hostMatches = $parsed === $request->getHost();
        }

        if ($request->expectsJson() || $request->is('api/*') || $hostMatches) {
            // Handle validation exceptions explicitly
            if ($e instanceof ValidationException) {
                return $this->errorResponse('The given data was invalid.', 422, $e->errors());
            }

            if ($e instanceof AuthenticationException) {
                return $this->errorResponse($e->getMessage() ?: 'Unauthenticated.', 401);
            }

            if ($e instanceof AuthorizationException) {
                return $this->errorResponse($e->getMessage() ?: 'Forbidden.', 403);
            }

            if ($e instanceof ModelNotFoundException) {
                return $this->errorResponse('Resource not found.', 404);
            }

            if ($e instanceof NotFoundHttpException) {
                return $this->errorResponse('Route not found.', 404);
            }

            if ($e instanceof MethodNotAllowedHttpException) {
                return $this->errorResponse('Method not allowed.', 405);
            }

            if ($e instanceof HttpException) {
                return $this->errorResponse($e->getMessage() ?: 'HTTP error', $e->getStatusCode());
            }

            $status = 500;
            $message = app()->hasDebugModeEnabled() ? $e->getMessage() : 'Server Error';
            $response = $this->errorResponse($message, $status);
            if (app()->hasDebugModeEnabled()) {
                $response->setData(array_merge($response->getData(true), [
                    'exception' => get_class($e),
                    'trace' => collect($e->getTrace())->map(function ($frame) {
                        return Arr::except($frame, ['args']);
                    })->toArray(),
                ], []));
            }

            return $response;
        }

        return parent::render($request, $e);
    }

    protected function errorResponse(string $message, int $status = 400, array|null $errors = null): JsonResponse
    {
        $payload = [
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
        ];

        return response()->json($payload, $status);
    }
}
