<?php

use App\Models\CurrencyTransaction;
use App\Models\DogWorkShift;
use App\Models\GameEvent;
use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\User;
use App\Modules\Pets\Actions\RegisterGameEvent;
use App\Modules\Pets\Calculators\PetBoardingRules;
use App\Modules\Pets\Enums\PetActivity;
use App\Modules\Pets\Enums\PetBoardingPlan;
use App\Modules\Pets\Services\GameEventProcessor;
use App\Modules\Pets\Services\PetLifecycle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00:00', 'UTC'));
});

test('boarding protects a living dog before a legacy care receipt finishes after the idle hour', function () {
    $user = User::factory()->create(['coins' => 10, 'boarding_plan' => 'cheap', 'boarding_enabled_at' => now(), 'last_seen_at' => now()]);
    $token = (string) Str::uuid();
    $pet = Pet::factory()->create([
        'user_id' => $user->id, 'health' => 6, 'health_max' => 100,
        'satiety' => 0, 'hydration' => 0, 'activity' => PetActivity::Sleep,
        'activity_token' => $token, 'activity_started_at' => now(), 'activity_ends_at' => now()->addMinutes(90),
    ]);
    $care = PetCareAction::factory()->create([
        'pet_id' => $pet->id, 'user_id' => $user->id, 'activity_token' => $token,
        'group' => 'sleep', 'variant' => 'sleep', 'effects' => ['health' => 4],
        'ends_at' => now()->addMinutes(90), 'available_at' => now()->addMinutes(90),
    ]);
    $this->travel(2)->hours();

    app(PetLifecycle::class)->synchronizeOwner($user);

    expect($pet->fresh()->died_at)->toBeNull();
    expect($pet->fresh()->health)->toBe(5.0);
    expect($care->fresh()->completed_at)->not->toBeNull();
    expect($care->fresh()->cancelled_at)->toBeNull();
    expect($user->fresh()->coins)->toBe(8);
    expect((int) CurrencyTransaction::query()->where('reason', 'pet_boarding')->sum('amount'))->toBe(-2);
});

test('a paid boarding hour preserves an earlier healing receipt when boarding has been disabled', function () {
    $user = User::factory()->create(['coins' => 10, 'boarding_plan' => 'off', 'last_seen_at' => now()]);
    $token = (string) Str::uuid();
    $pet = Pet::factory()->create([
        'user_id' => $user->id, 'health' => 0.1, 'health_max' => 100,
        'satiety' => 100, 'satiety_max' => 100, 'hydration' => 100, 'hydration_max' => 100,
        'activity' => PetActivity::Sleep, 'activity_token' => $token,
        'activity_started_at' => now(), 'activity_ends_at' => now()->addSeconds(15),
    ]);
    $pet->boarding_periods = [app(PetBoardingRules::class)->period(
        PetBoardingPlan::Cheap, now()->subMinutes(59)->getTimestamp(), now()->addMinute()->getTimestamp(),
    )];
    $pet->save();
    $care = PetCareAction::factory()->create([
        'pet_id' => $pet->id, 'user_id' => $user->id, 'activity_token' => $token,
        'group' => 'sleep', 'variant' => 'nap', 'effects' => ['health' => 4],
        'ends_at' => now()->addSeconds(15), 'available_at' => now()->addSeconds(15),
    ]);
    $this->travel(970)->minutes();

    app(PetLifecycle::class)->synchronizeOwner($user);

    expect($pet->fresh()->died_at)->toBeNull();
    expect($pet->fresh()->health)->toEqualWithDelta(3.35, 0.0001);
    expect($care->fresh()->completed_at)->not->toBeNull();
    expect($care->fresh()->cancelled_at)->toBeNull();
    expect($user->fresh()->coins)->toBe(10);
    expect(CurrencyTransaction::query()->where('reason', 'pet_boarding')->exists())->toBeFalse();
});

test('returning on a profile with a pending event records activity and retains the previous boarding bill', function () {
    $user = User::factory()->create(['coins' => 100, 'boarding_plan' => 'cheap', 'boarding_enabled_at' => now(), 'last_seen_at' => now()]);
    $pet = Pet::factory()->create(['user_id' => $user->id, 'energy' => 100]);
    $event = GameEvent::factory()->create([
        'closes_at' => now()->addHour(), 'starts_at' => now()->addMinutes(75), 'ends_at' => now()->addMinutes(85),
    ]);
    app(RegisterGameEvent::class)->handle($user, $event->id, $pet->id,
        ['stages' => ['balanced', 'careful', 'bold']], [], 25, (string) Str::uuid());
    $this->travel(150)->minutes();
    $returnedAt = now();

    $this->actingAs($user)->get(route('profile.edit'))->assertOk();

    expect($user->fresh()->last_seen_at)->toEqual($returnedAt);
    app(GameEventProcessor::class)->processForOwner($user);
    app(PetLifecycle::class)->synchronizeOwner($user);
    expect((int) CurrencyTransaction::query()->where('reason', 'pet_boarding')->sum('amount'))->toBe(-2);
    $this->travel(40)->minutes();
    app(PetLifecycle::class)->synchronizeOwner($user);
    expect((int) CurrencyTransaction::query()->where('reason', 'pet_boarding')->sum('amount'))->toBe(-2);
});

