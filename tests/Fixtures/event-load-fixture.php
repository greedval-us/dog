<?php

use App\Models\Achievement;
use App\Models\CurrencyTransaction;
use App\Models\Dog;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Enums\PetStat;
use App\Modules\Pets\Services\GameEventProcessor;
use Carbon\CarbonImmutable;
use Database\Seeders\AchievementSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

$app = require __DIR__.'/load-bootstrap.php';
$mode = $argv[1] ?? '';

if ($mode === 'prepare-schema') {
    $schema = config('database.connections.pgsql.search_path');
    if (DB::table('pg_namespace')->where('nspname', $schema)->exists()) {
        throw new RuntimeException('Event load tests require a new fixture schema.');
    }
    DB::statement('CREATE SCHEMA "'.$schema.'"');
    if (Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]) !== 0) {
        throw new RuntimeException('Event load-test migrations failed.');
    }
    if (Artisan::call('db:seed', ['--class' => AchievementSeeder::class, '--force' => true, '--no-interaction' => true]) !== 0) {
        throw new RuntimeException('Event load-test achievement seeding failed.');
    }
    echo json_encode([
        'php' => PHP_VERSION, 'laravel' => $app->version(),
        'databaseVersion' => DB::selectOne('SELECT version()')->version,
        'memoryLimit' => ini_get('memory_limit'), 'xdebug' => extension_loaded('xdebug'),
        'achievementCatalogueSize' => Achievement::query()->where('is_active', true)->count(),
    ], JSON_THROW_ON_ERROR);
    exit;
}

if ($mode === 'prepare-case') {
    $groups = filter_var($argv[2] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 500]]);
    $humansPerGroup = filter_var($argv[3] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 8]]);
    $scenario = $argv[4] ?? '';
    if ($groups === false || $humansPerGroup === false || ! in_array($scenario, ['split', 'backlog'], true)) {
        throw new RuntimeException('Use 1–500 groups and 1–8 human participants per group.');
    }
    Date::setTestNow(CarbonImmutable::parse('2026-10-04 12:00:00')->addDays(GameEvent::query()->count()));
    $dog = Dog::factory()->create();
    $event = GameEvent::factory()->create(['seed' => 'event-load:'.$groups.':'.$humansPerGroup.':'.$scenario]);
    for ($group = 1; $group <= $groups; $group++) {
        for ($slot = 1; $slot <= $humansPerGroup; $slot++) {
            $identity = 'event_'.$event->id.'_group_'.$group.'_slot_'.$slot;
            $user = User::factory()->create([
                'name' => $identity, 'username' => $identity, 'email' => $identity.'@example.test', 'coins' => 475,
            ]);
            $states = ['born_at' => now()->subDays(10), 'bond' => 70];
            foreach (PetStat::cases() as $stat) {
                $states[$stat->value] = 20;
            }
            $pet = Pet::factory()->for($user)->for($dog)->create($states);
            GameEventEntry::factory()->for($event, 'event')->for($user)->for($pet)->create([
                'division' => 'novice:medium'.($group === 1 ? '' : ':heat-'.$group),
                'plan' => ['stages' => ['careful', 'careful', 'careful']],
            ]);
        }
    }
    echo json_encode(['event' => $event->id], JSON_THROW_ON_ERROR);
    exit;
}

