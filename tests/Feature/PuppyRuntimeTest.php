<?php

use App\Models\BreedingLitter;
use App\Models\Pet;
use App\Models\Puppy;
use App\Models\User;
use App\Modules\Pets\Services\PuppyLifecycle;

beforeEach(function () {
    $this->freezeSecond();
});

test('owner synchronization bounds births and expirations and leaves foreign owners for the scheduler', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $born = collect(range(1, 3))->map(function () use ($owner): Puppy {
        $litter = BreedingLitter::factory()->create(['born_at' => now()->subHour()]);

        return Puppy::factory()->for($litter, 'litter')->for($owner)->create();
    });
    $foreignLitter = BreedingLitter::factory()->create(['born_at' => now()->subHour()]);
    $foreign = Puppy::factory()->for($foreignLitter, 'litter')->for($other)->create();
    $expiredLitter = BreedingLitter::factory()->create(['born_at' => now()->subDays(8), 'delivered_at' => now()->subDays(8)]);
    $expired = Puppy::factory()->for($expiredLitter, 'litter')->for($owner)->count(3)->create(['status' => 'pending', 'expires_at' => now()->subDay()]);
    $foreignExpired = Puppy::factory()->for($expiredLitter, 'litter')->for($other)->create(['status' => 'listed', 'expires_at' => now()->subDay(), 'sale_price' => 100]);

    expect(app(PuppyLifecycle::class)->synchronizeForOwner($owner, 2))->toBe(4);

    expect(Puppy::query()->whereIn('id', $born->pluck('id'))->where('status', 'pending')->count())->toBe(2);
    expect(Puppy::query()->whereIn('id', $expired->modelKeys())->where('status', 'kennel')->count())->toBe(2);
    expect($foreign->fresh()->status)->toBe('unborn');
    expect($foreignExpired->fresh()->status)->toBe('listed');
    $this->artisan('puppies:transfer-expired')->assertSuccessful();
    expect(Puppy::query()->whereIn('id', $born->pluck('id'))->where('status', 'pending')->count())->toBe(3);
    expect($foreign->fresh()->status)->toBe('pending');
    expect($foreignExpired->fresh()->status)->toBe('kennel');
    expect(Puppy::query()->whereIn('id', $expired->modelKeys())->where('status', 'kennel')->count())->toBe(3);
});

test('own puppy and breeding pages synchronize only a bounded owner batch', function (string $route) {
    config(['doglive.puppy_http_batch_size' => 1]);
    $owner = User::factory()->create();
    $parent = Pet::factory()->for($owner)->create();
    $litters = collect(range(1, 2))->map(fn () => BreedingLitter::factory()->create(['own_pet_id' => $parent->id, 'born_at' => now()->subHour()]));
    foreach ($litters as $litter) {
        Puppy::factory()->for($litter, 'litter')->for($owner)->create();
    }
    $foreign = Puppy::factory()->for(BreedingLitter::factory()->create(['born_at' => now()->subHour()]), 'litter')->create();

    $this->actingAs($owner)->get(route($route))->assertOk();

    expect(BreedingLitter::query()->whereIn('id', $litters->pluck('id'))->whereNotNull('delivered_at')->count())->toBe(1);
    expect($foreign->fresh()->status)->toBe('unborn');
})->with(['puppies.index', 'breeding.index']);

test('reading the market does not deliver or transfer any global puppy backlog', function () {
    $viewer = User::factory()->create();
    $foreignLitter = BreedingLitter::factory()->create(['born_at' => now()->subHour()]);
    $unborn = Puppy::factory()->for($foreignLitter, 'litter')->create();
    $expired = Puppy::factory()->create(['status' => 'listed', 'expires_at' => now()->subHour(), 'sale_price' => 100]);

    $this->actingAs($viewer)->get(route('puppies.market'))->assertOk();

    expect($foreignLitter->fresh()->delivered_at)->toBeNull();
    expect($unborn->fresh()->status)->toBe('unborn');
    expect($expired->fresh()->status)->toBe('listed');
});
