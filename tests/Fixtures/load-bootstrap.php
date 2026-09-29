<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$runDirectory = realpath((string) getenv('DOG_LOAD_RUN'));
$testingDirectory = realpath(dirname(__DIR__, 2).'/storage/framework/testing');

if (getenv('APP_ENV') !== 'loadtest' || $runDirectory === false || $testingDirectory === false
    || dirname($runDirectory) !== $testingDirectory || ! str_starts_with(basename($runDirectory), 'loadtest-')
    || getenv('DB_CONNECTION') !== 'sqlite'
    || realpath((string) getenv('DB_DATABASE')) !== $runDirectory.DIRECTORY_SEPARATOR.'fixture.sqlite') {
    throw new RuntimeException('Load tests require their own SQLite database in storage/framework/testing/loadtest-*.');
}

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(PHP_SAPI === 'cli' ? Kernel::class : HttpKernel::class)->bootstrap();

if (! $app->environment('loadtest') || config('database.default') !== 'sqlite'
    || realpath(config('database.connections.sqlite.database')) !== realpath((string) getenv('DB_DATABASE'))
    || realpath(storage_path()) !== $runDirectory) {
    throw new RuntimeException('The application did not load the isolated load-test environment.');
}

return $app;
