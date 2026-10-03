<?php

use App\Models\Disease;
use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\PetDisease;
use App\Models\PetDiseaseCounter;
use App\Modules\Pets\Actions\CompletePetCare;
use App\Modules\Pets\Actions\StartPetCare;
use App\Modules\Pets\Enums\PetActivity;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\GetPetCare;
use App\Modules\Pets\Queries\GetPetStatuses;
use App\Modules\Pets\Services\PetActivityManager;
use Carbon\CarbonImmutable;
use Database\Seeders\DiseaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Random\Engine;
use Random\Randomizer;

function diseaseRandom(int $draw): Randomizer
{
    return new Randomizer(new class($draw) implements Engine
    {
        public function __construct(private int $draw) {}

        public function generate(): string
        {
            return pack('V', $this->draw);
        }
    });
}

function readyDiseaseCare(Pet $pet, string $group, string $variant): PetCareAction
{
    $activity = app(PetActivityManager::class)->start($pet->user, $pet->id, PetActivity::from($group), now()->addSeconds(30), 0);

    return PetCareAction::factory()->create([
        'pet_id' => $pet->id, 'user_id' => $pet->user_id,
        'group' => $group, 'variant' => $variant, 'activity_token' => $activity->token,
        'ends_at' => $activity->endsAt, 'available_at' => $activity->endsAt, 'effects' => [],
    ]);
}

test('each starter disease appears at its saved daily threshold and does not stack', function (string $code, string $group, string $variant, int $threshold) {
    $this->freezeSecond();
    $this->seed(DiseaseSeeder::class);
    $pet = Pet::factory()->create();
    $disease = Disease::query()->where('code', $code)->firstOrFail();
    $counter = PetDiseaseCounter::factory()->for($pet)->for($disease)->create([
        'tracked_on' => now()->setTimezone('Europe/Moscow')->toDateString(), 'action_count' => $threshold - 2, 'threshold' => $threshold,
    ]);
    $first = readyDiseaseCare($pet, $group, $variant);
    $this->travel(30)->seconds();
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $first->token);
    $this->assertDatabaseCount('pet_diseases', 0);

    $next = readyDiseaseCare($pet, $group, $variant);
    $this->travel(30)->seconds();
    $this->actingAs($pet->user)->post(route('pets.care.complete', $pet), ['token' => $next->token])->assertSessionHasNoErrors();
    $again = readyDiseaseCare($pet, $group, $variant);
    $this->travel(30)->seconds();
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $again->token);

    $this->assertDatabaseHas('pet_disease_counters', ['id' => $counter->id, 'action_count' => $threshold + 1]);
    $this->assertDatabaseCount('pet_diseases', 1);
    $this->assertDatabaseHas('pet_diseases', ['pet_id' => $pet->id, 'disease_id' => $disease->id, 'ended_at' => null]);
    expect($pet->fresh()->debuffs)->toHaveCount(1);
    expect($pet->fresh()->debuffs[0])->toMatchArray(['code' => 'disease:'.$code, 'expires_at' => null, 'recovery_actions' => []]);
})->with([
    'meals' => ['digestive_upset', 'feed', 'meal', 10],
    'upper meal threshold' => ['digestive_upset', 'feed', 'meal', 16],
    'washing' => ['skin_irritation', 'groom', 'wash', 6],
    'care products' => ['skin_irritation', 'groom', 'care', 10],
    'outdoor walks' => ['sore_paws', 'walk', 'walk', 7],
    'training variants' => ['muscle_strain', 'training', 'training:sprint', 5],
    'play variants' => ['exhaustion', 'play', 'toy', 12],
    'play without equipment' => ['exhaustion', 'play', 'attention', 18],
]);

test('daily thresholds include both range boundaries and survive repeated reads', function (int $draw, int $threshold) {
    $this->freezeSecond();
    $this->app->instance(Randomizer::class, diseaseRandom($draw));
    $this->seed(DiseaseSeeder::class);
    $pet = Pet::factory()->create();
    $care = readyDiseaseCare($pet, 'feed', 'meal');
    $this->travel(30)->seconds();

    expect(app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token))->toBeTrue();
    app(GetPetCare::class)->handle($pet->user, $pet->id, 'ru');
    app(GetPetCare::class)->handle($pet->user, $pet->id, 'en');
    expect(app(CompletePetCare::class)->handle($pet->user, $pet->id, strtoupper($care->token)))->toBeFalse();

    $this->assertDatabaseCount('pet_disease_counters', 1);
    $this->assertDatabaseHas('pet_disease_counters', ['pet_id' => $pet->id, 'action_count' => 1, 'threshold' => $threshold]);
})->with(['minimum' => [0, 10], 'maximum' => [6, 16]]);

