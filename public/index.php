<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__ . '/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__ . '/../vendor/autoload.php';

// Ensure a valid APP_KEY exists before the framework boots. If no `.env` exists,
// copy from `.env.example`. If APP_KEY is missing or empty, generate a base64
// 32-byte key and persist it to `.env` so the Encryption service won't fail.
$envPath = __DIR__ . '/../.env';
$envExample = __DIR__ . '/../.env.example';
try {
    if (!file_exists($envPath) && file_exists($envExample)) {
        copy($envExample, $envPath);
        // make sure copied file is readable by the process
        @chmod($envPath, 0644);
    }

    if (file_exists($envPath)) {
        $contents = file_get_contents($envPath);
        $hasKey = preg_match('/^APP_KEY\s*=\s*(.+)$/m', $contents, $matches);
        $keyVal = $hasKey ? trim($matches[1]) : '';
        if (!$hasKey || $keyVal === '' || $keyVal === 'null') {
            $random = base64_encode(random_bytes(32));
            $newKey = 'base64:' . $random;
            if ($hasKey) {
                $contents = preg_replace('/^APP_KEY\s*=.*$/m', 'APP_KEY=' . $newKey, $contents);
            } else {
                $contents .= PHP_EOL . 'APP_KEY=' . $newKey . PHP_EOL;
            }
            // attempt to persist the new key; ignore failures (runtime env will still be set)
            @file_put_contents($envPath, $contents);
            putenv('APP_KEY=' . $newKey);
            $_ENV['APP_KEY'] = $newKey;
            $_SERVER['APP_KEY'] = $newKey;
        } else {
            // ensure runtime has the key available
            putenv('APP_KEY=' . $keyVal);
            $_ENV['APP_KEY'] = $keyVal;
            $_SERVER['APP_KEY'] = $keyVal;
        }
    }
} catch (\Throwable $e) {
    // If anything fails here, do not block the request; Laravel will report
    // a clear error. Swallowing silently to avoid exposing internals.
}

// If this request is the secure migration endpoint, force a non-database cache
// driver so the application does not attempt to read the DB-backed cache table
// before migrations have been run. This prevents a fatal error when the
// `cache` table does not yet exist.
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$isMigrationEndpoint = false;
// Normalize and check for both prefixed and non-prefixed paths
if (stripos($requestUri, '/admin/migrate') === 0 || stripos($requestUri, '/api/admin/migrate') === 0) {
    $isMigrationEndpoint = true;
}
// Also allow a special header to force the migration-mode behavior
if (!$isMigrationEndpoint && (!empty($_SERVER['HTTP_X_RUN_MIG_TOKEN']) || !empty($_SERVER['HTTP_X_RUN_MIGRATION']))) {
    $isMigrationEndpoint = true;
}
if ($isMigrationEndpoint) {
    putenv('CACHE_STORE=file');
    putenv('CACHE_DRIVER=file');
    $_ENV['CACHE_STORE'] = 'file';
    $_ENV['CACHE_DRIVER'] = 'file';
    $_SERVER['CACHE_STORE'] = 'file';
    $_SERVER['CACHE_DRIVER'] = 'file';
}

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__ . '/../bootstrap/app.php';

$app->handleRequest(Request::capture());
