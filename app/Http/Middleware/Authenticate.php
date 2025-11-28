<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

/**
 * Custom Authenticate middleware for API-only applications.
 *
 * Instead of redirecting unauthenticated users to a login route (which doesn't
 * exist in an API), this middleware always throws an AuthenticationException
 * which the exception handler converts to a JSON 401 response.
 */
class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * For API requests, we return null so that an AuthenticationException is thrown
     * instead of attempting a redirect to the non-existent "login" route.
     */
    protected function redirectTo(Request $request): ?string
    {
        // Always return null for API requests — never redirect
        return null;
    }
}
