<?php

use App\Models\Dog;
use App\Models\Pet;
use App\Models\User;
use Database\Seeders\DogSeeder;
use Illuminate\Database\QueryException;

test('starter catalogue seeds three localized breeds without giving pets to players', function () {
    $this->seed(DogSeeder::class);
    $this->seed(DogSeeder::class);

    $this->assertDatabaseCount('dog', 3);
    $this->assertDatabaseCount('pets', 0);
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseHas('dog', ['breed' => 'german_shepherd', 'size' => 'large', 'is_starter' => true]);
    $this->assertDatabaseHas('dog', ['breed' => 'pit_bull', 'size' => 'medium', 'is_starter' => true]);
    $this->assertDatabaseHas('dog', ['breed' => 'dachshund', 'size' => 'small', 'is_starter' => true]);

    $dog = Dog::query()->where('breed', 'german_shepherd')->firstOrFail();
    expect($dog->localizedName('ru'))->toBe('Немецкая овчарка');
    expect($dog->localizedName('en'))->toBe('German Shepherd');
    expect($dog->localizedName('de'))->toBe('German Shepherd');
    expect($dog->coat_colors['black_tan'])->toBe(['ru' => 'Чепрачный', 'en' => 'Black and tan']);
});

test('new pets keep a personal snapshot of breed potential and body needs', function () {
    $dog = Dog::factory()->create(['endurance_potential' => 140, 'satiety_max' => 600, 'food_per_day' => 300]);
    $user = User::factory()->create();
    $pet = $dog->newPet([
        'name' => 'Рэй',
        'sex' => 'male',
        'coat_color' => 'black',
        'description' => 'Любит прогулки.',
    ]);

    $user->pets()->save($pet);
    $dog->update(['endurance_potential' => 180, 'satiety_max' => 800, 'food_per_day' => 400]);
    $pet->refresh();

    expect($pet->user->is($user))->toBeTrue();
    expect($pet->dog->is($dog))->toBeTrue();
    $this->assertDatabaseHas('pets', [
        'id' => $pet->id, 'name' => 'Рэй', 'sex' => 'male', 'coat_color' => 'black',
        'endurance' => 0, 'endurance_potential' => 140,
        'satiety' => 600, 'satiety_max' => 600, 'food_per_day' => 300,
        'generation' => 1, 'father_id' => null, 'mother_id' => null, 'bond' => 0,
    ]);
});

test('all six genetic limits are independent of current training and may exceed the starting breed', function () {
    $dog = Dog::factory()->create();
    $user = User::factory()->create();
    $father = Pet::factory()->for($dog)->for($user)->create();
    $mother = Pet::factory()->female()->for($dog)->for($user)->create();

    $child = Pet::factory()->for($dog)->for($user)->create([
        'father_id' => $father->id,
        'mother_id' => $mother->id,
        'generation' => 2,
        'endurance' => 10, 'endurance_potential' => 110,
        'speed' => 11, 'speed_potential' => 111,
        'strength' => 12, 'strength_potential' => 112,
        'agility' => 13, 'agility_potential' => 113,
        'obedience' => 14, 'obedience_potential' => 114,
        'intelligence' => 15, 'intelligence_potential' => 115,
    ]);

    $this->assertDatabaseHas('pets', [
        'id' => $child->id, 'generation' => 2,
        'endurance' => 10, 'endurance_potential' => 110,
        'speed' => 11, 'speed_potential' => 111,
        'strength' => 12, 'strength_potential' => 112,
        'agility' => 13, 'agility_potential' => 113,
        'obedience' => 14, 'obedience_potential' => 114,
        'intelligence' => 15, 'intelligence_potential' => 115,
    ]);
    expect($child->father->is($father))->toBeTrue();
    expect($child->mother->is($mother))->toBeTrue();
    expect($father->paternalOffspring->modelKeys())->toBe([$child->id]);
    expect($mother->maternalOffspring->modelKeys())->toBe([$child->id]);
});

