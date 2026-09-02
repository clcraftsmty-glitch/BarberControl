<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Allow PHP's built-in server to deliver compiled CSS, JavaScript and images.
if (PHP_SAPI === 'cli-server') {
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $publicPath = realpath(__DIR__);
    $requestedFile = is_string($requestPath) ? realpath(__DIR__.$requestPath) : false;

    if ($requestedFile !== false
        && $publicPath !== false
        && str_starts_with($requestedFile, $publicPath.DIRECTORY_SEPARATOR)
        && is_file($requestedFile)) {
        return false;
    }
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
