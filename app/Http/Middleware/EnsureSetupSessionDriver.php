<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupSessionDriver
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldUseFileSessions($request)) {
            Config::set('session.driver', 'file');

            try {
                File::ensureDirectoryExists(storage_path('framework/sessions'));
            } catch (\Throwable $e) {
                // Laravel will report an actionable session-storage error if
                // the deployment user cannot create or write this directory.
            }
        }

        return $next($request);
    }

    private function shouldUseFileSessions(Request $request): bool
    {
        // The setup wizard must be able to render and submit before database
        // migrations have created the sessions table.
        if ($request->is('setup') || $request->is('setup/*')) {
            return true;
        }

        // If the login route is reached before the sessions table exists,
        // avoid failing in StartSession before the controller can redirect.
        if ($request->is('admin/login')) {
            try {
                return ! Schema::hasTable('sessions');
            } catch (\Throwable $e) {
                return true;
            }
        }

        return false;
    }
}
