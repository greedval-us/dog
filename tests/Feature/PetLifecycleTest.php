<?php

use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\PetHistoryEntry;
use App\Models\User;
use App\Modules\Pets\Actions\RetirePet;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\DTO\PetProfileData;
use App\Modules\Pets\Enums\PetActivity;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\GetPetSlots;
use App\Modules\Pets\Services\PetLifecycle;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-10-03 12:00:00', 'UTC'));
});

test('retirement becomes available at three calendar months including short month boundaries', function () {
    $this->travelTo(CarbonImmutable::parse('2026-04-30 12:00:00', 'UTC'));
    $pet = Pet::factory()->create(['born_at' => '2026-01-31 12:00:00']);
    $eligibleAt = CarbonImmutable::parse('2026-04-30 12:00:00', 'UTC');

    expect($pet->retirementEligibleAt())->toEqual($eligibleAt);
    expect($pet->canRetire($eligibleAt->subSecond()))->toBeFalse();
    expect($pet->canRetire($eligibleAt))->toBeTrue();
    expect(PetProfileData::fromModel($pet->fresh(), $pet->dog, 'ru')->toArray()['lifecycle'])->toMatchArray([
        'status' => 'active', 'canRetire' => true,
        'retirementEligibleAt' => $eligibleAt->toIso8601String(),
        'automaticRetirementAt' => '2026-07-31T12:00:00+00:00',
    ]);
});

test('an owner cannot retire a dog before three months', function () {
    $pet = Pet::factory()->create(['born_at' => now()->subMonthsNoOverflow(3)->addSecond()]);

    expect(fn () => app(RetirePet::class)->handle($pet->user, $pet->id))
        ->toThrow(PetUnavailable::class, 'Retirement is available three months after birth.');

    expect($pet->fresh()->retired_at)->toBeNull();
    expect($pet->fresh()->died_at)->toBeNull();
});

test('manual retirement frees a slot cancels pending care and preserves the complete snapshot permanently', function () {
    $pet = Pet::factory()->create(['born_at' => now()->subMonthsNoOverflow(3), 'speed' => 45, 'satiety' => 65]);
    $care = PetCareAction::factory()->create(['pet_id' => $pet->id]);
    $pet->update(['activity' => PetActivity::Play, 'activity_token' => $care->activity_token,
        'activity_started_at' => now(), 'activity_ends_at' => $care->ends_at]);

    $this->actingAs($pet->user)->post(route('pets.retire', $pet->id))
        ->assertSessionHasNoErrors()->assertRedirect(route('players.memorial.show', ['user' => $pet->user->username, 'pet' => $pet->id]));

    expect($pet->fresh()->retired_at)->toEqual(now());
    expect($care->fresh()->cancelled_at)->toEqual(now());
    expect($pet->fresh()->activity)->toBeNull();
    expect(app(GetPetSlots::class)->handle($pet->user)[0]['pet'])->toBeNull();
    $snapshot = $pet->fresh()->getAttributes();

    $this->travel(60)->days();
    app(PetLifecycle::class)->synchronizeOwner($pet->user);
    app(RetirePet::class)->handle($pet->user, $pet->id);
    $advanced = $pet->fresh();
    $advanced->advanceTo(now(), app(PetDecayCalculator::class));

    expect($advanced->getAttributes())->toBe($snapshot);
    expect($pet->user->fresh()->experience)->toBe('0');
    expect(PetHistoryEntry::query()->where('pet_id', $pet->id)->where('event_code', 'life.retirement')->count())->toBe(1);
});

test('automatic retirement freezes at six months even when discovered much later', function () {
    $deadline = now()->subDays(20);
    $pet = Pet::factory()->create([
        'born_at' => $deadline->subMonthsNoOverflow(6),
        'state_updated_at' => $deadline->subHour(), 'stats_updated_at' => $deadline->subHour(),
        'health' => 100, 'satiety' => 80, 'hydration' => 80, 'speed' => 50,
    ]);
    $expected = clone $pet;
    $expected->advanceTo($deadline, app(PetDecayCalculator::class));

    app(PetLifecycle::class)->synchronizeOwner($pet->user);

    $archived = $pet->fresh();
    expect($archived->retired_at)->toEqual($deadline);
    expect($archived->died_at)->toBeNull();
    expect($archived->state_updated_at)->toEqual($deadline);
    expect($archived->stats_updated_at)->toEqual($deadline);
    foreach (['health', 'satiety', 'hydration', 'energy', 'speed'] as $attribute) {
        expect($archived->getAttribute($attribute))->toBe($expected->getAttribute($attribute));
    }
    expect(app(GetPetSlots::class)->handle($pet->user)[0]['pet'])->toBeNull();
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
    expect(app(GetPetSlots::class)->handle($pet->user)[0]['pet'])->toBeNull();
});

test('death before the automatic retirement deadline takes precedence', function () {
    $started = now()->subHours(5);
    $deadline = $started->addHours(3);
    $pet = Pet::factory()->create(['born_at' => $deadline->subMonthsNoOverflow(6),
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

test('the lifecycle command retires offline dogs and freezes deaths idempotently', function () {
    $retired = Pet::factory()->create(['born_at' => now()->subMonthsNoOverflow(6)]);
    $dead = Pet::factory()->create(['health' => 0]);
    $active = Pet::factory()->create();

    $this->artisan('pets:sync-lifecycle')->assertSuccessful();
    $this->artisan('pets:sync-lifecycle')->assertSuccessful();

    expect($retired->fresh()->retired_at)->toEqual(now());
    expect($dead->fresh()->died_at)->toEqual(now());
    expect($active->fresh()->isActive())->toBeTrue();
    expect(PetHistoryEntry::query()->count())->toBe(2);
});

test('a dashboard link to a retired dog opens its read-only archive instead of game actions', function () {
    $pet = Pet::factory()->retired()->create();

    $this->actingAs($pet->user)->get(route('dashboard', ['pet' => $pet->id]))
        ->assertRedirect(route('players.memorial.show', ['user' => $pet->user->username, 'pet' => $pet->id]));
});

test('a dashboard visit discovers death and immediately frees the slot', function () {
    $pet = Pet::factory()->create(['health' => 0]);

    $this->actingAs($pet->user)->get(route('dashboard'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('pet', null)->where('slots.0.pet', null));

    expect($pet->fresh()->died_at)->not->toBeNull();
});
