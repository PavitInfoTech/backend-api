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

        $apiDomain = env('API_DOMAIN');
        $hostMatches = false;
        if (! empty($apiDomain)) {
            $parsed = preg_match('/^https?:\/\//', $apiDomain) ? parse_url($apiDomain, PHP_URL_HOST) : $apiDomain;
            $hostMatches = $parsed === $request->getHost();
        }

        // Only operate on API paths or API subdomain
        if (! $request->expectsJson() && ! $request->is('api/*') && ! $hostMatches) {
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
