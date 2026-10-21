<?php

use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\PetHistoryEntry;
use App\Models\User;
use App\Modules\Pets\Actions\RetirePet;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\DTO\PetProfileData;
use App\Modules\Pets\Enums\PetActivity;
use App\Modules\Pets\Queries\GetPetSlots;
use App\Modules\Pets\Services\PetLifecycle;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-10-03 12:00:00', 'UTC'));
});

test('voluntary retirement is available from birth and the profile has no automatic deadline', function () {
    $this->travelTo(CarbonImmutable::parse('2026-05-07 12:00:00', 'UTC'));
    $pet = Pet::factory()->create(['born_at' => '2026-01-31 12:00:00']);
    $eligibleAt = CarbonImmutable::parse('2026-01-31 12:00:00', 'UTC');

    expect($pet->retirementEligibleAt())->toEqual($eligibleAt);
    expect($pet->canRetire($eligibleAt->subSecond()))->toBeFalse();
    expect($pet->canRetire($eligibleAt))->toBeTrue();
    expect(PetProfileData::fromModel($pet->fresh(), $pet->dog, 'ru')->toArray()['lifecycle'])->toMatchArray([
        'status' => 'active', 'canRetire' => true,
        'retirementEligibleAt' => $eligibleAt->toIso8601String(),
        'automaticRetirementAt' => null,
    ]);
});

test('an owner can voluntarily retire a young dog without marking death', function () {
    $pet = Pet::factory()->create(['born_at' => now()->subDays(3)]);

    app(RetirePet::class)->handle($pet->user, $pet->id);

    expect($pet->fresh()->retired_at)->toEqual(now());
    expect($pet->fresh()->died_at)->toBeNull();
    expect(PetHistoryEntry::query()->where('pet_id', $pet->id)->where('event_code', 'life.retirement')->sole()->details['automatic'])->toBeFalse();
    expect($pet->user->notifications()->get()->where('data.kind', 'pet_death')->isNotEmpty())->toBeFalse();
});

test('manual retirement preserves the slot and pending care and records retirement once', function () {
    $pet = Pet::factory()->create(['born_at' => now()->subDays(96), 'speed' => 45, 'satiety' => 65]);
    $care = PetCareAction::factory()->create(['pet_id' => $pet->id]);
    $pet->update(['activity' => PetActivity::Play, 'activity_token' => $care->activity_token,
        'activity_started_at' => now(), 'activity_ends_at' => $care->ends_at]);

    $this->actingAs($pet->user)->post(route('pets.retire', $pet->id))
        ->assertSessionHasNoErrors()->assertRedirect(route('dashboard', ['pet' => $pet->id]));

    expect($pet->fresh()->retired_at)->toEqual(now());
    expect($care->fresh()->cancelled_at)->toBeNull();
    expect($pet->fresh()->activity)->toBe(PetActivity::Play);
    expect(app(GetPetSlots::class)->handle($pet->user)[0]['pet']['id'])->toBe($pet->id);
    app(RetirePet::class)->handle($pet->user, $pet->id);
    expect($pet->user->fresh()->experience)->toBe('0');
    expect(PetHistoryEntry::query()->where('pet_id', $pet->id)->where('event_code', 'life.retirement')->count())->toBe(1);
});

test('crossing ten game years does not retire the dog or persist an ordinary care projection', function () {
    $deadline = now()->subHours(2);
    $pet = Pet::factory()->create([
        'born_at' => $deadline->subDays(120),
        'state_updated_at' => $deadline->subHour(), 'stats_updated_at' => $deadline->subHour(),
        'health' => 100, 'satiety' => 80, 'hydration' => 80, 'speed' => 50,
    ]);
    $projected = clone $pet;
    $projected->advanceTo(now(), app(PetDecayCalculator::class));

    app(PetLifecycle::class)->synchronizeOwner($pet->user);

    $active = $pet->fresh();
    expect($active->retired_at)->toBeNull();
    expect($active->died_at)->toBeNull();
    expect($active->state_updated_at)->toEqual($deadline->subHour());
    expect($projected->retired_at)->toBeNull();
    expect($projected->state_updated_at)->toEqual(now());
    expect($projected->stats_updated_at)->toEqual(now());
    expect($projected->satiety)->toBe(65.0);
    expect(PetHistoryEntry::query()->where('event_code', 'life.retirement')->exists())->toBeFalse();
    expect(app(GetPetSlots::class)->handle($pet->user)[0]['pet']['id'])->toBe($pet->id);
});

