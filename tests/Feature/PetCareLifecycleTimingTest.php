<?php

use App\Models\Pet;
use App\Models\PetHistoryEntry;
use App\Models\PetHistoryEvent;
use App\Modules\Pets\Actions\CompletePetCare;
use App\Modules\Pets\Actions\StartPetCare;
use App\Modules\Pets\Services\PetLifecycle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-10-03 12:00:00', 'UTC'));
});

test('late confirmation of finished water prevents a death that would occur without its earlier effect', function () {
    $pet = Pet::factory()->create([
        'health' => 5, 'health_max' => 100, 'satiety' => 80, 'satiety_max' => 100,
        'hydration' => 0, 'hydration_max' => 100,
    ]);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'water', [], (string) Str::uuid());
    $this->travel(1)->hours();

    $this->actingAs($pet->user)->post(route('pets.care.complete', $pet), ['token' => $care->token])
        ->assertSessionHasNoErrors();
    $this->post(route('pets.care.complete', $pet), ['token' => $care->token])->assertSessionHasNoErrors();

    expect($pet->fresh()->died_at)->toBeNull();
    expect($pet->fresh()->retired_at)->toBeNull();
    expect($pet->fresh()->health)->toBe(4.9792);
    expect($care->fresh()->completed_at)->not->toBeNull();
    expect($care->fresh()->cancelled_at)->toBeNull();
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'experience' => '10']);
    $this->get(route('dashboard', ['pet' => $pet->id]))->assertInertia(fn (Assert $page) => $page
        ->where('pet.states.hydration', 30)->where('pet.lifecycle.status', 'active')
    );
});

test('a dashboard visit and repeated lifecycle synchronization honor finished water before declaring an offline death', function () {
    $pet = Pet::factory()->create([
        'health' => 5, 'health_max' => 100, 'satiety' => 80, 'satiety_max' => 100,
        'hydration' => 0, 'hydration_max' => 100,
    ]);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'water', [], (string) Str::uuid());
    $this->travel(1)->hours();

    $this->actingAs($pet->user)->get(route('dashboard', ['pet' => $pet->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pet.id', $pet->id)->where('pet.lifecycle.status', 'active')
            ->where('pet.states.hydration', 30)
        );
    app(PetLifecycle::class)->synchronizeOwner($pet->user);
    app(PetLifecycle::class)->synchronizeOwner($pet->user);

    expect($pet->fresh()->died_at)->toBeNull();
    expect($pet->fresh()->health)->toBe(4.9792);
    expect($care->fresh()->completed_at)->not->toBeNull();
    expect($care->fresh()->experience_awarded)->toBe(10);
    expect($care->fresh()->cancelled_at)->toBeNull();
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'experience' => '10', 'pet_statistics' => json_encode(['care.water' => 1])]);
});

test('death before a nap finishes cancels its later health recovery without reviving the dog or awarding experience', function () {
    $started = now();
    $pet = Pet::factory()->create([
        'health' => 0.1, 'health_max' => 100, 'satiety' => 0, 'satiety_max' => 100,
        'hydration' => 0, 'hydration_max' => 100, 'energy' => 0, 'energy_max' => 100,
    ]);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'nap', [], (string) Str::uuid());
    $this->travel(10)->minutes();

    app(PetLifecycle::class)->synchronizeOwner($pet->user);
    expect(app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token))->toBeFalse();

    expect($pet->fresh()->died_at)->toEqual($started->addSeconds(72));
    expect($care->fresh()->cancelled_at)->toEqual($started->addSeconds(72));
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'health' => 0, 'energy' => 0, 'activity' => null]);
    $this->assertDatabaseHas('pet_care_actions', ['id' => $care->id, 'completed_at' => null, 'experience_awarded' => null]);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'experience' => '0']);
});

test('care that finishes before a later death applies and awards once before freezing at the actual death time', function () {
    $started = now();
    $pet = Pet::factory()->create([
        'health' => 5, 'health_max' => 100, 'satiety' => 0, 'satiety_max' => 100,
        'hydration' => 0, 'hydration_max' => 100, 'speed' => 50,
    ]);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'water', [], (string) Str::uuid());
    PetHistoryEvent::factory()->create(['code' => 'care.water']);
    $this->travel(2)->hours();

    app(PetLifecycle::class)->synchronizeOwner($pet->user);

    $archived = $pet->fresh();
    expect($archived->died_at)->toEqual($started->addHour());
    expect($archived->state_updated_at)->toEqual($started->addHour());
    expect($archived->stats_updated_at)->toEqual($started->addHour());
    expect($archived->health)->toBe(0.0);
    expect($archived->hydration)->toBe(30.0208);
    expect($care->fresh()->completed_at)->not->toBeNull();
    expect($care->fresh()->cancelled_at)->toBeNull();
    expect($care->fresh()->experience_awarded)->toBe(10);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'experience' => '10']);
    $this->assertDatabaseHas('pet_history_entries', ['pet_id' => $pet->id, 'source_key' => 'care:'.$care->id.':completed',
        'occurred_at' => $care->ends_at->toDateTimeString()]);
    $snapshot = $archived->getAttributes();

    $this->travel(60)->days();
    app(PetLifecycle::class)->synchronizeOwner($pet->user);
    expect(app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token))->toBeFalse();
    expect($pet->fresh()->getAttributes())->toBe($snapshot);
    expect($pet->user->fresh()->experience)->toBe('10');
    expect($pet->user->fresh()->pet_statistics)->toBe(['care.water' => 1]);
    expect(PetHistoryEntry::query()->where('source_key', 'care:'.$care->id.':completed')->count())->toBe(1);
    expect(PetHistoryEntry::query()->where('pet_id', $pet->id)->where('event_code', 'life.death')->count())->toBe(1);
});

test('care that finishes before automatic retirement is preserved and awarded once at the six month cutoff', function () {
    $retirementAt = now();
    $this->travelTo($retirementAt->subMinute());
    $pet = Pet::factory()->create([
        'born_at' => '2026-04-03 12:00:00', 'health' => 100, 'health_max' => 100,
        'satiety' => 80, 'satiety_max' => 100, 'hydration' => 25, 'hydration_max' => 100, 'speed' => 50,
    ]);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'water', [], (string) Str::uuid());
    PetHistoryEvent::factory()->create(['code' => 'care.water']);
    $this->travel(2)->minutes();

    app(PetLifecycle::class)->synchronizeOwner($pet->user);

    $archived = $pet->fresh();
    expect($archived->retired_at)->toEqual($retirementAt);
    expect($archived->died_at)->toBeNull();
    expect($archived->state_updated_at)->toEqual($retirementAt);
    expect($archived->stats_updated_at)->toEqual($retirementAt);
    expect($archived->hydration)->toBe(59.9167);
    expect($care->fresh()->completed_at)->not->toBeNull();
    expect($care->fresh()->cancelled_at)->toBeNull();
    expect($care->fresh()->experience_awarded)->toBe(10);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'experience' => '10']);
    $snapshot = $archived->getAttributes();

    $this->travel(60)->days();
    app(PetLifecycle::class)->synchronizeOwner($pet->user);
    expect(app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token))->toBeFalse();
    expect($pet->fresh()->getAttributes())->toBe($snapshot);
    expect($pet->user->fresh()->pet_statistics)->toBe(['care.water' => 1]);
    expect(PetHistoryEntry::query()->where('source_key', 'care:'.$care->id.':completed')->count())->toBe(1);
    expect(PetHistoryEntry::query()->where('pet_id', $pet->id)->where('event_code', 'life.retirement')->count())->toBe(1);
});
