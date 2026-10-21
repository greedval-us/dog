<?php

use App\Models\Pet;
use App\Modules\Pets\Actions\StartPetCare;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\DTO\PetProfileData;
use App\Modules\Pets\Queries\GetPetSlots;
use App\Modules\Pets\Services\PetLifecycle;
use Illuminate\Support\Str;

test('a living retired dog keeps its slot and continues losing needs', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->retired()->create(['born_at' => now()->subDays(120)]);

    $snapshot = clone $pet;
    $snapshot->advanceTo(now()->addHour(), app(PetDecayCalculator::class));

    expect($snapshot->satiety)->toBe(95.0);
    expect($snapshot->isActive())->toBeTrue();
    expect($snapshot->archivedAt())->toBeNull();
    expect(app(GetPetSlots::class)->handle($pet->user)[0]['pet']['id'])->toBe($pet->id);
    expect($pet->user->canClaimStarterPet())->toBeFalse();
});

test('a ten year old dog remains a senior with an open career until the owner retires it', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['born_at' => now()->subDays(120)]);

    app(PetLifecycle::class)->synchronizeOwner($pet->user);

    expect($pet->fresh()->retired_at)->toBeNull();
    expect($pet->fresh()->isActive())->toBeTrue();
    expect($pet->fresh()->archivedAt())->toBeNull();
    expect(PetProfileData::fromModel($pet->fresh(), $pet->dog, 'en')->toArray()['age']['stage'])->toBe('senior');
});

test('natural death happens at the saved lifespan even with paid care', function () {
    $this->freezeSecond();
    $death = now()->addHour();
    $pet = Pet::factory()->create(['born_at' => $death->subDays(144), 'lifespan_months' => 144]);
    $pet->forceFill(['boarding_periods' => [[
        'starts_at' => now()->getTimestamp(), 'ends_at' => now()->addDay()->getTimestamp(), 'plan' => 'cheap',
        'state_rates' => ['satiety' => 0, 'hydration' => 0], 'stat_rates' => [],
    ]]])->save();

    $pet->advanceTo(now()->addHours(2), app(PetDecayCalculator::class));

    expect($pet->died_at)->toEqual($death);
    expect($pet->health)->toBe(0.0);
    expect($pet->state_updated_at)->toEqual($death);
    expect($pet->stats_updated_at)->toEqual($death);
});

test('a dog surrendered to shelter no longer occupies a slot', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['surrendered_at' => now()]);

    expect($pet->isActive())->toBeFalse();
    expect($pet->archivedAt())->toEqual(now());
    expect($pet->user->canClaimStarterPet())->toBeTrue();
});

test('profile shows game age without revealing the natural death deadline', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['born_at' => now()->subDays(27)]);
    $pet->refresh();
    $pet->load(['dog', 'characterTraits']);

    $data = PetProfileData::fromModel($pet, $pet->dog, 'en')->toArray();

    expect($data['age'])->toMatchArray(['months' => 27, 'years' => 2, 'remainingMonths' => 3]);
    expect($data)->not->toHaveKey('lifespan_months')->not->toHaveKey('naturalDeathAt');
});

test('a retired dog can start ordinary care without losing retirement or occupying another slot', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->retired()->create(['hydration' => 40, 'born_at' => now()->subDays(120)]);

    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'water', [], (string) Str::uuid());

    expect($care->pet_id)->toBe($pet->id);
    expect($pet->fresh()->retired_at)->not->toBeNull();
    expect($pet->fresh()->isActive())->toBeTrue();
});

test('a saved snapshot older than its new lifespan never moves its clocks backward', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->retired()->create(['born_at' => now()->subDays(220), 'lifespan_months' => 144]);

    $pet->advanceTo(now()->addHour(), app(PetDecayCalculator::class));

    expect($pet->died_at)->toEqual(now());
    expect($pet->state_updated_at)->toEqual(now());
    expect($pet->stats_updated_at)->toEqual(now());
});

test('aging reduces energy recovery and accelerates physical decline without changing potential', function () {
    $this->freezeSecond();
    $young = Pet::factory()->create(['energy' => 0, 'speed' => 100, 'speed_potential' => 150, 'born_at' => now()->subDays(18)]);
    $old = Pet::factory()->create(['energy' => 0, 'speed' => 100, 'speed_potential' => 150,
        'born_at' => now()->subDays(144), 'lifespan_months' => 168]);
    $period = ['starts_at' => now()->getTimestamp(), 'ends_at' => now()->addHours(32)->getTimestamp(), 'plan' => 'cheap',
        'state_rates' => ['satiety' => 0, 'hydration' => 0], 'stat_rates' => []];
    $young->boarding_periods = [$period];
    $old->boarding_periods = [$period];
    $youngLater = clone $young;
    $oldLater = clone $old;

    $young->advanceTo(now()->addHour(), app(PetDecayCalculator::class));
    $old->advanceTo(now()->addHour(), app(PetDecayCalculator::class));
    $youngLater->advanceTo(now()->addHours(31), app(PetDecayCalculator::class));
    $oldLater->advanceTo(now()->addHours(31), app(PetDecayCalculator::class));

    expect($old->energy)->toBeLessThan($young->energy);
    expect($oldLater->speed)->toBeLessThan($youngLater->speed);
    expect($oldLater->speed_potential)->toBe(150);
});
