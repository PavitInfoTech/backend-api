<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EnsureApiResponseIsJson
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only operate on API paths. API_DOMAIN does not create a root-path API alias.
        if (! $request->expectsJson() && ! $request->is('api/*')) {
            return $response;
        }

        // If response is already JSON — do nothing
        if ($response instanceof JsonResponse || str_contains($response->headers->get('Content-Type', ''), 'application/json')) {
            return $response;
        }

        // Only wrap error responses (status >= 400)
        $status = $response->getStatusCode();
        if ($status < 400) {
            return $response;
        }

        $message = SymfonyResponse::$statusTexts[$status] ?? 'Error';

        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => null,
            'code' => $status,
            'timestamp' => now()->toIso8601String(),
        ], $status);
    }
}