$eventId = filter_var($argv[2] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (! in_array($mode, ['freeze', 'settle', 'backlog'], true) || $eventId === false) {
    throw new RuntimeException('Use prepare-schema, prepare-case, freeze, settle or backlog.');
}

$event = GameEvent::query()->findOrFail($eventId);
$humanCount = $event->entries()->where('is_npc', false)->count();
$groupCount = $event->entries()->distinct()->count('division');
$expectedCount = $groupCount * $event->rules['field_size'];
$at = $mode === 'freeze' ? $event->closes_at : $event->ends_at;
Date::setTestNow($at);
DB::statement("SET lock_timeout = '5s'");
DB::statement("SET statement_timeout = '30s'");
DB::disableQueryLog();
$queries = 0;
$sqlMilliseconds = 0.0;
$transactions = [];
$rolledBack = 0;
$transactionStarted = null;
$measuring = true;
DB::listen(function (QueryExecuted $query) use (&$measuring, &$queries, &$sqlMilliseconds): void {
    if ($measuring) {
        $queries++;
        $sqlMilliseconds += $query->time;
    }
});
Event::listen(TransactionBeginning::class, function (TransactionBeginning $transaction) use (&$measuring, &$transactionStarted): void {
    if ($measuring && $transaction->connection->transactionLevel() === 1) {
        $transactionStarted = hrtime(true);
    }
});
Event::listen(TransactionCommitted::class, function (TransactionCommitted $transaction) use (&$measuring, &$transactionStarted, &$transactions): void {
    if ($measuring && $transaction->connection->transactionLevel() === 0 && $transactionStarted !== null) {
        $transactions[] = (hrtime(true) - $transactionStarted) / 1_000_000;
        $transactionStarted = null;
    }
});
Event::listen(TransactionRolledBack::class, function (TransactionRolledBack $transaction) use (&$measuring, &$transactionStarted, &$rolledBack): void {
    if ($measuring && $transaction->connection->transactionLevel() === 0) {
        $rolledBack++;
        $transactionStarted = null;
    }
});
memory_reset_peak_usage();
$memoryBefore = memory_get_usage(true);
$started = hrtime(true);
$processed = app(GameEventProcessor::class)->processDue($at, limit: 1);
$elapsedMilliseconds = (hrtime(true) - $started) / 1_000_000;
$peakMemory = memory_get_peak_usage(true);
$measuring = false;

$entries = $event->entries()->with('user', 'pet')->get();
$expectedStatus = $mode === 'freeze' ? 'frozen' : 'completed';
if ($processed !== 1 || count($transactions) !== 1 || $rolledBack !== 0
    || $event->fresh()->status !== ($mode === 'freeze' ? 'frozen' : 'settled')
    || $entries->count() !== $expectedCount || $entries->where('status', $expectedStatus)->count() !== $expectedCount
    || $entries->where('is_npc', false)->count() !== $humanCount
    || $entries->contains(fn (GameEventEntry $entry): bool => $entry->snapshot === null)) {
    throw new RuntimeException('Event processing did not preserve the complete fixture.');
}
if ($mode !== 'freeze') {
    foreach ($entries->groupBy('division') as $group) {
        if ($group->pluck('rank')->sort()->values()->all() !== range(1, $event->rules['field_size'])) {
            throw new RuntimeException('Ranks are incomplete or overlap inside a group.');
        }
    }
    foreach ($entries->where('is_npc', false) as $entry) {
        $expectedPrize = $entry->result['eliminated'] ? 0 : ($event->rules['prizes'][$entry->rank - 1] ?? 0);
        if ($entry->prize !== $expectedPrize || $entry->user->coins !== 475 + $expectedPrize
            || $entry->experience_awarded === null || $entry->pet->isBusy()) {
            throw new RuntimeException('Settlement did not preserve rewards, experience or pet availability.');
        }
    }
    if ($entries->where('is_npc', true)->sum('prize') !== 0) {
        throw new RuntimeException('NPCs received a prize.');
    }
}
$rewardCount = CurrencyTransaction::query()->count();
if (app(GameEventProcessor::class)->processDue($at, limit: 1) !== 0 || CurrencyTransaction::query()->count() !== $rewardCount) {
    throw new RuntimeException('Repeated processing changed a settled or running event.');
}
echo json_encode([
    'phase' => $mode, 'groups' => $groupCount, 'humans' => $humanCount, 'entries' => $entries->count(),
    'npcs' => $entries->where('is_npc', true)->count(), 'elapsedMs' => round($elapsedMilliseconds, 3),
    'transactionMs' => round($transactions[0], 3), 'queries' => $queries, 'sqlMs' => round($sqlMilliseconds, 3),
    'baselineMemoryMiB' => $memoryBefore / 1048576, 'peakMemoryMiB' => $peakMemory / 1048576,
    'peakGrowthMiB' => ($peakMemory - $memoryBefore) / 1048576, 'verified' => true,
], JSON_THROW_ON_ERROR);