test('raw condition values produce the seven percentages shown in the sketch', function () {
    $pet = Pet::factory()->create([
        'health' => 120, 'health_max' => 120,
        'energy' => 102, 'energy_max' => 120,
        'satiety' => 540, 'satiety_max' => 600,
        'hydration' => 855, 'hydration_max' => 900,
        'mood' => 80, 'mood_max' => 100,
        'cleanliness' => 88, 'cleanliness_max' => 100,
        'bond' => 92, 'bond_max' => 100,
    ])->refresh();

    expect($pet->statePercentages())->toBe([
        'health' => 100.0, 'energy' => 85.0, 'satiety' => 90.0, 'hydration' => 95.0,
        'mood' => 80.0, 'cleanliness' => 88.0, 'bond' => 92.0,
    ]);
});

test('small and large dogs need different food amounts at the same visible percentage', function () {
    $this->seed(DogSeeder::class);
    $smallDog = Dog::query()->where('breed', 'dachshund')->firstOrFail();
    $largeDog = Dog::query()->where('breed', 'german_shepherd')->firstOrFail();

    $small = Pet::factory()->for($smallDog)->create(['satiety' => 100]);
    $large = Pet::factory()->for($largeDog)->create(['satiety' => 300]);

    expect($small->statePercentages()['satiety'])->toBe(50.0);
    expect($large->statePercentages()['satiety'])->toBe(50.0);
    expect($small->satiety_max - $small->satiety)->toBe(100.0);
    expect($large->satiety_max - $large->satiety)->toBe(300.0);
    expect($small->food_per_day)->toBe(100);
    expect($large->food_per_day)->toBe(300);
});

test('condition percentages are bounded and allow fractional raw amounts', function (float $value, int $maximum, float $expected) {
    $pet = Pet::factory()->make(['satiety' => $value, 'satiety_max' => $maximum]);

    expect($pet->statePercentages()['satiety'])->toBe($expected);
})->with([
    'fraction' => [1.25, 10, 12.5],
    'empty' => [0.0, 100, 0.0],
    'below zero' => [-5.0, 100, 0.0],
    'above capacity' => [150.0, 100, 100.0],
    'zero capacity' => [10.0, 0, 0.0],
]);

test('retirement and owner deletion preserve pedigree records', function () {
    $dog = Dog::factory()->create();
    $fatherOwner = User::factory()->create();
    $father = Pet::factory()->for($dog)->for($fatherOwner)->retired()->create();
    $child = Pet::factory()->for($dog)->create(['father_id' => $father->id, 'generation' => 2]);

    $fatherOwner->delete();

    $this->assertModelExists($father);
    expect($father->refresh()->user_id)->toBeNull();
    expect($father->retired_at)->not->toBeNull();
    expect($child->refresh()->father->is($father))->toBeTrue();
});

test('referenced parents cannot be physically deleted', function (string $parentField, string $sex) {
    $parent = Pet::factory()->create(['sex' => $sex]);
    Pet::factory()->for($parent->dog)->create([$parentField => $parent->id]);

    expect(fn () => $parent->delete())->toThrow(QueryException::class);
})->with([
    'father' => ['father_id', 'male'],
    'mother' => ['mother_id', 'female'],
]);

test('a breed used by a pet cannot be deleted', function () {
    $pet = Pet::factory()->create();

    expect(fn () => $pet->dog->delete())->toThrow(QueryException::class);
});

test('pets cannot reference a nonexistent parent', function () {
    expect(fn () => Pet::factory()->create(['father_id' => 999999]))
        ->toThrow(QueryException::class);
});

test('birth dates photos and personality can be stored on an individual pet', function () {
    $pet = Pet::factory()->create([
        'born_at' => '2026-09-01 10:00:00',
        'photos' => ['pets/front.jpg', 'pets/side.jpg'],
        'traits' => ['friendly', 'active'],
    ])->refresh();

    expect($pet->born_at->format('Y-m-d H:i:s'))->toBe('2026-09-01 10:00:00');
    expect($pet->photos)->toBe(['pets/front.jpg', 'pets/side.jpg']);
    expect($pet->traits)->toBe(['friendly', 'active']);
});
