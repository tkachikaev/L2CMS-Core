<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$projectRoot = dirname(__DIR__);
if (! is_file($projectRoot.'/.env')) {
    $scriptDirectory = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
    $basePath = $scriptDirectory === '/' ? '' : rtrim($scriptDirectory, '/');
    header('Location: '.$basePath.'/install/', true, 302);
    exit;
}

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../vendor/autoload.php';
(require_once __DIR__.'/../bootstrap/app.php')->handleRequest(Request::capture());
