<?php

use App\Models\Disease;
use App\Models\Dog;
use App\Models\DogWorkBoard;
use App\Models\DogWorkOffer;
use App\Models\DogWorkShift;
use App\Models\DogWorkType;
use App\Models\GameAsset;
use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\PetDisease;
use App\Models\User;
use App\Modules\Pets\Actions\StartPetCare;
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

test('simultaneous veterinary visits charge and apply the service once', function (string $service, bool $sameToken) {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create(['health' => 50, 'health_max' => 100]);
    $episode = $service === 'treatment' ? PetDisease::factory()->for($pet)->create() : null;
    $price = match ($service) {
        'treatment' => 120, 'checkup' => 60, 'vaccination' => 100
    };
    $operation = ['action' => 'veterinarian', 'pet_id' => $pet->id, 'service' => $service,
        'episode_id' => $episode?->id, 'price' => $price, 'token' => (string) Str::uuid()];
    $second = $sameToken ? $operation : [...$operation, 'token' => (string) Str::uuid()];

    expect(runDatabaseRace($pet->user, [$operation, $second]))->toBe($sameToken ? ['ok', 'ok'] : ['ok', 'unavailable']);
    expect($pet->user->fresh()->coins)->toBe(500 - $price);
    expect($pet->fresh()->health)->toBe($service === 'checkup' ? 60.0 : 50.0);
    if ($episode !== null) {
        expect($episode->fresh()->ended_at)->not->toBeNull();
    }
    if ($service === 'vaccination') {
        expect($pet->fresh()->buffs)->toHaveCount(1);
    }
    $this->assertDatabaseCount('currency_transactions', 1);
    $this->assertDatabaseCount('veterinary_visits', 1);
})->with(['treatment replay' => ['treatment', true], 'treatment race' => ['treatment', false],
    'checkup replay' => ['checkup', true], 'checkup race' => ['checkup', false],
    'vaccination replay' => ['vaccination', true], 'vaccination race' => ['vaccination', false]]);

/** @param array<string, mixed> $operation */
function startDatabaseWorker(array $operation): Process
{
    $process = new Process([
        PHP_BINARY,
        ...(PHP_OS_FAMILY === 'Windows' && ! extension_loaded('pdo_pgsql') ? ['-d', 'extension=pdo_pgsql'] : []),
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

/**
 * @param  list<array<string, mixed>>  $operations
 * @return list<string>
 */
function runDatabaseRace(User $user, array $operations): array
{
    $workers = [];
    $names = [];
    DB::beginTransaction();

    try {
        User::query()->lockForUpdate()->findOrFail($user->id);
        foreach ($operations as $operation) {
            $names[] = $name = 'dog-test-'.Str::uuid();
            $workers[] = startDatabaseWorker(['name' => $name, 'user_id' => $user->id, 'at' => now()->toISOString(), ...$operation]);
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

        return $results;
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        foreach ($workers as $worker) {
            $worker->stop();
        }
    }
}

test('simultaneous care starts consume energy and the last item once', function (string $race) {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['energy' => 50, 'mood' => 20]);
    $toy = InventoryItem::factory()->for($pet->user)->for(Item::factory()->for(
        ItemCategory::factory()->state(['code' => 'toys']), 'category'
    ))->create(['remaining_uses' => 1]);
    $operation = ['action' => 'care-start', 'pet_id' => $pet->id, 'variant' => 'toy', 'items' => ['toys' => $toy->id], 'token' => (string) Str::uuid()];
    $second = $operation;
    if ($race !== 'same token') {
        $second['token'] = (string) Str::uuid();
    }
    if ($race === 'shared item') {
        $second['pet_id'] = Pet::factory()->for($pet->user)->create(['energy' => 50, 'mood' => 20])->id;
    }

    expect(runDatabaseRace($pet->user, [$operation, $second]))->toBe($race === 'same token' ? ['ok', 'ok'] : ['ok', 'unavailable']);
    $this->assertModelMissing($toy);
    $this->assertDatabaseCount('item_usages', 1);
    $this->assertDatabaseCount('pet_care_actions', 1);
    $winner = PetCareAction::query()->sole();
    $this->assertDatabaseHas('pets', ['id' => $winner->pet_id, 'energy' => 40, 'activity_token' => $winner->activity_token]);
    if ($race === 'shared item') {
        $loser = $winner->pet_id === $pet->id ? $second['pet_id'] : $pet->id;
        $this->assertDatabaseHas('pets', ['id' => $loser, 'energy' => 50, 'activity' => null]);
    }
})->with(['same token', 'different tokens', 'shared item']);

test('simultaneous care completions apply the saved result once', function () {
    $this->freezeSecond();
    $disease = Disease::factory()->create([
        'acquisition_rules' => ['group' => 'play', 'variants' => [], 'daily_min' => 1, 'daily_max' => 1],
        'is_active' => true, 'modifiers' => ['energy_cost_percent' => 20],
    ]);
    $pet = Pet::factory()->create(['energy' => 50, 'mood' => 20, 'mood_max' => 100]);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'attention', [], (string) Str::uuid());
    $this->travelTo($care->ends_at);
    $operation = ['action' => 'care-complete', 'pet_id' => $pet->id, 'token' => $care->token];

    expect(runDatabaseRace($pet->user, [$operation, $operation]))->toBe(['applied', 'replayed']);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'mood' => 29.9333, 'activity' => null]);
    $this->assertDatabaseHas('pet_care_actions', ['id' => $care->id, 'completed_at' => now()->toDateTimeString()]);
    $this->assertDatabaseCount('pet_disease_counters', 1);
    $this->assertDatabaseHas('pet_disease_counters', ['pet_id' => $pet->id, 'disease_id' => $disease->id, 'action_count' => 1]);
    $this->assertDatabaseCount('pet_diseases', 1);
    expect($pet->fresh()->debuffs)->toHaveCount(1);
});

