<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$runDirectory = realpath((string) getenv('DOG_LOAD_RUN'));
$testingDirectory = realpath(dirname(__DIR__, 2).'/storage/framework/testing');

if (getenv('APP_ENV') !== 'loadtest' || $runDirectory === false || $testingDirectory === false
    || dirname($runDirectory) !== $testingDirectory || ! str_starts_with(basename($runDirectory), 'loadtest-')
    || getenv('DB_CONNECTION') !== 'pgsql' || getenv('DB_DATABASE') !== 'dog_loadtest'
    || ! preg_match('/^loadtest_[a-f0-9]{16}$/D', (string) getenv('DB_SCHEMA'))
    || ! is_file($runDirectory.DIRECTORY_SEPARATOR.'database-schema')
    || file_get_contents($runDirectory.DIRECTORY_SEPARATOR.'database-schema') !== getenv('DB_SCHEMA')) {
    throw new RuntimeException('Load tests require dog_loadtest and their own PostgreSQL schema in storage/framework/testing/loadtest-*.');
}

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(PHP_SAPI === 'cli' ? Kernel::class : HttpKernel::class)->bootstrap();

if (! $app->environment('loadtest') || config('database.default') !== 'pgsql'
    || config('database.connections.pgsql.database') !== 'dog_loadtest'
    || config('database.connections.pgsql.search_path') !== getenv('DB_SCHEMA')
    || config('database.redis.options.prefix') !== getenv('DB_SCHEMA').'-database-'
    || config('session.driver') !== 'redis' || config('cache.default') !== 'redis'
    || realpath(storage_path()) !== $runDirectory) {
    throw new RuntimeException('The application did not load the isolated load-test environment.');
}

return $app;
