<?php

use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Enums\PetActivity;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Services\PetActivityManager;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

test('new pets have separate calculation clocks and no gameplay activity', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-25 12:00:00'));
    $pet = Pet::factory()->create()->refresh();

    expect($pet->state_updated_at->toDateTimeString())->toBe('2026-09-25 12:00:00');
    expect($pet->stats_updated_at->toDateTimeString())->toBe('2026-09-25 12:00:00');
    expect($pet->last_activity_at)->toBeNull();
    expect($pet->isBusy())->toBeFalse();
});

test('starting a timed activity claims only the owners pet and preserves decay clocks', function (PetActivity $activity) {
    $this->travelTo(CarbonImmutable::parse('2026-09-25 12:00:00'));
    $pet = Pet::factory()->create([
        'energy' => 100,
        'state_updated_at' => '2026-09-25 11:00:00',
        'stats_updated_at' => '2026-09-24 12:00:00',
    ]);

    $started = app(PetActivityManager::class)->start($pet->user, $pet->id, $activity, now()->addHour(), energyCost: 10);

    $this->assertDatabaseHas('pets', [
        'id' => $pet->id, 'activity' => $activity->value, 'activity_token' => $started->token,
        'activity_started_at' => '2026-09-25 12:00:00', 'activity_ends_at' => '2026-09-25 13:00:00',
        'last_activity_at' => '2026-09-25 12:00:00',
        'energy' => 90,
        'state_updated_at' => '2026-09-25 11:00:00', 'stats_updated_at' => '2026-09-24 12:00:00',
    ]);
    expect($pet->refresh()->activity)->toBe($activity);
    expect($pet->isBusy())->toBeTrue();
    expect($pet->newQuery()->availableForActivity()->whereKey($pet->id)->exists())->toBeFalse();
})->with(PetActivity::cases());

test('a second activity cannot overwrite an unfinished activity even after its timer expires', function (int $minutes) {
    $this->travelTo(CarbonImmutable::parse('2026-09-25 12:00:00'));
    $pet = Pet::factory()->create(['energy' => 100]);
    $manager = app(PetActivityManager::class);
    $started = $manager->start($pet->user, $pet->id, PetActivity::Training, now()->addHour(), energyCost: 10);
    $this->travel($minutes)->minutes();

    expect(fn () => $manager->start($pet->user, $pet->id, PetActivity::Walk, now()->addHour(), energyCost: 20))
        ->toThrow(PetUnavailable::class);

    expect($pet->refresh()->isBusy())->toBeTrue();
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'activity' => 'training', 'activity_token' => $started->token, 'energy' => 90]);
})->with(['running' => 30, 'awaiting completion' => 90]);

test('players cannot claim another players pet', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['energy' => 100]);
    $other = User::factory()->create();

    expect(fn () => app(PetActivityManager::class)->start($other, $pet->id, PetActivity::Training, now()->addHour(), energyCost: 10))
        ->toThrow(PetUnavailable::class);

    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'activity' => null, 'last_activity_at' => null, 'energy' => 100]);
});

test('activity durations must reach a future stored second', function (int $seconds) {
    $this->freezeSecond();
    $pet = Pet::factory()->create();

    expect(fn () => app(PetActivityManager::class)->start($pet->user, $pet->id, PetActivity::Training, now()->addSeconds($seconds), energyCost: 10))
        ->toThrow(InvalidArgumentException::class);

    expect($pet->refresh()->isBusy())->toBeFalse();
})->with(['past' => -1, 'present' => 0]);

test('only a matching due activity can complete once and old tokens cannot finish later activities', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-25 12:00:00'));
    $pet = Pet::factory()->create(['energy' => 100]);
    $manager = app(PetActivityManager::class);
    $first = $manager->start($pet->user, $pet->id, PetActivity::Training, now()->addHour(), energyCost: 10);

    expect($manager->complete($pet->user, $pet->id, $first->token))->toBeFalse();
    expect($pet->refresh()->isBusy())->toBeTrue();
    $this->travelTo(CarbonImmutable::parse('2026-09-25 13:00:00'));
    expect($manager->complete($pet->user, $pet->id, 'wrong-token'))->toBeFalse();
    expect($manager->complete($pet->user, $pet->id, $first->token))->toBeTrue();
    expect($manager->complete($pet->user, $pet->id, $first->token))->toBeFalse();
    $this->assertDatabaseHas('pets', [
        'id' => $pet->id, 'activity' => null, 'activity_token' => null,
        'activity_started_at' => null, 'activity_ends_at' => null, 'last_activity_at' => '2026-09-25 13:00:00',
        'energy' => 90,
    ]);

    $second = $manager->start($pet->user, $pet->id, PetActivity::Walk, now()->addHour(), energyCost: 20);
    $this->travel(1)->hour();
    expect($manager->complete($pet->user, $pet->id, $first->token))->toBeFalse();
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'activity' => 'walk', 'activity_token' => $second->token, 'energy' => 70]);
});

