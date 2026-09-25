<?php

use App\Actions\AdoptStarterPet;
use App\Data\AdoptStarterPetData;
use App\Enums\PetSex;
use App\Models\Dog;
use App\Models\Pet;
use App\Models\User;
use Database\Seeders\DogSeeder;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

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
        ->and($pet->endurance)->toBe(0)
        ->and($pet->endurance_potential)->toBe($dog->endurance_potential)
        ->and($pet->health)->toBe((float) $dog->health_max)
        ->and($pet->bond)->toBe(0.0)
        ->and($user->fresh()->starter_pet_claimed_at)->not->toBeNull()
        ->and($user->fresh()->coins)->toBe(123)
        ->and($user->fresh()->gems)->toBe(7)
        ->and($other->pets()->exists())->toBeFalse();
    $this->assertDatabaseCount('pets', 1);
})->with(['german_shepherd', 'pit_bull', 'dachshund']);

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