test('water and indoor walks do not count as meals or outdoor walks', function (string $group, string $variant) {
    $this->freezeSecond();
    $this->seed(DiseaseSeeder::class);
    $pet = Pet::factory()->create();
    $care = readyDiseaseCare($pet, $group, $variant);
    $this->travel(30)->seconds();

    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);

    $this->assertDatabaseCount('pet_disease_counters', 0);
    $this->assertDatabaseCount('pet_diseases', 0);
})->with(['water' => ['feed', 'water'], 'indoor walk' => ['walk', 'home']]);

test('dogs belonging to the same player have independent counters', function () {
    $this->freezeSecond();
    $this->app->instance(Randomizer::class, diseaseRandom(0));
    $this->seed(DiseaseSeeder::class);
    $pet = Pet::factory()->create();
    $other = Pet::factory()->for($pet->user)->create();
    $first = readyDiseaseCare($pet, 'feed', 'meal');
    $second = readyDiseaseCare($other, 'feed', 'meal');
    $this->travel(30)->seconds();

    app(CompletePetCare::class)->handle($pet->user, $pet->id, $first->token);
    app(CompletePetCare::class)->handle($pet->user, $other->id, $second->token);

    $this->assertDatabaseCount('pet_disease_counters', 2);
    $this->assertDatabaseHas('pet_disease_counters', ['pet_id' => $pet->id, 'action_count' => 1]);
    $this->assertDatabaseHas('pet_disease_counters', ['pet_id' => $other->id, 'action_count' => 1]);
});

test('counters reset at Moscow midnight and use the action end date for delayed completion', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-03 20:59:00', 'UTC'));
    $this->app->instance(Randomizer::class, diseaseRandom(6));
    $this->seed(DiseaseSeeder::class);
    $pet = Pet::factory()->create();
    $disease = Disease::query()->where('code', 'digestive_upset')->firstOrFail();
    $counter = PetDiseaseCounter::factory()->for($pet)->for($disease)->create(['tracked_on' => '2026-10-03', 'action_count' => 8, 'threshold' => 10]);
    $beforeMidnight = readyDiseaseCare($pet, 'feed', 'meal');
    $this->travel(2)->minutes();
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $beforeMidnight->token);
    $this->assertDatabaseHas('pet_disease_counters', ['id' => $counter->id, 'tracked_on' => '2026-10-03', 'action_count' => 9, 'threshold' => 10]);
    $afterMidnight = readyDiseaseCare($pet, 'feed', 'meal');
    $this->travel(30)->seconds();

    app(CompletePetCare::class)->handle($pet->user, $pet->id, $afterMidnight->token);

    $this->assertDatabaseCount('pet_disease_counters', 1);
    $this->assertDatabaseHas('pet_disease_counters', ['id' => $counter->id, 'tracked_on' => '2026-10-04', 'action_count' => 1, 'threshold' => 16]);
    $this->assertDatabaseCount('pet_diseases', 0);
});

test('failed and premature completion never counts an action or acquires a disease', function () {
    $this->freezeSecond();
    $this->seed(DiseaseSeeder::class);
    $pet = Pet::factory()->create();
    $disease = Disease::query()->where('code', 'digestive_upset')->firstOrFail();
    $counter = PetDiseaseCounter::factory()->for($pet)->for($disease)->create([
        'tracked_on' => now()->setTimezone('Europe/Moscow')->toDateString(), 'action_count' => 9, 'threshold' => 10,
    ]);
    $care = readyDiseaseCare($pet, 'feed', 'meal');
    expect(fn () => app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token))->toThrow(PetUnavailable::class);
    $this->travel(30)->seconds();
    $this->rejectCareWrites('reject_disease_completion');

    expect(fn () => app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token))->toThrow(QueryException::class);

    $this->assertDatabaseHas('pet_disease_counters', ['id' => $counter->id, 'action_count' => 9]);
    $this->assertDatabaseCount('pet_diseases', 0);
    $this->assertDatabaseHas('pet_care_actions', ['id' => $care->id, 'completed_at' => null]);
    expect($pet->fresh()->debuffs)->toBeNull();
    $this->allowCareWrites('reject_disease_completion');
});

