<?php

use App\Models\Dog;
use App\Models\KennelPurchase;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Kennel\Actions\AdoptStarterPet;
use App\Modules\Kennel\Actions\PurchaseKennelPet;
use App\Modules\Kennel\DTO\AdoptStarterPetData;
use App\Modules\Kennel\DTO\PurchaseKennelPetData;
use App\Modules\Kennel\Exceptions\AdoptionUnavailable;
use App\Modules\Pets\Enums\PetSex;
use App\Modules\Pets\Enums\PetStat;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Players\Services\PlayerWallet;
use Database\Seeders\DogSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Random\Engine;
use Random\Randomizer;

beforeEach(function () {
    $this->withoutVite();
});

test('guests cannot open the kennel or claim a dog', function () {
    $this->get(route('kennel.index'))->assertRedirect(route('login'));
    $this->post(route('kennel.store'), ['dog_id' => 1, 'name' => 'Рэй'])->assertRedirect(route('login'));

    $this->assertDatabaseCount('pets', 0);
});

test('kennel shows only available starter breeds in the player language', function (string $locale, string $name) {
    $this->seed(DogSeeder::class);
    Dog::factory()->create();
    Dog::factory()->create(['is_starter' => true, 'coat_colors' => []]);
    $user = User::factory()->create(['locale' => $locale]);

    $this->actingAs($user)->get(route('kennel.index'))->assertInertia(fn (Assert $page) => $page
        ->component('Kennel')
        ->where('canClaimStarterPet', true)
        ->has('breeds', 3)
        ->where('breeds.0.name', $name)
        ->where('breeds.0.illustration', 'german_shepherd')
        ->where('breeds.0.potentials.endurance', 100)
    );
})->with([['ru', 'Немецкая овчарка'], ['en', 'German Shepherd']]);

test('a player can adopt each starter breed for free with server chosen sex and coat', function (string $breed) {
    $this->seed(DogSeeder::class);
    $dog = Dog::query()->where('breed', $breed)->firstOrFail();
    $user = User::factory()->create(['coins' => 123, 'gems' => 7]);
    $other = User::factory()->create();

    $this->actingAs($user)->post(route('kennel.store'), [
        'dog_id' => $dog->id,
        'name' => '  Рэй  ',
        'user_id' => $other->id,
        'sex' => 'forged',
        'coat_color' => 'forged',
        'generation' => 999,
        'endurance_potential' => 999999,
        'coins' => 999999,
    ])->assertRedirect(route('dashboard'))->assertSessionHasNoErrors();

    $pet = $user->pets()->sole();
    expect($pet->name)->toBe('Рэй')
        ->and($pet->dog_id)->toBe($dog->id)
        ->and($pet->sex)->toBeIn([PetSex::Male, PetSex::Female])
        ->and($pet->coat_color)->toBeIn(array_keys($dog->coat_colors))
        ->and($pet->generation)->toBe(1)
        ->and($pet->endurance_potential)->toBeGreaterThanOrEqual((int) ceil($dog->endurance_potential * 0.9))
        ->and($pet->endurance_potential)->toBeLessThanOrEqual((int) floor($dog->endurance_potential * 1.1))
        ->and($pet->health)->toBe((float) $dog->health_max)
        ->and($pet->energy)->toBe(100.0)
        ->and($pet->energy_max)->toBe(100)
        ->and($pet->bond)->toBe(0.0)
        ->and($user->fresh()->starter_pet_claimed_at)->not->toBeNull()
        ->and($user->fresh()->coins)->toBe(123)
        ->and($user->fresh()->gems)->toBe(7)
        ->and($other->pets()->exists())->toBeFalse();
    $this->assertDatabaseCount('pets', 1);

    foreach (PetStat::cases() as $stat) {
        expect($pet->getAttribute($stat->value))->toBe((int) round($pet->getAttribute($stat->potentialColumn()) / 5));
    }
})->with(['german_shepherd', 'pit_bull', 'dachshund']);

