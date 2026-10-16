<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

try {
    \App\Support\FirstRunBootstrap::prepare(dirname(__DIR__));
} catch (\Throwable $e) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Não foi possível preparar o CREASY CRM. Confira as permissões de escrita na pasta do projeto, storage, bootstrap/cache e public/storage.');
}

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
