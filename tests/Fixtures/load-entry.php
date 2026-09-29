<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

define('LARAVEL_START', microtime(true));
$app = require __DIR__.'/load-bootstrap.php';
$queryCount = 0;
$queryMilliseconds = 0.0;
DB::listen(function (QueryExecuted $query) use (&$queryCount, &$queryMilliseconds): void {
    $queryCount++;
    $queryMilliseconds += $query->time;
});

$kernel = $app->make(Kernel::class);
$request = Request::capture();
$response = $kernel->handle($request);
$response->headers->set('X-Dog-Load-Test', 'isolated');
$response->headers->set('X-Load-Queries', (string) $queryCount);
$response->headers->set('X-Load-Sql-Ms', (string) round($queryMilliseconds, 2));
$response->headers->set('X-Load-App-Ms', (string) round((microtime(true) - LARAVEL_START) * 1000, 2));
$response->send();
$kernel->terminate($request, $response);