test('offline death freezes states and attributes at the first second health reaches zero', function () {
    $started = now()->subDay();
    $pet = Pet::factory()->create(['health' => 10, 'satiety' => 0, 'hydration' => 0,
        'speed' => 50, 'state_updated_at' => $started, 'stats_updated_at' => $started,
        'born_at' => $started]);
    $death = $started->addHours(2);

    app(PetLifecycle::class)->synchronizeOwner($pet->user);

    $archived = $pet->fresh();
    expect($archived->died_at)->toEqual($death);
    expect($archived->retired_at)->toBeNull();
    expect($archived->state_updated_at)->toEqual($death);
    expect($archived->stats_updated_at)->toEqual($death);
    expect($archived->health)->toBe(0.0);
    $snapshot = $archived->getAttributes();
    $this->travel(90)->days();
    app(PetLifecycle::class)->synchronizeOwner($pet->user);

    expect($pet->fresh()->getAttributes())->toBe($snapshot);
    expect(PetHistoryEntry::query()->where('pet_id', $pet->id)->where('event_code', 'life.death')->count())->toBe(1);
    $notification = $pet->user->notifications()->get()->where('data.kind', 'pet_death')->sole();
    expect($notification->data['message']['en'])->toBe($pet->name.' has passed away. Their memory is preserved in your memorial.');
    expect($notification->data['actionUrl'])->toBe(route('players.memorial.show', ['user' => $pet->user->username, 'pet' => $pet->id], absolute: false));
    expect(app(GetPetSlots::class)->handle($pet->user)[0]['pet'])->toBeNull();
});

test('death before ten game years never records retirement', function () {
    $started = now()->subHours(5);
    $deadline = $started->addHours(3);
    $pet = Pet::factory()->create(['born_at' => $deadline->subDays(120),
        'state_updated_at' => $started, 'stats_updated_at' => $started,
        'health' => 5, 'satiety' => 0, 'hydration' => 0]);

    app(PetLifecycle::class)->synchronizeOwner($pet->user);

    expect($pet->fresh()->died_at)->toEqual($started->addHour());
    expect($pet->fresh()->retired_at)->toBeNull();
});

test('zero health cannot be converted to retirement or revived by submitting retirement', function () {
    $pet = Pet::factory()->create(['health' => 0, 'born_at' => now()->subMonthsNoOverflow(3)]);

    $this->actingAs($pet->user)->post(route('pets.retire', $pet->id))->assertSessionHasErrors('retirement');

    expect($pet->fresh()->died_at)->toEqual(now());
    expect($pet->fresh()->retired_at)->toBeNull();
    expect($pet->fresh()->health)->toBe(0.0);
});

test('retirement does not allow managing another players dog', function () {
    $pet = Pet::factory()->create(['born_at' => now()->subMonthsNoOverflow(3)]);

    $this->actingAs(User::factory()->create())->post(route('pets.retire', $pet->id))->assertNotFound();

    expect($pet->fresh()->retired_at)->toBeNull();
});

test('guests cannot retire a dog', function () {
    $pet = Pet::factory()->create(['born_at' => now()->subMonthsNoOverflow(3)]);

    $this->post(route('pets.retire', $pet->id))->assertRedirect(route('login'));

    expect($pet->fresh()->retired_at)->toBeNull();
});

test('a blocked player cannot submit voluntary retirement', function () {
    $owner = User::factory()->create(['status' => 'blocked']);
    $pet = Pet::factory()->for($owner)->create(['born_at' => now()->subMonthsNoOverflow(3)]);

    $this->actingAs($owner)->post(route('pets.retire', $pet->id))->assertSessionHasErrors('retirement');

    expect($pet->fresh()->retired_at)->toBeNull();
});

test('the lifecycle command leaves senior careers open and freezes deaths idempotently', function () {
    $retired = Pet::factory()->create(['born_at' => now()->subDays(120)]);
    $dead = Pet::factory()->create(['health' => 0]);
    $active = Pet::factory()->create();

    $this->artisan('pets:sync-lifecycle')->assertSuccessful();
    $this->artisan('pets:sync-lifecycle')->assertSuccessful();

    expect($retired->fresh()->retired_at)->toBeNull();
    expect($dead->fresh()->died_at)->toEqual(now());
    expect($active->fresh()->isActive())->toBeTrue();
    expect(PetHistoryEntry::query()->count())->toBe(1);
});

test('a dashboard link to a living retired dog opens care actions', function () {
    $pet = Pet::factory()->retired()->create();

    $this->actingAs($pet->user)->get(route('dashboard', ['pet' => $pet->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('pet.id', $pet->id)->where('pet.lifecycle.status', 'retired'));
});

test('a dashboard visit discovers death and immediately frees the slot', function () {
    $pet = Pet::factory()->create(['health' => 0]);

    $this->actingAs($pet->user)->get(route('dashboard'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('pet', null)->where('slots.0.pet', null));

    expect($pet->fresh()->died_at)->not->toBeNull();
});
