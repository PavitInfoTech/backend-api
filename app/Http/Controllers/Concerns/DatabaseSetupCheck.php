<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Config;

trait DatabaseSetupCheck
{
    /**
     * Check whether required database tables are present.
     */
    protected function isDatabaseReady(): bool
    {
        $tables = [
            'migrations',
            'users',
            'admin_credentials',
            'app_settings',
        ];

        try {
            foreach ($tables as $table) {
                if (! Schema::hasTable($table)) {
                    return false;
                }
            }

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Run cache clear safely since the cache table may not exist yet.
     */
    protected function safeClearCache()
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('cache:clear');
        } catch (\Throwable $e) {
            // If cache table is missing or cache driver misconfigured, ignore.
        }
    }

    /**
     * Refresh DB connection to pick up environment settings.
     *
     * @param array|null $databaseConfig
     */
    protected function refreshDatabaseConnection(array $databaseConfig = null)
    {
        // Load connection config from array or fallback to current config.
        $config = $databaseConfig ?? [
            'driver' => config('database.default', 'mysql'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ];

        Config::set('database.connections.' . config('database.default', 'mysql'), $config);
        DB::purge(config('database.default', 'mysql'));
        DB::reconnect(config('database.default', 'mysql'));
    }
}