test('adoption persists the generated sex and coat through the container supplied randomness', function () {
    $this->instance(Randomizer::class, new Randomizer(new class implements Engine
    {
        public function generate(): string
        {
            return pack('V', 1);
        }
    }));
    $dog = Dog::factory()->create([
        'is_starter' => true,
        'coat_colors' => [
            'black' => ['ru' => 'Чёрный', 'en' => 'Black'],
            'brown' => ['ru' => 'Коричневый', 'en' => 'Brown'],
        ],
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('kennel.store'), ['dog_id' => $dog->id, 'name' => 'Рэй'])
        ->assertRedirect(route('dashboard'))->assertSessionHasNoErrors();

    $pet = $user->pets()->sole();
    expect($pet->sex)->toBe(PetSex::Female)
        ->and($pet->coat_color)->toBe('brown')
        ->and($user->fresh()->starter_pet_claimed_at)->not->toBeNull();

    foreach (PetStat::cases() as $stat) {
        expect($pet->getAttribute($stat->potentialColumn()))->toBe(91);
        expect($pet->getAttribute($stat->value))->toBe(18);
        expect($dog->fresh()->getAttribute($stat->potentialColumn()))->toBe(100);
    }
});

test('retrying adoption cannot issue a second pet even after the first is removed', function () {
    $dog = Dog::factory()->create(['is_starter' => true]);
    $user = User::factory()->create();
    $payload = ['dog_id' => $dog->id, 'name' => 'Рэй'];
    $this->actingAs($user)->post(route('kennel.store'), $payload)->assertSessionHasNoErrors();
    $claimedAt = $user->fresh()->starter_pet_claimed_at;

    $this->post(route('kennel.store'), $payload)->assertSessionHasErrors('adoption');
    $this->assertDatabaseCount('pets', 1);

    $user->pets()->sole()->delete();

    $this->post(route('kennel.store'), $payload)->assertSessionHasErrors('adoption');
    $this->assertDatabaseCount('pets', 0);
    expect($user->fresh()->starter_pet_claimed_at->equalTo($claimedAt))->toBeTrue();
    $this->get(route('kennel.index'))->assertInertia(fn (Assert $page) => $page
        ->where('canClaimStarterPet', false)
    );
});

test('an existing pet including a retired pet prevents a first gift', function (bool $retired) {
    $user = User::factory()->create();
    $dog = Dog::factory()->create(['is_starter' => true]);
    Pet::factory()->for($user)->for($dog)->create(['retired_at' => $retired ? now() : null]);

    $this->actingAs($user)->post(route('kennel.store'), ['dog_id' => $dog->id, 'name' => 'Рэй'])
        ->assertSessionHasErrors('adoption');
    $this->get(route('kennel.index'))->assertInertia(fn (Assert $page) => $page
        ->where('canClaimStarterPet', false)
    );
    $this->assertDatabaseCount('pets', 1);
    expect($user->fresh()->starter_pet_claimed_at)->toBeNull();
})->with([false, true]);

test('invalid names or breed selections leave the gift available', function (array $changes, string $field) {
    $user = User::factory()->create(['locale' => 'ru']);
    $dog = Dog::factory()->create(['is_starter' => true]);

    $this->actingAs($user)->post(route('kennel.store'), array_replace([
        'dog_id' => $dog->id, 'name' => 'Рэй',
    ], $changes))->assertSessionHasErrors($field);

    $this->assertDatabaseCount('pets', 0);
    expect($user->fresh()->starter_pet_claimed_at)->toBeNull();
})->with([
    'blank name' => [['name' => '  '], 'name'],
    'long name' => [['name' => str_repeat('я', 65)], 'name'],
    'array name' => [['name' => ['Рэй']], 'name'],
    'missing breed' => [['dog_id' => null], 'dog_id'],
    'unknown breed' => [['dog_id' => 99999], 'dog_id'],
    'invalid breed id' => [['dog_id' => 'invalid'], 'dog_id'],
]);

test('non starter breeds cannot be claimed by submitting their ids', function () {
    $dog = Dog::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('kennel.store'), ['dog_id' => $dog->id, 'name' => 'Рэй'])
        ->assertSessionHasErrors('dog_id');

    $this->assertDatabaseCount('pets', 0);
    expect($user->fresh()->starter_pet_claimed_at)->toBeNull();
});

