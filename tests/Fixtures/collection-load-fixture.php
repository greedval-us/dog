<?php

use App\Models\Dog;
use App\Models\GameAsset;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\PetTitle;
use App\Models\User;
use App\Modules\Pets\Queries\GetPetCareer;
use App\Modules\Players\Queries\GetPlayerDogs;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

$app = require __DIR__.'/load-bootstrap.php';
$mode = $argv[1] ?? '';
Date::setTestNow(CarbonImmutable::parse('2026-10-04 12:00:00'));

if ($mode === 'prepare-schema') {
    $schema = config('database.connections.pgsql.search_path');
    if (DB::table('pg_namespace')->where('nspname', $schema)->exists()) {
        throw new RuntimeException('Collection load tests require a new fixture schema.');
    }
    DB::statement('CREATE SCHEMA "'.$schema.'"');
    if (Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]) !== 0) {
        throw new RuntimeException('Collection load-test migrations failed.');
    }
    $dog = Dog::factory()->create();
    Storage::disk('local')->put('appearance/test/portrait.png', 'isolated load fixture');
    Storage::disk('local')->put('appearance/test/icon.png', 'isolated load fixture');
    GameAsset::factory()->count(40)->for($dog)->create(['coat_color' => 'black']);
    GameAsset::factory()->count(40)->background()->create();
    GameAsset::factory()->count(20)->for($dog)->create(['coat_color' => 'white']);
    file_put_contents(storage_path('collection-dog'), (string) $dog->id);
    echo json_encode([
        'php' => PHP_VERSION, 'laravel' => $app->version(),
        'databaseVersion' => DB::selectOne('SELECT version()')->version,
        'memoryLimit' => ini_get('memory_limit'), 'xdebug' => extension_loaded('xdebug'),
        'appearanceCatalogueSize' => GameAsset::query()->count(), 'compatibleAssetsPerPet' => 80,
    ], JSON_THROW_ON_ERROR);
    exit;
}

if ($mode === 'prepare-dogs' || $mode === 'prepare-career') {
    $size = filter_var($argv[2] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $mode === 'prepare-dogs' ? 1000 : 10000]]);
    if ($size === false || ($mode === 'prepare-career' && ($size < 30 || $size % 10 !== 0))) {
        throw new RuntimeException('Use 1–1000 pets or 30–10000 career results in multiples of ten.');
    }
    $dog = Dog::query()->findOrFail((int) file_get_contents(storage_path('collection-dog')));
    $user = User::factory()->create();
    $fixture = DB::transaction(function () use ($mode, $size, $dog, $user): array {
        if ($mode === 'prepare-dogs') {
            $pets = Pet::factory()->count($size)->for($user)->for($dog)->create(['coat_color' => 'black']);
            Pet::factory()->for($user)->for($dog)->retired()->create();
            Pet::factory()->for($user)->for($dog)->deceased()->create();
            Pet::factory()->for($dog)->create();

            return ['owner' => $user->id, 'size' => $size, 'petIds' => $pets->modelKeys()];
        }

        $pet = Pet::factory()->for($user)->for($dog)->create();
        $disciplineNames = ['agility', 'nosework', 'canicross', 'conformation', 'progeny'];
        $entryIds = [];
        for ($index = 0; $index < $size; $index++) {
            $completedAt = CarbonImmutable::now()->subDays($user->id)->subSeconds($index);
            $discipline = $disciplineNames[$index % 5];
            $frequency = $index % 2 === 0 ? 'daily' : 'weekly';
            $event = GameEvent::factory()->create([
                'status' => 'settled', 'discipline' => $discipline, 'frequency' => $frequency,
                'registration_opens_at' => $completedAt->subDay(), 'closes_at' => $completedAt->subMinutes(25),
                'starts_at' => $completedAt->subMinutes(10), 'ends_at' => $completedAt, 'settled_at' => $completedAt,
            ]);
            $entry = GameEventEntry::factory()->for($event, 'event')->for($user)->for($pet)->create([
                'status' => 'completed', 'completed_at' => $completedAt, 'rank' => 1, 'prize' => 100,
                'result' => ['eliminated' => false, 'score' => 97], 'snapshot' => ['name' => 'Historical dog'],
            ]);
            PetTitle::factory()->for($entry, 'entry')->create([
                'pet_id' => $pet->id, 'discipline' => $discipline, 'frequency' => $frequency,
                'code' => $discipline.'_'.$frequency.'_winner', 'awarded_at' => $completedAt,
            ]);
            $entryIds[] = $entry->id;
        }
        $foreignPet = Pet::factory()->for($dog)->create();
        $foreignEntry = GameEventEntry::factory()->for($event, 'event')->for($foreignPet->user)->for($foreignPet)->create([
            'status' => 'completed', 'completed_at' => $completedAt, 'rank' => 1,
            'result' => ['eliminated' => false], 'snapshot' => ['name' => 'Foreign dog'],
        ]);
        PetTitle::factory()->for($foreignEntry, 'entry')->create(['pet_id' => $foreignPet->id]);

        return ['owner' => $user->id, 'pet' => $pet->id, 'size' => $size, 'entryIds' => $entryIds];
    });
    DB::statement('ANALYZE pets');
    DB::statement('ANALYZE game_events');
    DB::statement('ANALYZE game_event_entries');
    DB::statement('ANALYZE pet_titles');
    $key = $mode === 'prepare-dogs' ? 'dogs' : 'career';
    if ($key === 'career') {
        $firstPage = app(GetPetCareer::class)->handle($user, $fixture['pet'], 'en');
        $fixture['nextCursor'] = $firstPage['results']['nextCursor'];
    }
    $filename = $key.'-'.$user->id.'.json';
    file_put_contents(storage_path($filename), json_encode($fixture, JSON_THROW_ON_ERROR));
    echo json_encode(['fixture' => $filename, 'size' => $size], JSON_THROW_ON_ERROR);
    exit;
}

