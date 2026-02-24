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
        $rawKey = $hasKey ? trim($matches[1]) : '';
        // strip surrounding quotes if present
        $keyVal = $rawKey !== '' ? trim($rawKey, "'\" \t\r\n") : '';

        // determine expected key length from config/app.php if possible
        $expectedLen = 32; // default to 32 bytes (AES-256)
        $configApp = @file_get_contents(__DIR__ . '/../config/app.php');
        if ($configApp !== false) {
            if (preg_match("/'cipher'\s*=>\s*'([^']+)'/", $configApp, $cMatch)) {
                $cipher = strtoupper($cMatch[1]);
                if (strpos($cipher, '128') !== false) {
                    $expectedLen = 16;
                } else {
                    $expectedLen = 32;
                }
            }
        }

        $valid = false;
        if ($keyVal !== '') {
            if (str_starts_with($keyVal, 'base64:')) {
                $decoded = base64_decode(substr($keyVal, 7), true);
                $valid = $decoded !== false && strlen($decoded) === $expectedLen;
            } else {
                // raw key provided: check length
                $valid = strlen($keyVal) === $expectedLen;
            }
        }

        if (!$valid) {
            // generate a new key that matches expected length
            $randomBytes = random_bytes($expectedLen);
            $random = base64_encode($randomBytes);
            $newKey = 'base64:' . $random;
            if ($hasKey) {
                $contents = preg_replace('/^APP_KEY\s*=.*$/m', 'APP_KEY=' . $newKey, $contents);
            } else {
                $contents .= PHP_EOL . 'APP_KEY=' . $newKey . PHP_EOL;
            }
            @file_put_contents($envPath, $contents);
            putenv('APP_KEY=' . $newKey);
            $_ENV['APP_KEY'] = $newKey;
            $_SERVER['APP_KEY'] = $newKey;
        } else {
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

// If the app uses SQLite and the database file does not yet exist, avoid
// using DB-backed session/cache drivers so the first request (e.g. /setup)
// does not attempt to read the database. Switch to `file` drivers at runtime.
try {
    $envContents = file_exists($envPath) ? file_get_contents($envPath) : '';
    // simple parser for key=value lines
    $getEnv = function ($key) use ($envContents) {
        if (!$envContents) {
            return getenv($key) ?: null;
        }
        if (preg_match('/^' . preg_quote($key, '/') . '\s*=\s*(.*)$/m', $envContents, $m)) {
            return trim($m[1], "'"."\""." \t\r\n");
        }
        return getenv($key) ?: null;
    };

    $dbConn = $getEnv('DB_CONNECTION') ?: 'sqlite';
    $dbDatabase = $getEnv('DB_DATABASE') ?: database_path('database.sqlite');
    $sessionDriver = $getEnv('SESSION_DRIVER') ?: 'file';

    if (strtolower($dbConn) === 'sqlite') {
        // If DB path is relative, make it absolute relative to project root
        if (!preg_match('#^(/|[A-Za-z]:\\)#', $dbDatabase)) {
            $dbDatabase = __DIR__ . '/../' . ltrim($dbDatabase, "./");
        }
        if (!file_exists($dbDatabase)) {
            // switch session/cache/queue to file/sync to avoid DB access
            putenv('SESSION_DRIVER=file');
            putenv('CACHE_DRIVER=file');
            putenv('CACHE_STORE=file');
            putenv('QUEUE_CONNECTION=sync');
            $_ENV['SESSION_DRIVER'] = 'file';
            $_ENV['CACHE_DRIVER'] = 'file';
            $_ENV['CACHE_STORE'] = 'file';
            $_ENV['QUEUE_CONNECTION'] = 'sync';
            $_SERVER['SESSION_DRIVER'] = 'file';
            $_SERVER['CACHE_DRIVER'] = 'file';
            $_SERVER['CACHE_STORE'] = 'file';
            $_SERVER['QUEUE_CONNECTION'] = 'sync';
        }
    }
} catch (\Throwable $e) {
    // Non-fatal during early bootstrap
}

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__ . '/../bootstrap/app.php';

$app->handleRequest(Request::capture());
