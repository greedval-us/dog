<?php

use App\Models\BreedingLitter;
use App\Models\CurrencyTransaction;
use App\Models\Pet;
use App\Models\Puppy;
use App\Models\PuppyPlacement;
use App\Models\User;
use App\Modules\Pets\Actions\KeepPuppy;
use App\Modules\Pets\Actions\PurchasePuppy;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Services\PuppyLifecycle;
use App\Modules\Players\Services\PlayerWallet;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->freezeSecond();
});

/** @param array<string, mixed> $attributes */
function tradePuppy(User $owner, string $status = 'pending', array $attributes = []): Puppy
{
    $litter = BreedingLitter::factory()->create(['born_at' => now()->subHour(), 'delivered_at' => now()->subHour()]);

    return Puppy::factory()->for($litter, 'litter')->for($owner)->create(['status' => $status, ...$attributes]);
}

test('guests cannot view or change puppies', function () {
    $puppy = tradePuppy(User::factory()->create());
    $payload = ['name' => 'Рэй', 'operation_token' => (string) Str::uuid(), 'price' => 123, 'expected_price' => 123];

    $this->get(route('puppies.index'))->assertRedirect(route('login'));
    $this->get(route('puppies.market'))->assertRedirect(route('login'));
    $this->post(route('puppies.keep', $puppy), $payload)->assertRedirect(route('login'));
    $this->post(route('puppies.listing.store', $puppy), $payload)->assertRedirect(route('login'));
    $this->delete(route('puppies.listing.destroy', $puppy))->assertRedirect(route('login'));
    $this->post(route('puppies.surrender', $puppy))->assertRedirect(route('login'));
    $this->post(route('puppies.purchase', $puppy), $payload)->assertRedirect(route('login'));

    $this->assertDatabaseHas('puppies', ['id' => $puppy->id, 'status' => 'pending']);
    $this->assertDatabaseCount('puppy_placements', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('the puppy page shows only the owners puppies with localized breed and coat labels', function (string $locale, string $breed, string $coat) {
    $owner = User::factory()->create(['locale' => $locale]);
    $puppy = tradePuppy($owner);
    $puppy->dog->update(['name' => ['ru' => 'Пудель', 'en' => 'Poodle'], 'coat_colors' => ['black' => ['ru' => 'Чёрный', 'en' => 'Black']]]);
    tradePuppy(User::factory()->create(), 'listed', ['sale_price' => 100]);

    $this->actingAs($owner)->get(route('puppies.index'))->assertInertia(fn (Assert $page) => $page
        ->component('puppies/Index')->has('puppies', 1)->has('pregnancies', 0)
        ->where('parentId', null)->where('puppies.0.id', $puppy->id)
        ->where('puppies.0.breed', $breed)->where('puppies.0.coatLabel', $coat)
        ->where('puppies.0.potentials.speed', 100)
        ->where('puppies.0.price', null)->where('freeSlots', 1)
    );
})->with([['ru', 'Пудель', 'Чёрный'], ['en', 'Poodle', 'Black']]);

test('the unborn result stays hidden until the litter is delivered exactly at its due time', function () {
    $owner = User::factory()->create();
    $litter = BreedingLitter::factory()->create();
    $puppies = Puppy::factory()->for($litter, 'litter')->for($owner)->count(2)->create();
    $this->actingAs($owner)->get(route('puppies.index'))->assertInertia(fn (Assert $page) => $page
        ->has('puppies', 0)->has('pregnancies', 1)->where('pregnancies.0.dueAt', $litter->born_at->toIso8601String()));
    $this->travelTo($litter->born_at->subSecond());
    app(PuppyLifecycle::class)->synchronize();
    $this->assertDatabaseHas('breeding_litters', ['id' => $litter->id, 'delivered_at' => null]);
    $this->travelTo($litter->born_at);

    $this->get(route('puppies.index'))->assertInertia(fn (Assert $page) => $page->has('puppies', 2)->has('pregnancies', 0));

    foreach ($puppies as $puppy) {
        $this->assertDatabaseHas('puppies', ['id' => $puppy->id, 'status' => 'pending', 'user_id' => $owner->id, 'pet_id' => null]);
    }
    expect($litter->fresh()->delivered_at->toISOString())->toBe($litter->born_at->toISOString());
    $this->assertDatabaseCount('puppy_placements', 0);
});

test('puppies can be filtered by an owned parent without leaking another players dog', function () {
    $owner = User::factory()->create();
    $parent = Pet::factory()->for($owner)->create();
    $litter = BreedingLitter::factory()->create(['own_pet_id' => $parent->id, 'father_id' => $parent->id, 'born_at' => now(), 'delivered_at' => now()]);
    $puppy = Puppy::factory()->for($owner)->for($litter, 'litter')->create(['status' => 'pending']);
    tradePuppy($owner);
    $otherParent = Pet::factory()->create();

    $this->actingAs($owner)->get(route('puppies.index', ['parent' => $parent->id]))->assertInertia(fn (Assert $page) => $page
        ->where('parentId', $parent->id)->has('puppies', 1)->where('puppies.0.id', $puppy->id));
    $this->get(route('puppies.index', ['parent' => $otherParent->id]))->assertNotFound();
});

test('the puppy market separates active player listings from kennel puppies', function () {
    $viewer = User::factory()->create();
    $listed = tradePuppy(User::factory()->create(), 'listed', ['sale_price' => 137]);
    $kennel = tradePuppy($viewer, 'kennel', ['user_id' => null, 'expires_at' => now()->subDay()]);
    tradePuppy($viewer);
    tradePuppy(User::factory()->create(['status' => 'blocked']), 'listed', ['sale_price' => 100]);

    $this->actingAs($viewer)->get(route('puppies.market'))->assertInertia(fn (Assert $page) => $page
        ->component('puppies/Market')->where('source', 'players')->has('puppies', 1)
        ->where('puppies.0.id', $listed->id)->where('puppies.0.price', 137)
        ->where('puppies.0.seller.username', $listed->user->username));
    $this->get(route('puppies.market', ['source' => 'kennel']))->assertInertia(fn (Assert $page) => $page
        ->where('source', 'kennel')->has('puppies', 1)->where('puppies.0.id', $kennel->id)
        ->where('puppies.0.price', 500)->where('puppies.0.seller', null));
});

test('keeping a puppy preserves its genetics and ancestry and starts its game lifetime on acquisition', function () {
    $owner = User::factory()->create(['coins' => 317]);
    $puppy = tradePuppy($owner, 'pending', [
        'coat_color' => 'silver', 'generation' => 4, 'endurance_potential' => 135, 'speed_potential' => 127,
        'strength_potential' => 88, 'agility_potential' => 101, 'obedience_potential' => 73, 'intelligence_potential' => 59,
    ]);
    $puppy->father->forceFill(['retired_at' => now()])->save();
    $puppy->mother->forceFill(['died_at' => now(), 'health' => 0])->save();
    $token = (string) Str::uuid();
    $payload = ['name' => '  Рэй  ', 'operation_token' => $token, 'generation' => 999, 'speed_potential' => 9999];

    $this->actingAs($owner)->post(route('puppies.keep', $puppy), $payload)->assertSessionHasNoErrors();
    $placement = PuppyPlacement::query()->sole();
    $this->post(route('puppies.keep', $puppy), [...$payload, 'operation_token' => strtoupper($token)])->assertRedirect(route('dashboard', ['pet' => $placement->pet_id]));

    $this->assertDatabaseHas('pets', [
        'id' => $placement->pet_id, 'user_id' => $owner->id, 'name' => 'Рэй', 'coat_color' => 'silver',
        'father_id' => $puppy->father_id, 'mother_id' => $puppy->mother_id, 'generation' => 4,
        'endurance_potential' => 135, 'speed_potential' => 127, 'strength_potential' => 88,
        'agility_potential' => 101, 'obedience_potential' => 73, 'intelligence_potential' => 59,
        'endurance' => 27, 'speed' => 25, 'strength' => 18, 'agility' => 20, 'obedience' => 15, 'intelligence' => 12,
        'born_at' => now()->toDateTimeString(),
    ]);
    $this->assertDatabaseHas('puppies', ['id' => $puppy->id, 'status' => 'placed', 'pet_id' => $placement->pet_id]);
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'coins' => 317, 'experience' => '0']);
    $this->assertDatabaseCount('puppy_placements', 1);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('only the owner can keep list unlist or surrender a puppy', function (string $routeName, string $method) {
    $owner = User::factory()->create();
    $puppy = tradePuppy($owner);
    $viewer = User::factory()->create();

    $this->actingAs($viewer)->{$method}(route($routeName, $puppy), ['name' => 'Рэй', 'operation_token' => (string) Str::uuid(), 'price' => 100])->assertForbidden();

    $this->assertDatabaseHas('puppies', ['id' => $puppy->id, 'status' => 'pending', 'user_id' => $owner->id]);
    $this->assertDatabaseCount('puppy_placements', 0);
})->with([
    'keep' => ['puppies.keep', 'post'], 'list' => ['puppies.listing.store', 'post'],
    'unlist' => ['puppies.listing.destroy', 'delete'], 'surrender' => ['puppies.surrender', 'post'],
]);

test('a full dog roster refuses keeping a puppy without consuming its decision', function () {
    $owner = User::factory()->create();
    Pet::factory()->for($owner)->create();
    $puppy = tradePuppy($owner);

    $this->actingAs($owner)->post(route('puppies.keep', $puppy), ['name' => 'Рэй', 'operation_token' => (string) Str::uuid()])->assertSessionHasErrors('puppy');

    $this->assertDatabaseHas('puppies', ['id' => $puppy->id, 'status' => 'pending', 'pet_id' => null]);
    $this->assertDatabaseCount('puppy_placements', 0);
});

test('expired owner actions commit the kennel handoff before returning an error', function (string $routeName, string $method) {
    $owner = User::factory()->create();
    $puppy = tradePuppy($owner, 'listed', ['sale_price' => 137, 'expires_at' => now()]);

    $this->actingAs($owner)->{$method}(route($routeName, $puppy), ['name' => 'Рэй', 'operation_token' => (string) Str::uuid(), 'price' => 100])->assertSessionHasErrors('puppy');

    $this->assertDatabaseHas('puppies', ['id' => $puppy->id, 'status' => 'kennel', 'user_id' => null, 'sale_price' => null]);
    $this->assertDatabaseCount('puppy_placements', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with([
    'keep' => ['puppies.keep', 'post'], 'list' => ['puppies.listing.store', 'post'],
    'unlist' => ['puppies.listing.destroy', 'delete'], 'surrender' => ['puppies.surrender', 'post'],
]);

test('player puppy sales transfer the exact price and replay the durable placement once', function () {
    $seller = User::factory()->create(['coins' => 40]);
    $buyer = User::factory()->create(['coins' => 500]);
    $puppy = tradePuppy($seller, 'listed', ['sale_price' => 137]);
    $token = (string) Str::uuid();
    $payload = ['name' => 'Бим', 'expected_price' => 137, 'operation_token' => $token];

    $this->actingAs($buyer)->post(route('puppies.purchase', $puppy), $payload)->assertSessionHasNoErrors();
    $placement = PuppyPlacement::query()->sole();
    $this->post(route('puppies.purchase', $puppy), [...$payload, 'operation_token' => strtoupper($token)])->assertRedirect(route('dashboard', ['pet' => $placement->pet_id]));

    $this->assertDatabaseHas('users', ['id' => $buyer->id, 'coins' => 363, 'experience' => '0']);
    $this->assertDatabaseHas('users', ['id' => $seller->id, 'coins' => 177, 'experience' => '0']);
    $this->assertDatabaseHas('currency_transactions', ['user_id' => $buyer->id, 'amount' => -137, 'reason' => 'puppy_purchase', 'operation_key' => 'puppy-purchase:'.$token]);
    $this->assertDatabaseHas('currency_transactions', ['user_id' => $seller->id, 'amount' => 137, 'reason' => 'puppy_sale', 'operation_key' => 'puppy-purchase:'.$token]);
    $this->assertDatabaseHas('puppy_placements', ['id' => $placement->id, 'seller_id' => $seller->id, 'price' => 137, 'kind' => 'purchase']);
    $this->assertDatabaseHas('puppies', ['id' => $puppy->id, 'status' => 'placed', 'user_id' => $buyer->id]);
    $this->assertDatabaseCount('currency_transactions', 2);
    $this->assertDatabaseCount('puppy_placements', 1);
});

test('a different buyer cannot buy an already placed puppy', function () {
    $seller = User::factory()->create();
    $firstBuyer = User::factory()->create(['coins' => 500]);
    $secondBuyer = User::factory()->create(['coins' => 500]);
    $puppy = tradePuppy($seller, 'listed', ['sale_price' => 137]);
    app(PurchasePuppy::class)->handle($firstBuyer, $puppy->id, 'Бим', 137, (string) Str::uuid());

    $this->actingAs($secondBuyer)->post(route('puppies.purchase', $puppy), ['name' => 'Рэй', 'expected_price' => 137, 'operation_token' => (string) Str::uuid()])->assertSessionHasErrors('puppy');

    $this->assertDatabaseHas('users', ['id' => $secondBuyer->id, 'coins' => 500]);
    $this->assertDatabaseCount('puppy_placements', 1);
    $this->assertDatabaseCount('currency_transactions', 2);
});

test('a changed price or expired player listing cannot charge the buyer', function (bool $expired) {
    $seller = User::factory()->create();
    $buyer = User::factory()->create(['coins' => 500]);
    $puppy = tradePuppy($seller, 'listed', ['sale_price' => 137, 'expires_at' => $expired ? now() : now()->addDay()]);

    $this->actingAs($buyer)->post(route('puppies.purchase', $puppy), ['name' => 'Рэй', 'expected_price' => 100, 'operation_token' => (string) Str::uuid()])->assertSessionHasErrors('puppy');

    $this->assertDatabaseHas('users', ['id' => $buyer->id, 'coins' => 500]);
    $this->assertDatabaseHas('puppies', ['id' => $puppy->id, 'status' => $expired ? 'kennel' : 'listed']);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with(['changed price' => [false], 'deadline reached' => [true]]);

test('level one players can buy a kennel puppy for the configured price without paying a seller', function () {
    $buyer = User::factory()->create(['coins' => 500, 'experience' => '0', 'level' => 1]);
    $puppy = tradePuppy($buyer, 'kennel', ['user_id' => null, 'expires_at' => now()->subDay()]);

    $this->actingAs($buyer)->post(route('puppies.purchase', $puppy), ['name' => 'Рэй', 'expected_price' => 500, 'operation_token' => (string) Str::uuid()])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('users', ['id' => $buyer->id, 'coins' => 0, 'experience' => '0']);
    $this->assertDatabaseHas('puppies', ['id' => $puppy->id, 'status' => 'placed', 'user_id' => $buyer->id]);
    $this->assertDatabaseCount('currency_transactions', 1);
});

test('insufficient funds or a full roster prevents both payment and puppy placement', function (bool $fullRoster) {
    $seller = User::factory()->create();
    $buyer = User::factory()->create(['coins' => $fullRoster ? 500 : 136]);
    $puppy = tradePuppy($seller, 'listed', ['sale_price' => 137]);
    if ($fullRoster) {
        Pet::factory()->for($buyer)->create();
    }

    $this->actingAs($buyer)->post(route('puppies.purchase', $puppy), ['name' => 'Рэй', 'expected_price' => 137, 'operation_token' => (string) Str::uuid()])->assertSessionHasErrors('puppy');

    $this->assertDatabaseHas('puppies', ['id' => $puppy->id, 'status' => 'listed', 'user_id' => $seller->id]);
    $this->assertDatabaseCount('currency_transactions', 0);
    $this->assertDatabaseCount('puppy_placements', 0);
})->with(['insufficient funds' => [false], 'full roster' => [true]]);

test('listing can be cancelled and surrender gives the kennel custody without a payout', function () {
    $owner = User::factory()->create(['coins' => 317]);
    $puppy = tradePuppy($owner);

    $this->actingAs($owner)->post(route('puppies.listing.store', $puppy), ['price' => 1000000])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('puppies', ['id' => $puppy->id, 'status' => 'listed', 'sale_price' => 1000000]);
    $this->delete(route('puppies.listing.destroy', $puppy))->assertSessionHasNoErrors();
    $this->assertDatabaseHas('puppies', ['id' => $puppy->id, 'status' => 'pending', 'sale_price' => null]);
    $this->post(route('puppies.surrender', $puppy))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('puppies', ['id' => $puppy->id, 'status' => 'kennel', 'user_id' => null, 'pet_id' => null]);
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'coins' => 317]);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('a blocked player cannot keep list surrender or purchase a puppy', function (string $routeName) {
    $owner = User::factory()->create(['status' => 'blocked', 'coins' => 1000]);
    $puppy = tradePuppy($owner);
    if ($routeName === 'puppies.purchase') {
        $puppy = tradePuppy(User::factory()->create(), 'listed', ['sale_price' => 137]);
    }

    $this->actingAs($owner)->post(route($routeName, $puppy), ['name' => 'Рэй', 'operation_token' => (string) Str::uuid(), 'price' => 137, 'expected_price' => 137])->assertSessionHasErrors('puppy');

    $this->assertDatabaseCount('currency_transactions', 0);
    $this->assertDatabaseCount('puppy_placements', 0);
})->with(['puppies.keep', 'puppies.listing.store', 'puppies.surrender', 'puppies.purchase']);

test('the maintenance command delivers due system puppies and transfers expired or orphaned decisions', function () {
    $owner = User::factory()->create();
    $litter = BreedingLitter::factory()->create(['born_at' => now(), 'expires_at' => now()->addDays(7)]);
    $unborn = Puppy::factory()->for($litter, 'litter')->create(['user_id' => null]);
    $expired = tradePuppy($owner, 'listed', ['sale_price' => 137, 'expires_at' => now()]);
    $orphan = tradePuppy(User::factory()->create());
    $orphan->user->delete();

    $this->artisan('puppies:transfer-expired')->assertSuccessful();

    foreach ([$unborn, $expired, $orphan] as $puppy) {
        $this->assertDatabaseHas('puppies', ['id' => $puppy->id, 'status' => 'kennel', 'user_id' => null, 'sale_price' => null]);
    }
    expect(app(PuppyLifecycle::class)->synchronize())->toBe(0);
    $this->assertDatabaseCount('puppy_placements', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('a failed seller payout rolls back buyer coins and the entire placement until retry', function () {
    $seller = User::factory()->create(['coins' => 40]);
    $buyer = User::factory()->create(['coins' => 500]);
    $puppy = tradePuppy($seller, 'listed', ['sale_price' => 137]);
    $token = (string) Str::uuid();
    CurrencyTransaction::creating(function (CurrencyTransaction $entry): void {
        if ($entry->reason === 'puppy_sale') {
            throw new RuntimeException('Cannot pay the puppy seller.');
        }
    });

    try {
        expect(fn () => app(PurchasePuppy::class)->handle($buyer, $puppy->id, 'Бим', 137, $token))->toThrow(RuntimeException::class, 'Cannot pay the puppy seller.');
    } finally {
        CurrencyTransaction::flushEventListeners();
    }

    $this->assertDatabaseHas('users', ['id' => $buyer->id, 'coins' => 500]);
    $this->assertDatabaseHas('users', ['id' => $seller->id, 'coins' => 40]);
    $this->assertDatabaseHas('puppies', ['id' => $puppy->id, 'status' => 'listed', 'pet_id' => null]);
    $this->assertDatabaseCount('currency_transactions', 0);
    $this->assertDatabaseCount('puppy_placements', 0);
    app(PurchasePuppy::class)->handle($buyer, $puppy->id, 'Бим', 137, $token);
    $this->assertDatabaseHas('users', ['id' => $buyer->id, 'coins' => 363]);
});

test('a failed placement receipt rolls back the new dog custody and starter marker', function () {
    $owner = User::factory()->create();
    $puppy = tradePuppy($owner);
    PuppyPlacement::creating(fn () => throw new RuntimeException('Cannot save puppy placement.'));

    try {
        expect(fn () => app(KeepPuppy::class)->handle($owner, $puppy->id, 'Рэй', (string) Str::uuid()))->toThrow(RuntimeException::class, 'Cannot save puppy placement.');
    } finally {
        PuppyPlacement::flushEventListeners();
    }

    expect($owner->pets()->count())->toBe(0);
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'starter_pet_claimed_at' => null]);
    $this->assertDatabaseHas('puppies', ['id' => $puppy->id, 'status' => 'pending', 'pet_id' => null]);
    $this->assertDatabaseCount('puppy_placements', 0);
});

test('reusing a placement token with changed contents or a different player is rejected', function () {
    $owner = User::factory()->create();
    $puppy = tradePuppy($owner);
    $token = (string) Str::uuid();
    app(KeepPuppy::class)->handle($owner, $puppy->id, 'Рэй', $token);
    $other = User::factory()->create();
    $otherPuppy = tradePuppy($other);

    expect(fn () => app(KeepPuppy::class)->handle($owner, $puppy->id, 'Другой', $token))->toThrow(PetUnavailable::class);
    expect(fn () => app(KeepPuppy::class)->handle($other, $otherPuppy->id, 'Рэй', $token))->toThrow(PetUnavailable::class);

    $this->assertDatabaseCount('puppy_placements', 1);
    $this->assertDatabaseHas('puppies', ['id' => $otherPuppy->id, 'status' => 'pending', 'pet_id' => null]);
});

test('an unborn puppy cannot be kept or listed before delivery', function (string $routeName) {
    $owner = User::factory()->create();
    $puppy = Puppy::factory()->for($owner)->create();

    $this->actingAs($owner)->post(route($routeName, $puppy), ['name' => 'Рэй', 'operation_token' => (string) Str::uuid(), 'price' => 137])->assertSessionHasErrors('puppy');

    $this->assertDatabaseHas('puppies', ['id' => $puppy->id, 'status' => 'unborn', 'pet_id' => null]);
    $this->assertDatabaseCount('puppy_placements', 0);
})->with(['puppies.keep', 'puppies.listing.store']);

test('invalid sale prices cannot publish a puppy', function (int $price) {
    $owner = User::factory()->create();
    $puppy = tradePuppy($owner);

    $this->actingAs($owner)->post(route('puppies.listing.store', $puppy), ['price' => $price])->assertSessionHasErrors('price');

    $this->assertDatabaseHas('puppies', ['id' => $puppy->id, 'status' => 'pending', 'sale_price' => null]);
})->with(['negative' => [-1], 'zero' => [0], 'over the price limit' => [1000001]]);

test('puppies assigned to a blocked owner are delivered to the kennel', function () {
    $owner = User::factory()->create(['status' => 'blocked']);
    $litter = BreedingLitter::factory()->create(['born_at' => now()]);
    $puppy = Puppy::factory()->for($litter, 'litter')->for($owner)->create();

    app(PuppyLifecycle::class)->synchronize();

    $this->assertDatabaseHas('puppies', ['id' => $puppy->id, 'status' => 'kennel', 'user_id' => null]);
    $this->assertDatabaseHas('breeding_litters', ['id' => $litter->id, 'delivered_at' => now()->toDateTimeString()]);
});

test('a payment without its placement receipt cannot be reused to issue a puppy', function (string $side, int $buyerCoins, int $sellerCoins) {
    $buyer = User::factory()->create(['coins' => 500]);
    $seller = User::factory()->create();
    $puppy = tradePuppy($seller, 'listed', ['sale_price' => 137]);
    $token = (string) Str::uuid();
    app(PlayerWallet::class)->change($side === 'buyer' ? $buyer : $seller, 'coins', $side === 'buyer' ? -137 : 137,
        'puppy-purchase:'.strtoupper($token), $side === 'buyer' ? 'puppy_purchase' : 'puppy_sale');

    expect(fn () => app(PurchasePuppy::class)->handle($buyer, $puppy->id, 'Рэй', 137, $token))->toThrow(PetUnavailable::class);

    $this->assertDatabaseHas('users', ['id' => $buyer->id, 'coins' => $buyerCoins]);
    $this->assertDatabaseHas('users', ['id' => $seller->id, 'coins' => $sellerCoins]);
    $this->assertDatabaseHas('puppies', ['id' => $puppy->id, 'status' => 'listed', 'pet_id' => null]);
    $this->assertDatabaseCount('puppy_placements', 0);
    $this->assertDatabaseCount('currency_transactions', 1);
})->with(['buyer ledger' => ['buyer', 363, 0], 'seller ledger' => ['seller', 500, 137]]);

test('a deadline reached while acquiring the puppy lock still commits the handoff before refusing', function () {
    $owner = User::factory()->create();
    $puppy = tradePuppy($owner, 'pending', ['expires_at' => now()->addSecond()]);
    $advanced = false;
    Puppy::retrieved(function (Puppy $loaded) use ($puppy, &$advanced): void {
        if ($loaded->id === $puppy->id && ! $advanced) {
            $advanced = true;
            $this->travel(1)->seconds();
        }
    });

    try {
        expect(fn () => app(KeepPuppy::class)->handle($owner, $puppy->id, 'Рэй', (string) Str::uuid()))->toThrow(PetUnavailable::class);
    } finally {
        Puppy::flushEventListeners();
    }

    $this->assertDatabaseHas('puppies', ['id' => $puppy->id, 'status' => 'kennel', 'user_id' => null]);
    $this->assertDatabaseCount('puppy_placements', 0);
});
