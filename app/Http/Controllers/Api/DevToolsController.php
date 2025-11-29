<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class DevToolsController extends ApiController
{
    /**
     * Run database migrations (safe run using --force)
     * Should only run when ALLOW_RUN_MIG is enabled and a valid token is provided.
     */
    public function runMigration(Request $request)
    {
        $allow = filter_var(env('ALLOW_RUN_MIG', false), FILTER_VALIDATE_BOOLEAN);
        if (! $allow) {
            return $this->error('Migrations via HTTP are disabled', 403);
        }

        $token = $request->header('X-RUN-MIG-TOKEN') ?? $request->input('token');
        $expected = env('RUN_MIG_TOKEN');
        if (empty($expected) || empty($token) || ! hash_equals($expected, $token)) {
            return $this->error('Invalid migration token', 403);
        }

        $doSeed = filter_var($request->input('seed', false), FILTER_VALIDATE_BOOLEAN);
        $path = $request->input('path');

        Log::info('DevTools: Running migrate via HTTP', ['path' => $path, 'seed' => $doSeed, 'remote' => $request->ip()]);

        try {
            $options = ['--force' => true];
            if (! empty($path)) {
                $options['--path'] = $path;
            }

            Artisan::call('migrate', $options);
            $output = Artisan::output();

            if ($doSeed) {
                Artisan::call('db:seed', ['--force' => true]);
                $output .= "\n" . Artisan::output();
            }

            // Clear caches to ensure new config/routes are loaded if needed
            Artisan::call('optimize:clear');
            $output .= "\n" . Artisan::output();

            return $this->success(['output' => $output], 'Migrations executed');
        } catch (\Throwable $e) {
            Log::error('DevTools: Migration failed', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->error('Migration failed: ' . $e->getMessage(), 500);
        }
    }
}