test('another player cannot complete an activity', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create();
    $other = User::factory()->create();
    $manager = app(PetActivityManager::class);
    $started = $manager->start($pet->user, $pet->id, PetActivity::Training, now()->addHour(), energyCost: 10);
    $this->travel(1)->hour();

    expect($manager->complete($other, $pet->id, $started->token))->toBeFalse();

    expect($pet->refresh()->isBusy())->toBeTrue();
});

test('failed gameplay results roll back activity completion with the callers transaction', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['strength' => 10]);
    $manager = app(PetActivityManager::class);
    $started = $manager->start($pet->user, $pet->id, PetActivity::Training, now()->addHour(), energyCost: 10);
    $this->travel(1)->hour();

    expect(fn () => DB::transaction(function () use ($pet, $manager, $started): void {
        expect($manager->complete($pet->user, $pet->id, $started->token))->toBeTrue();
        $pet->increment('strength', 2);
        throw new RuntimeException('Result failed');
    }))->toThrow(RuntimeException::class, 'Result failed');

    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'activity_token' => $started->token, 'strength' => 10]);
    expect($pet->refresh()->isBusy())->toBeTrue();
});

test('an activity cannot start without enough energy', function (float $energy) {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['energy' => $energy]);

    expect(fn () => app(PetActivityManager::class)->start($pet->user, $pet->id, PetActivity::Training, now()->addHour(), energyCost: 10))
        ->toThrow(PetUnavailable::class);

    $this->assertDatabaseHas('pets', [
        'id' => $pet->id, 'energy' => $energy, 'activity' => null, 'activity_token' => null,
        'activity_started_at' => null, 'activity_ends_at' => null, 'last_activity_at' => null,
    ]);
})->with(['empty' => 0.0, 'just below cost' => 9.9999]);

test('starting an activity spends the exact cost including at the energy boundary', function (float $energy, int $cost, float $remaining) {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['energy' => $energy]);

    app(PetActivityManager::class)->start($pet->user, $pet->id, PetActivity::Walk, now()->addHour(), energyCost: $cost);

    expect($pet->refresh()->energy)->toEqualWithDelta($remaining, 0.00001);
    expect($pet->isBusy())->toBeTrue();
})->with([
    'exactly enough' => [10.0, 10, 0.0],
    'fractional remainder' => [10.4321, 10, 0.4321],
    'free activity with no energy' => [0.0, 0, 0.0],
]);

test('retired pets cannot spend energy or start an activity', function () {
    $pet = Pet::factory()->retired()->create(['energy' => 100]);

    expect(fn () => app(PetActivityManager::class)->start($pet->user, $pet->id, PetActivity::Training, now()->addHour(), energyCost: 10))
        ->toThrow(PetUnavailable::class);

    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => 100, 'activity' => null]);
});

test('negative activity costs cannot increase energy', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['energy' => 50]);

    expect(fn () => app(PetActivityManager::class)->start($pet->user, $pet->id, PetActivity::Training, now()->addHour(), energyCost: -10))
        ->toThrow(InvalidArgumentException::class);

    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => 50, 'activity' => null, 'last_activity_at' => null]);
});

test('failed gameplay setup rolls back both the activity and its energy cost', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['energy' => 50]);

    expect(fn () => DB::transaction(function () use ($pet): void {
        app(PetActivityManager::class)->start($pet->user, $pet->id, PetActivity::Training, now()->addHour(), energyCost: 10);
        throw new RuntimeException('Setup failed');
    }))->toThrow(RuntimeException::class, 'Setup failed');

    $this->assertDatabaseHas('pets', [
        'id' => $pet->id, 'energy' => 50, 'activity' => null, 'activity_token' => null,
        'activity_started_at' => null, 'activity_ends_at' => null, 'last_activity_at' => null,
    ]);
});