test('an unavailable coat catalogue rolls back the claim and can be retried after repair', function () {
    $dog = Dog::factory()->create(['is_starter' => true, 'coat_colors' => []]);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('kennel.store'), ['dog_id' => $dog->id, 'name' => 'Рэй'])
        ->assertSessionHasErrors('dog_id');

    expect($user->fresh()->starter_pet_claimed_at)->toBeNull();
    $this->assertDatabaseCount('pets', 0);

    $dog->update(['coat_colors' => ['black' => ['ru' => 'Чёрный', 'en' => 'Black']]]);
    $this->post(route('kennel.store'), ['dog_id' => $dog->id, 'name' => 'Рэй'])
        ->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
});

test('a failed pet insert does not consume the first gift', function () {
    $dog = Dog::factory()->create(['is_starter' => true]);
    $user = User::factory()->create();
    Event::listen('eloquent.creating: '.Pet::class, function (): void {
        throw new RuntimeException('Pet insert failed');
    });

    expect(fn () => app(AdoptStarterPet::class)->handle($user, new AdoptStarterPetData($dog->id, 'Рэй')))
        ->toThrow(RuntimeException::class, 'Pet insert failed');

    expect($user->fresh()->starter_pet_claimed_at)->toBeNull();
    $this->assertDatabaseCount('pets', 0);
});

test('the kennel handles an empty catalogue', function () {
    $this->actingAs(User::factory()->create())->get(route('kennel.index'))
        ->assertInertia(fn (Assert $page) => $page->has('breeds', 0)->where('canClaimStarterPet', true));
});

test('additional dogs cost 500 coins once per purchase and belong to the buyer', function () {
    $dog = Dog::factory()->create(['is_starter' => true]);
    $user = User::factory()->create(['pet_slots' => 3, 'coins' => 1000, 'gems' => 20]);
    Pet::factory()->for($user)->create();
    $other = User::factory()->create();
    $payload = [
        'dog_id' => $dog->id, 'name' => '  Луна  ', 'expected_price' => 500,
        'adoption_token' => (string) Str::uuid(),
        'user_id' => $other->id, 'currency' => 'gems', 'sex' => 'forged', 'generation' => 999,
    ];

    $this->actingAs($user)->post(route('kennel.purchase'), $payload)->assertSessionHasNoErrors();
    $newPet = $user->pets()->latest('id')->firstOrFail();
    $generatedAttributes = $newPet->only(array_map(fn (PetStat $stat): string => $stat->potentialColumn(), PetStat::cases()));
    $this->post(route('kennel.purchase'), $payload)->assertSessionHasNoErrors();

    $this->assertDatabaseCount('pets', 2);
    $this->assertDatabaseCount('currency_transactions', 1);
    $this->assertDatabaseHas('currency_transactions', [
        'user_id' => $user->id, 'amount' => -500, 'currency' => 'coins', 'reason' => 'kennel_purchase',
    ]);
    expect($user->fresh()->coins)->toBe(500);
    expect($user->fresh()->gems)->toBe(20);
    expect($newPet->name)->toBe('Луна')
        ->and($newPet->dog_id)->toBe($dog->id)
        ->and($newPet->generation)->toBe(1)
        ->and($newPet->sex)->toBeIn([PetSex::Male, PetSex::Female])
        ->and($newPet->coat_color)->toBeIn(array_keys($dog->coat_colors));
    expect($other->pets()->exists())->toBeFalse();

    foreach (PetStat::cases() as $stat) {
        expect($newPet->getAttribute($stat->potentialColumn()))->toBeGreaterThanOrEqual(90)->toBeLessThanOrEqual(110);
        expect($newPet->getAttribute($stat->value))->toBe((int) round($newPet->getAttribute($stat->potentialColumn()) / 5));
    }

    expect($newPet->fresh()->only(array_keys($generatedAttributes)))->toBe($generatedAttributes);

    $payload['adoption_token'] = (string) Str::uuid();
    $this->post(route('kennel.purchase'), $payload)->assertSessionHasNoErrors();
    $this->assertDatabaseCount('pets', 3);
    expect($user->fresh()->coins)->toBe(0);
});

