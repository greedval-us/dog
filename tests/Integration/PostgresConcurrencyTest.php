<?php

use App\Models\GameAsset;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class, DatabaseTruncation::class);

beforeEach(function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Row-lock tests require PostgreSQL.');
    }
});

/** @param array<string, mixed> $operation */
function startDatabaseWorker(array $operation): Process
{
    $process = new Process([
        PHP_BINARY,
        ...(PHP_OS_FAMILY === 'Windows' ? ['-d', 'extension=pdo_pgsql'] : []),
        base_path('tests/Fixtures/database-worker.php'),
    ], base_path(), ['APP_ENV' => 'testing']);
    $process->setInput(json_encode([
        'connection' => config('database.connections.pgsql'),
        'storage' => Storage::disk('local')->path(''),
        ...$operation,
    ], JSON_THROW_ON_ERROR));
    $process->setTimeout(20);
    $process->start();

    return $process;
}

/** @param list<string> $names */
function waitForDatabaseLocks(array $names): void
{
    $deadline = microtime(true) + 10;

    do {
        DB::select('SELECT pg_stat_clear_snapshot()');
        $waiting = DB::table('pg_stat_activity')->whereIn('application_name', $names)->where('wait_event_type', 'Lock')->count();
        if ($waiting === count($names)) {
            return;
        }

        usleep(10000);
    } while (microtime(true) < $deadline);

    throw new RuntimeException('Concurrent workers did not reach the expected row locks.');
}

test('simultaneous requests buy one slot and debit the player once', function () {
    $user = User::factory()->create(['coins' => 100]);
    $names = ['dog-test-'.Str::uuid(), 'dog-test-'.Str::uuid()];
    $workers = [];
    DB::beginTransaction();

    try {
        User::query()->lockForUpdate()->findOrFail($user->id);
        foreach ($names as $name) {
            $workers[] = startDatabaseWorker(['name' => $name, 'action' => 'slot', 'user_id' => $user->id]);
        }
        waitForDatabaseLocks($names);
        DB::commit();

        foreach ($workers as $worker) {
            $worker->wait();
            expect($worker->isSuccessful())->toBeTrue($worker->getErrorOutput().$worker->getOutput());
            expect(trim($worker->getOutput()))->toBe('ok');
        }
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        foreach ($workers as $worker) {
            $worker->stop();
        }
    }

    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => 0, 'pet_slots' => 2]);
    $this->assertDatabaseCount('currency_transactions', 1);
});

test('simultaneous independent purchases cannot overspend a balance', function () {
    $user = User::factory()->create(['coins' => 100]);
    $names = ['dog-test-'.Str::uuid(), 'dog-test-'.Str::uuid()];
    $workers = [];
    DB::beginTransaction();

    try {
        User::query()->lockForUpdate()->findOrFail($user->id);
        foreach ($names as $name) {
            $workers[] = startDatabaseWorker(['name' => $name, 'action' => 'wallet', 'user_id' => $user->id]);
        }
        waitForDatabaseLocks($names);
        DB::commit();

        $results = [];
        foreach ($workers as $worker) {
            $worker->wait();
            expect($worker->isSuccessful())->toBeTrue($worker->getErrorOutput().$worker->getOutput());
            $results[] = trim($worker->getOutput());
        }
        sort($results);
        expect($results)->toBe(['insufficient', 'ok']);
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        foreach ($workers as $worker) {
            $worker->stop();
        }
    }

    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => 20]);
    $this->assertDatabaseCount('currency_transactions', 1);
});

test('purchasing a popular background does not wait for another catalogue reader', function () {
    Storage::fake('local');
    Storage::disk('local')->put('appearance/test/portrait.png', 'image');
    Storage::disk('local')->put('appearance/test/icon.png', 'icon');
    $user = User::factory()->create(['coins' => 100]);
    $pet = Pet::factory()->for($user)->create();
    $asset = GameAsset::factory()->background()->paid()->create();
    $worker = null;
    DB::beginTransaction();

    try {
        GameAsset::query()->sharedLock()->findOrFail($asset->id);
        $worker = startDatabaseWorker([
            'name' => 'dog-test-'.Str::uuid(), 'action' => 'appearance',
            'user_id' => $user->id, 'pet_id' => $pet->id, 'asset_id' => $asset->id,
        ]);
        $worker->wait();
        expect($worker->isSuccessful())->toBeTrue($worker->getErrorOutput().$worker->getOutput());
        expect(trim($worker->getOutput()))->toBe('ok');
    } finally {
        DB::rollBack();
        $worker?->stop();
    }

    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'background_asset_id' => $asset->id]);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => 0]);
    $this->assertDatabaseCount('asset_unlocks', 1);
    $this->assertDatabaseCount('currency_transactions', 1);
});