if (! in_array($mode, ['dogs', 'career-first', 'career-next', 'plans-dogs', 'plans-career'], true)
    || ! preg_match('/^(dogs|career)-[1-9][0-9]*\.json$/D', $argv[2] ?? '')) {
    throw new RuntimeException('Use prepare-schema, prepare-dogs, prepare-career, dogs, career-first, career-next, plans-dogs or plans-career.');
}
$fixture = json_decode(file_get_contents(storage_path($argv[2])), true, flags: JSON_THROW_ON_ERROR);
$user = User::query()->findOrFail($fixture['owner']);
$isDogs = in_array($mode, ['dogs', 'plans-dogs'], true);
$cursor = null;
if ($mode === 'career-next') {
    $cursor = Cursor::fromEncoded($fixture['nextCursor']);
}
DB::statement("SET statement_timeout = '30s'");
DB::disableQueryLog();
$queries = 0;
$sqlMilliseconds = 0.0;
$measuring = true;
$plansMode = str_starts_with($mode, 'plans-');
$planQueries = [];
DB::listen(function (QueryExecuted $query) use (&$measuring, &$queries, &$sqlMilliseconds, &$planQueries, $plansMode): void {
    if ($measuring) {
        $queries++;
        $sqlMilliseconds += $query->time;
        if ($plansMode) {
            $planQueries[] = ['sql' => $query->sql, 'bindings' => $query->bindings];
        }
    }
});
memory_reset_peak_usage();
$memoryBefore = memory_get_usage(true);
$started = hrtime(true);
$result = $isDogs
    ? app(GetPlayerDogs::class)->handle($user, 'en')
    : app(GetPetCareer::class)->handle($user, $fixture['pet'], 'en', $cursor);
$elapsedMilliseconds = (hrtime(true) - $started) / 1_000_000;
$peakMemory = memory_get_peak_usage(true);
$measuring = false;
$payload = json_encode($result, JSON_THROW_ON_ERROR);

if ($isDogs) {
    if (array_column($result, 'id') !== $fixture['petIds'] || count($result) !== $fixture['size']
        || collect($result)->contains(fn (array $pet): bool => $pet['portraitId'] === null || $pet['backgroundId'] === null)) {
        throw new RuntimeException('The dog collection omitted owned active pets or valid appearance, or included foreign/archived pets.');
    }
} else {
    $offset = $mode === 'career-next' ? 20 : 0;
    if ($result['petId'] !== $fixture['pet']
        || array_column($result['results']['entries'], 'id') !== array_slice($fixture['entryIds'], $offset, 20)
        || count($result['titles']) !== 10
        || array_sum(array_column($result['titles'], 'count')) !== $fixture['size']
        || collect($result['titles'])->contains(fn (array $title): bool => $title['count'] !== (int) ($fixture['size'] / 10))
        || $result['summary'] !== [
            'competitionStarts' => (int) ($fixture['size'] * 3 / 5), 'competitionWins' => (int) ($fixture['size'] * 3 / 5),
            'exhibitionStarts' => (int) ($fixture['size'] * 2 / 5), 'exhibitionWins' => (int) ($fixture['size'] * 2 / 5),
            'podiums' => $fixture['size'], 'cups' => (int) ($fixture['size'] / 2),
        ]
        || ($fixture['size'] > $offset + 20 && $result['results']['nextCursor'] === null)
        || ($offset === 0 && $result['results']['previousCursor'] !== null)) {
        throw new RuntimeException('Career pagination, owner isolation, title aggregation or summary counts changed.');
    }
    if ($offset > 0) {
        $previous = app(GetPetCareer::class)->handle($user, $fixture['pet'], 'en', Cursor::fromEncoded($result['results']['previousCursor']));
        if (array_column($previous['results']['entries'], 'id') !== array_slice($fixture['entryIds'], 0, 20)) {
            throw new RuntimeException('The previous career cursor did not return the first page.');
        }
    }
}

if ($plansMode) {
    $plans = [];
    foreach ($planQueries as $query) {
        $rawPlan = DB::selectOne('EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) '.$query['sql'], $query['bindings']);
        $plan = json_decode($rawPlan->{'QUERY PLAN'}, true, flags: JSON_THROW_ON_ERROR)[0];
        $plans[] = ['sql' => $query['sql'], 'plan' => $plan];
    }
    echo json_encode(['scenario' => $isDogs ? 'dogs' : 'career', 'size' => $fixture['size'], 'plans' => $plans], JSON_THROW_ON_ERROR);
    exit;
}
echo json_encode([
    'scenario' => $mode, 'size' => $fixture['size'], 'elapsedMs' => round($elapsedMilliseconds, 3),
    'queries' => $queries, 'sqlMs' => round($sqlMilliseconds, 3),
    'baselineMemoryMiB' => $memoryBefore / 1048576, 'peakMemoryMiB' => $peakMemory / 1048576,
    'peakGrowthMiB' => ($peakMemory - $memoryBefore) / 1048576, 'payloadBytes' => strlen($payload),
    'returnedPets' => $isDogs ? count($result) : null, 'returnedResults' => $isDogs ? null : count($result['results']['entries']),
    'returnedTitleGroups' => $isDogs ? null : count($result['titles']), 'verified' => true,
], JSON_THROW_ON_ERROR);