test('a player starting care through a partial Inertia submission counts as active gameplay', function () {
    $user = User::factory()->create(['coins' => 10, 'boarding_plan' => 'cheap', 'boarding_enabled_at' => now(), 'last_seen_at' => now()]);
    $pet = Pet::factory()->create(['user_id' => $user->id]);
    $this->travel(30)->minutes();

    $this->actingAs($user)->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'Dashboard', 'X-Inertia-Partial-Data' => 'pet,care',
    ])->post(route('pets.care.store', $pet), ['variant' => 'water', 'items' => [], 'token' => (string) Str::uuid()])
        ->assertSessionHasNoErrors()->assertRedirect();

    expect($user->fresh()->last_seen_at)->toEqual(now());
    expect($user->fresh()->coins)->toBe(10);
});

test('boarding stops buying later hours when a legacy training receipt kills the dog', function () {
    $user = User::factory()->create(['coins' => 20, 'boarding_plan' => 'cheap', 'boarding_enabled_at' => now(), 'last_seen_at' => now()]);
    $token = (string) Str::uuid();
    $endedAt = now()->addMinutes(90);
    $pet = Pet::factory()->create([
        'user_id' => $user->id, 'health' => 50, 'health_max' => 100,
        'activity' => PetActivity::Training, 'activity_token' => $token,
        'activity_started_at' => now(), 'activity_ends_at' => $endedAt,
    ]);
    $care = PetCareAction::factory()->create([
        'pet_id' => $pet->id, 'user_id' => $user->id, 'activity_token' => $token,
        'group' => 'training', 'variant' => 'training:1', 'effects' => ['health' => -50],
        'ends_at' => $endedAt, 'available_at' => $endedAt,
    ]);
    $this->travel(10)->hours();

    app(PetLifecycle::class)->synchronizeOwner($user);
    app(PetLifecycle::class)->synchronizeOwner($user);

    expect($pet->fresh()->died_at)->toEqual($endedAt);
    expect($pet->fresh()->health)->toBe(0.0);
    expect($care->fresh()->completed_at)->not->toBeNull();
    expect($care->fresh()->cancelled_at)->toBeNull();
    expect($user->fresh()->coins)->toBe(19);
    expect((int) CurrencyTransaction::query()->where('reason', 'pet_boarding')->sum('amount'))->toBe(-1);
});

test('manually collecting a dog work reward records the returning player activity', function () {
    $user = User::factory()->create(['coins' => 10, 'boarding_plan' => 'cheap', 'boarding_enabled_at' => now(), 'last_seen_at' => now()]);
    $token = (string) Str::uuid();
    $pet = Pet::factory()->create([
        'user_id' => $user->id, 'activity' => PetActivity::Work, 'activity_token' => $token,
        'activity_started_at' => now(), 'activity_ends_at' => now()->addMinutes(5),
    ]);
    $shift = DogWorkShift::factory()->create([
        'pet_id' => $pet->id, 'user_id' => $user->id, 'activity_token' => $token,
        'coins_reward' => 150, 'gems_reward' => 0, 'ends_at' => now()->addMinutes(5),
    ]);
    $this->travel(30)->minutes();

    $this->actingAs($user)->post(route('dog-work.complete'), ['token' => $shift->token])
        ->assertSessionHasNoErrors()->assertRedirect();

    expect($user->fresh()->last_seen_at)->toEqual(now());
    expect($user->fresh()->coins)->toBe(160);
    expect($shift->fresh()->completed_at)->not->toBeNull();
});

test('manually retrying finished care records activity without a background request marker', function () {
    $user = User::factory()->create(['coins' => 10, 'boarding_plan' => 'cheap', 'boarding_enabled_at' => now(), 'last_seen_at' => now()]);
    $token = (string) Str::uuid();
    $pet = Pet::factory()->create([
        'user_id' => $user->id, 'activity' => PetActivity::Feed, 'activity_token' => $token,
        'activity_started_at' => now(), 'activity_ends_at' => now()->addSeconds(15),
    ]);
    $care = PetCareAction::factory()->create([
        'pet_id' => $pet->id, 'user_id' => $user->id, 'activity_token' => $token,
        'group' => 'feed', 'variant' => 'water', 'effects' => ['hydration' => 35],
        'ends_at' => now()->addSeconds(15), 'available_at' => now()->addMinutes(5),
    ]);
    $this->travel(30)->minutes();

    $this->actingAs($user)->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'Dashboard', 'X-Inertia-Partial-Data' => 'pet,care',
    ])->post(route('pets.care.complete', $pet), ['token' => $care->token])
        ->assertSessionHasNoErrors()->assertRedirect();

    expect($user->fresh()->last_seen_at)->toEqual(now());
    expect($user->fresh()->coins)->toBe(10);
    expect($care->fresh()->completed_at)->not->toBeNull();
});