test('purchases with unavailable places funds or changed prices leave the account unchanged', function (int $slots, int $coins, int $price, string $message) {
    $dog = Dog::factory()->create(['is_starter' => true]);
    $user = User::factory()->create(['locale' => 'en', 'pet_slots' => $slots, 'coins' => $coins]);
    Pet::factory()->for($user)->create();

    $this->actingAs($user)->post(route('kennel.purchase'), [
        'dog_id' => $dog->id, 'name' => 'Luna', 'expected_price' => $price,
        'adoption_token' => (string) Str::uuid(),
    ])->assertSessionHasErrors(['adoption' => $message]);

    $this->assertDatabaseCount('pets', 1);
    $this->assertDatabaseCount('currency_transactions', 0);
    expect($user->fresh()->coins)->toBe($coins);
    expect($user->fresh()->starter_pet_claimed_at)->toBeNull();
})->with([
    'full places' => [1, 1000, 500, 'You need a free dog slot. Unlock a place on the My dog page.'],
    'insufficient coins' => [2, 499, 500, 'You do not have enough coins for this dog.'],
    'stale quote' => [2, 1000, 499, 'The price has changed. Refresh the page before purchasing.'],
]);

test('a retired dog frees its place but does not restore a free gift', function () {
    $dog = Dog::factory()->create(['is_starter' => true]);
    $user = User::factory()->create(['coins' => 500]);
    Pet::factory()->for($user)->retired()->create();

    $this->actingAs($user)->get(route('kennel.index'))->assertInertia(fn (Assert $page) => $page
        ->where('freeSlots', 1)->where('price', 500)->where('canClaimStarterPet', false));
    $this->post(route('kennel.purchase'), [
        'dog_id' => $dog->id, 'name' => 'Луна', 'expected_price' => 500,
        'adoption_token' => (string) Str::uuid(),
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseCount('pets', 2);
    expect($user->fresh()->coins)->toBe(0);
    expect($user->fresh()->starter_pet_claimed_at)->not->toBeNull();
    $this->get(route('kennel.index'))->assertInertia(fn (Assert $page) => $page->where('freeSlots', 0));
});

test('a first dog cannot be charged and a free gift also requires an open place', function () {
    $dog = Dog::factory()->create(['is_starter' => true]);
    $user = User::factory()->create(['coins' => 500, 'pet_slots' => 0, 'locale' => 'en']);

    $this->actingAs($user)->post(route('kennel.store'), ['dog_id' => $dog->id, 'name' => 'Luna'])
        ->assertSessionHasErrors(['adoption' => 'You need a free dog slot. Unlock a place on the My dog page.']);
    $this->post(route('kennel.purchase'), [
        'dog_id' => $dog->id, 'name' => 'Luna', 'expected_price' => 500,
        'adoption_token' => (string) Str::uuid(),
    ])->assertSessionHasErrors(['adoption' => 'Your first dog is free. Refresh the kennel to claim it.']);

    $this->assertDatabaseCount('pets', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
    expect($user->fresh()->coins)->toBe(500);
    expect($user->fresh()->starter_pet_claimed_at)->toBeNull();
});

test('paid adoption validates input and catalogue availability before spending coins', function (array $changes, string $field, bool $starter, bool $coats) {
    $dog = Dog::factory()->create(['is_starter' => $starter, ...($coats ? [] : ['coat_colors' => []])]);
    $user = User::factory()->create(['coins' => 500, 'starter_pet_claimed_at' => now()]);

    $this->actingAs($user)->post(route('kennel.purchase'), array_replace([
        'dog_id' => $dog->id, 'name' => 'Луна', 'expected_price' => 500,
        'adoption_token' => (string) Str::uuid(),
    ], $changes))->assertSessionHasErrors($field);

    $this->assertDatabaseCount('pets', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
    expect($user->fresh()->coins)->toBe(500);
})->with([
    'missing token' => [['adoption_token' => null], 'adoption_token', true, true],
    'invalid token' => [['adoption_token' => 'invalid'], 'adoption_token', true, true],
    'blank name' => [['name' => ' '], 'name', true, true],
    'long name' => [['name' => str_repeat('я', 65)], 'name', true, true],
    'missing quote' => [['expected_price' => null], 'expected_price', true, true],
    'free quote' => [['expected_price' => 0], 'expected_price', true, true],
    'unavailable breed' => [[], 'dog_id', false, true],
    'empty coats' => [[], 'dog_id', true, false],
]);

test('a failed paid pet insert rolls back the coins and allows retrying the same purchase', function () {
    $dog = Dog::factory()->create(['is_starter' => true]);
    $user = User::factory()->create(['coins' => 500, 'starter_pet_claimed_at' => now()]);
    $data = new PurchaseKennelPetData($dog->id, 'Луна', 500, (string) Str::uuid());
    Event::listen('eloquent.creating: '.Pet::class, function (): void {
        throw new RuntimeException('Pet insert failed');
    });

    expect(fn () => app(PurchaseKennelPet::class)->handle($user, $data))
        ->toThrow(RuntimeException::class, 'Pet insert failed');

    expect($user->fresh()->coins)->toBe(500);
    $this->assertDatabaseCount('pets', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
    Event::forget('eloquent.creating: '.Pet::class);
    app(PurchaseKennelPet::class)->handle($user, $data);
    expect($user->fresh()->coins)->toBe(0);
    $this->assertDatabaseCount('pets', 1);
});

test('a purchase receipt survives pet changes and deletion and normalizes its token', function () {
    $dog = Dog::factory()->create(['is_starter' => true]);
    $user = User::factory()->create(['coins' => 500, 'starter_pet_claimed_at' => now()]);
    $token = strtoupper((string) Str::uuid());
    $action = app(PurchaseKennelPet::class);
    $receipt = $action->handle($user, new PurchaseKennelPetData($dog->id, ' Luna ', 500, $token));
    Pet::query()->findOrFail($receipt->pet_id)->update(['name' => 'Renamed', 'retired_at' => now()]);
    $dog->update(['is_starter' => false]);
    config(['doglive.kennel_price' => 700]);

    $repeat = $action->handle($user, new PurchaseKennelPetData($dog->id, 'Luna', 500, strtolower($token)));
    expect($repeat->id)->toBe($receipt->id);
    expect($repeat->wasRecentlyCreated)->toBeFalse();
    Pet::query()->findOrFail($receipt->pet_id)->delete();
    expect($action->handle($user, new PurchaseKennelPetData($dog->id, 'Luna', 500, $token))->id)->toBe($receipt->id);
    $this->assertDatabaseHas('kennel_purchases', ['id' => $receipt->id, 'pet_id' => $receipt->pet_id, 'pet_name' => 'Luna', 'token' => strtolower($token)]);
    $this->assertDatabaseCount('currency_transactions', 1);
    $this->assertDatabaseCount('pets', 0);
    expect($user->fresh()->coins)->toBe(0);
});

test('reusing a dog purchase token with different parameters is rejected', function (string $field) {
    $dog = Dog::factory()->create(['is_starter' => true]);
    $otherBreed = Dog::factory()->create(['is_starter' => true]);
    $user = User::factory()->create(['locale' => 'en', 'coins' => 1000, 'pet_slots' => 2, 'starter_pet_claimed_at' => now()]);
    $payload = ['dog_id' => $dog->id, 'name' => 'Luna', 'expected_price' => 500, 'adoption_token' => (string) Str::uuid()];
    $this->actingAs($user)->post(route('kennel.purchase'), $payload)->assertSessionHasNoErrors();
    $payload[$field] = match ($field) {
        'dog_id' => $otherBreed->id, 'name' => 'Other', 'expected_price' => 501
    };

    $this->post(route('kennel.purchase'), $payload)->assertSessionHasErrors(['adoption' => 'The token was already used for a different dog purchase.']);
    $this->assertDatabaseCount('pets', 1);
    $this->assertDatabaseCount('kennel_purchases', 1);
    expect($user->fresh()->coins)->toBe(500);
})->with(['dog_id', 'name', 'expected_price']);

test('a replay returns the original dog and explains that no second charge occurred', function () {
    $dog = Dog::factory()->create(['is_starter' => true]);
    $user = User::factory()->create(['locale' => 'en', 'coins' => 500, 'starter_pet_claimed_at' => now()]);
    $payload = ['dog_id' => $dog->id, 'name' => 'Luna', 'expected_price' => 500, 'adoption_token' => (string) Str::uuid()];
    $this->actingAs($user)->post(route('kennel.purchase'), $payload)->assertSessionHasNoErrors();
    $pet = $user->pets()->sole();
    $dog->update(['is_starter' => false]);

    $this->post(route('kennel.purchase'), $payload)->assertRedirect(route('dashboard', ['pet' => $pet->id]));
    $this->followingRedirects()->post(route('kennel.purchase'), $payload)->assertInertia(fn (Assert $page) => $page
        ->hasFlash('toast.message', 'This dog purchase was already completed. You have not been charged again.'));
    $pet->delete();
    $this->post(route('kennel.purchase'), $payload)->assertRedirect(route('dashboard'));
    $this->assertDatabaseCount('currency_transactions', 1);
});

test('legacy payments without receipts never issue another dog or claim a successful replay', function () {
    $dog = Dog::factory()->create(['is_starter' => true]);
    $user = User::factory()->create(['coins' => 1000, 'starter_pet_claimed_at' => now()]);
    $token = strtoupper((string) Str::uuid());
    app(PlayerWallet::class)->change($user, 'coins', -500, 'kennel:'.$token, 'kennel_purchase');

    expect(fn () => app(PurchaseKennelPet::class)->handle($user, new PurchaseKennelPetData($dog->id, 'Luna', 500, strtolower($token))))
        ->toThrow(AdoptionUnavailable::class, 'This purchase was already paid for, but its receipt is unavailable. Check your dogs before making a new purchase.');
    $this->assertDatabaseCount('pets', 0);
    $this->assertDatabaseCount('currency_transactions', 1);
    expect($user->fresh()->coins)->toBe(500);
});

test('receipt insert failure rolls back the whole purchase', function () {
    $dog = Dog::factory()->create(['is_starter' => true]);
    $user = User::factory()->create(['coins' => 500, 'starter_pet_claimed_at' => now()]);
    Event::listen('eloquent.creating: '.KennelPurchase::class, function (): void {
        throw new RuntimeException('Receipt unavailable');
    });

    expect(fn () => app(PurchaseKennelPet::class)->handle($user, new PurchaseKennelPetData($dog->id, 'Luna', 500, (string) Str::uuid())))
        ->toThrow(RuntimeException::class, 'Receipt unavailable');
    $this->assertDatabaseCount('pets', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
    $this->assertDatabaseCount('kennel_purchases', 0);
    expect($user->fresh()->coins)->toBe(500);
});

test('guests cannot purchase dogs', function () {
    $this->post(route('kennel.purchase'))->assertRedirect(route('login'));
    $this->assertDatabaseCount('pets', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('blocked players cannot receive dogs from the kennel', function (string $route) {
    $dog = Dog::factory()->create(['is_starter' => true]);
    $user = User::factory()->create(['status' => PlayerStatus::Blocked, 'coins' => 1000, 'locale' => 'en']);

    $this->actingAs($user)->post(route($route), [
        'dog_id' => $dog->id, 'name' => 'Luna', 'expected_price' => 500,
        'adoption_token' => (string) Str::uuid(),
    ])->assertSessionHasErrors(['adoption' => 'Your account is blocked.']);

    $this->assertDatabaseCount('pets', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => 1000, 'starter_pet_claimed_at' => null]);
})->with(['kennel.store', 'kennel.purchase']);

test('starter adoption checks the persisted account status even with a stale user instance', function () {
    $dog = Dog::factory()->create(['is_starter' => true]);
    $user = User::factory()->create();
    User::query()->whereKey($user->id)->update(['status' => PlayerStatus::Blocked]);

    expect(fn () => app(AdoptStarterPet::class)->handle($user, new AdoptStarterPetData($dog->id, 'Luna')))
        ->toThrow(AdoptionUnavailable::class, 'Your account is blocked.');

    $this->assertDatabaseCount('pets', 0);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'starter_pet_claimed_at' => null]);
});

test('paid adoption checks the persisted status before a new purchase or replay', function (bool $replay) {
    $dog = Dog::factory()->create(['is_starter' => true]);
    $user = User::factory()->create(['pet_slots' => 3, 'coins' => 1000, 'starter_pet_claimed_at' => now()]);
    $data = new PurchaseKennelPetData($dog->id, 'Luna', 500, (string) Str::uuid());

    if ($replay) {
        app(PurchaseKennelPet::class)->handle($user, $data);
    }

    User::query()->whereKey($user->id)->update(['status' => PlayerStatus::Blocked]);

    expect(fn () => app(PurchaseKennelPet::class)->handle($user, $data))
        ->toThrow(AdoptionUnavailable::class, 'Your account is blocked.');

    $this->assertDatabaseCount('pets', $replay ? 1 : 0);
    $this->assertDatabaseCount('currency_transactions', $replay ? 1 : 0);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => $replay ? 500 : 1000]);
})->with(['new purchase' => false, 'repeated purchase' => true]);
