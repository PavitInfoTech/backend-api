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
