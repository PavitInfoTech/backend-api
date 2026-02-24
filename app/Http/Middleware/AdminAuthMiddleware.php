<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAuthMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $adminCredential = session('admin');

        if (!$adminCredential) {
            return redirect()->route('admin.login')->with('error', 'Please login to access this page.');
        }

        return $next($request);
    }
}