test('disabled diseases are not acquired', function () {
    $this->freezeSecond();
    Disease::factory()->create([
        'acquisition_rules' => ['group' => 'feed', 'variants' => ['meal'], 'daily_min' => 1, 'daily_max' => 1],
        'modifiers' => ['satiety_gain_percent' => -25], 'is_active' => false,
    ]);
    $pet = Pet::factory()->create();
    $care = readyDiseaseCare($pet, 'feed', 'meal');
    $this->travel(30)->seconds();

    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);

    $this->assertDatabaseCount('pet_disease_counters', 0);
    $this->assertDatabaseCount('pet_diseases', 0);
});

test('disease penalties survive sleep and time and reduce the next meal gain', function () {
    $this->freezeSecond();
    $this->seed(DiseaseSeeder::class);
    $pet = Pet::factory()->create(['satiety' => 10, 'satiety_max' => 100, 'size' => 'small', 'energy' => 50]);
    $disease = Disease::query()->where('code', 'digestive_upset')->firstOrFail();
    $episode = PetDisease::factory()->for($pet)->for($disease)->create([
        'effect_snapshot' => [...$disease->snapshot(), 'starts_at' => now()->timestamp],
    ]);
    $sleep = app(StartPetCare::class)->handle($pet->user, $pet->id, 'nap', [], (string) Str::uuid());
    $this->travel(300)->seconds();
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $sleep->token);
    $this->travel(3)->days();
    $food = InventoryItem::factory()->for($pet->user)->for(Item::factory()->for(ItemCategory::factory()->state(['code' => 'food']), 'category'))->create();

    $meal = app(StartPetCare::class)->handle($pet->user, $pet->id, 'meal', ['food' => $food->id], (string) Str::uuid());

    expect($meal->effects['satiety'])->toBe(22.5);
    expect(app(GetPetStatuses::class)->handle($pet->fresh(), now())->debuffs)->toHaveCount(1);
    $this->assertDatabaseHas('pet_diseases', ['id' => $episode->id, 'ended_at' => null]);
    expect($pet->fresh()->debuffs[0]['expires_at'])->toBeNull();
});

test('disease modifiers influence offline decay from their onset', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['mood' => 100, 'mood_max' => 100]);
    $healthy = Pet::factory()->for($pet->user)->create(['mood' => 100, 'mood_max' => 100]);
    $disease = Disease::factory()->create(['modifiers' => ['mood_decay_percent' => 50]]);
    $effect = [...$disease->snapshot(), 'starts_at' => now()->addHour()->timestamp];
    PetDisease::factory()->for($pet)->for($disease)->create(['started_at' => now()->addHour(), 'effect_snapshot' => $effect]);
    $pet->update(['debuffs' => [$effect]]);
    $this->travel(2)->hours();

    $this->actingAs($pet->user)->get(route('dashboard', ['pet' => $pet->id]))->assertInertia(fn (Assert $page) => $page->where('pet.states.mood', 95));
    $this->get(route('dashboard', ['pet' => $healthy->id]))->assertInertia(fn (Assert $page) => $page->where('pet.states.mood', 96));
});

test('the player sees disease effects without counters acquisition rules or causes', function () {
    $this->freezeSecond();
    $this->seed(DiseaseSeeder::class);
    $this->seed(DiseaseSeeder::class);
    $pet = Pet::factory()->create();
    $disease = Disease::query()->where('code', 'digestive_upset')->firstOrFail();
    PetDisease::factory()->for($pet)->for($disease)->create(['effect_snapshot' => $disease->snapshot()]);
    PetDiseaseCounter::factory()->for($pet)->for($disease)->create(['action_count' => 10, 'threshold' => 10]);

    $data = app(GetPetCare::class)->handle($pet->user, $pet->id, 'ru');

    $this->assertDatabaseCount('diseases', 5);
    expect($data['debuffs'][0]['name']['ru'])->toBe('Расстройство желудка');
    expect($data['debuffs'][0])->not->toHaveKeys(['acquisition_rules', 'threshold', 'action_count', 'variants', 'daily_min', 'daily_max']);
    expect($data['recentIncidents'])->toBe([]);
    expect($data)->not->toHaveKeys(['diseases', 'diseaseCounters', 'disease_counters']);
});