test('simultaneous kennel retries return one durable purchase', function () {
    $dog = Dog::factory()->create(['is_starter' => true]);
    $user = User::factory()->create(['coins' => 1000, 'pet_slots' => 2, 'starter_pet_claimed_at' => now()]);
    $operation = ['action' => 'kennel', 'dog_id' => $dog->id, 'token' => (string) Str::uuid()];

    expect(runDatabaseRace($user, [$operation, $operation]))->toBe(['ok', 'ok']);
    $this->assertDatabaseCount('pets', 1);
    $this->assertDatabaseCount('kennel_purchases', 1);
    $this->assertDatabaseCount('currency_transactions', 1);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => 500]);
});

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

test('simultaneous different players compete for one global dog work place', function () {
    $this->freezeSecond();
    $offer = DogWorkOffer::factory()->create(['daily_limit' => 1]);
    $pets = [];
    foreach (range(1, 2) as $index) {
        $pet = Pet::factory()->create(['intelligence' => 100, 'obedience' => 100, 'energy' => 100]);
        $pet->skills()->attach($offer->required_skill_id, ['level' => $offer->required_skill_level]);
        $pets[] = $pet;
    }
    $workers = [];
    $names = [];
    DB::beginTransaction();
    try {
        DogWorkOffer::query()->lockForUpdate()->findOrFail($offer->id);
        foreach ($pets as $pet) {
            $names[] = $name = 'dog-test-'.Str::uuid();
            $workers[] = startDatabaseWorker(['name' => $name, 'user_id' => $pet->user_id, 'at' => now()->toISOString(),
                'action' => 'dog-work-start', 'pet_id' => $pet->id, 'offer_id' => $offer->id, 'token' => (string) Str::uuid()]);
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
        expect($results)->toBe(['ok', 'unavailable']);
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        foreach ($workers as $worker) {
            $worker->stop();
        }
    }
    $this->assertDatabaseCount('dog_work_shifts', 1);
    expect($offer->refresh()->reserved_count)->toBe(1);
    $winner = DogWorkShift::query()->sole();
    $loser = $pets[0]->id === $winner->pet_id ? $pets[1] : $pets[0];
    $this->assertDatabaseHas('pets', ['id' => $loser->id, 'energy' => 100, 'activity' => null]);
});

test('simultaneous board generators publish one daily selection', function () {
    $this->freezeSecond();
    DogWorkType::factory()->count(8)->create();
    $board = DogWorkBoard::factory()->create(['generated_at' => null]);
    $user = User::factory()->create();
    $workers = [];
    $names = [];
    DB::beginTransaction();
    try {
        DogWorkBoard::query()->lockForUpdate()->findOrFail($board->id);
        foreach (range(1, 2) as $index) {
            $names[] = $name = 'dog-test-'.Str::uuid();
            $workers[] = startDatabaseWorker(['name' => $name, 'user_id' => $user->id, 'at' => now()->toISOString(), 'action' => 'dog-work-board']);
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
    $this->assertDatabaseCount('dog_work_boards', 1);
    expect($board->offers()->count())->toBe(6);
    expect($board->refresh()->generated_at)->not->toBeNull();
});

test('simultaneous dog work retries reserve and reward only once', function () {
    $this->freezeSecond();
    $offer = DogWorkOffer::factory()->create(['gems_reward' => 1]);
    $pet = Pet::factory()->create(['intelligence' => 100, 'obedience' => 100, 'energy' => 100]);
    $pet->skills()->attach($offer->required_skill_id, ['level' => $offer->required_skill_level]);
    $operation = ['action' => 'dog-work-start', 'pet_id' => $pet->id, 'offer_id' => $offer->id, 'token' => (string) Str::uuid()];
    expect(runDatabaseRace($pet->user, [$operation, $operation]))->toBe(['ok', 'ok']);
    expect($offer->refresh()->reserved_count)->toBe(1);
    $shift = DogWorkShift::query()->sole();
    $coins = $pet->user->coins;
    $gems = $pet->user->gems;
    $this->travelTo($shift->ends_at);
    $finish = ['action' => 'dog-work-complete', 'token' => $shift->token];
    expect(runDatabaseRace($pet->user, [$finish, $finish]))->toBe(['ok', 'ok']);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => $coins + 150, 'gems' => $gems + 1]);
    $this->assertDatabaseCount('currency_transactions', 2);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'activity' => null]);
});
