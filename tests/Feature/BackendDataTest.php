<?php

use App\Actions\AdoptStarterPet;
use App\Data\AdoptStarterPetData;
use App\Enums\DogSize;
use App\Enums\PetSex;
use App\Enums\PlayerStatus;
use App\Exceptions\StarterBreedUnavailable;
use App\Models\Dog;
use App\Models\Pet;
use App\Models\User;
use App\Queries\GetPlayerProfile;
use App\Queries\GetPrimaryPet;
use App\Queries\GetStarterBreeds;
use Illuminate\Support\Facades\DB;

test('stored strings become enums and enum assignments preserve the database format', function (string $size, DogSize $expectedSize, string $sex, PetSex $expectedSex) {
    $user = User::factory()->create(['status' => 'blocked'])->refresh();
    $dog = Dog::factory()->create(['size' => $size])->refresh();
    $pet = Pet::factory()->for($dog)->for($user)->create(['sex' => $sex])->refresh();

    expect($dog->size)->toBe($expectedSize);
    expect($pet->size)->toBe($expectedSize);
    expect($pet->sex)->toBe($expectedSex);
    expect($user->status)->toBe(PlayerStatus::Blocked);

    $pet->sex = PetSex::Female;
    $pet->save();
    $user->status = PlayerStatus::Active;
    $user->save();

    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'sex' => 'female', 'size' => $size]);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'active']);
    expect($pet->refresh()->toArray()['sex'])->toBe('female');
})->with([
    'small male' => ['small', DogSize::Small, 'male', PetSex::Male],
    'medium female' => ['medium', DogSize::Medium, 'female', PetSex::Female],
    'large male' => ['large', DogSize::Large, 'male', PetSex::Male],
]);

test('read DTOs serialize localized scalar data without additional queries', function () {
    $user = User::factory()->create();
    $dog = Dog::factory()->create([
        'name' => ['en' => 'Hound'], 'description' => ['en' => 'A friendly hound.'],
        'size' => DogSize::Small, 'is_starter' => true,
        'coat_colors' => ['black' => ['en' => 'Black']],
    ]);
    Pet::factory()->for($dog)->for($user)->create(['sex' => PetSex::Female]);
    $pet = app(GetPrimaryPet::class)->handle($user, 'ru');
    $breeds = app(GetStarterBreeds::class)->handle('ru');
    $player = app(GetPlayerProfile::class)->handle($user);

    DB::enableQueryLog();
    DB::flushQueryLog();

    try {
        $petData = $pet->toArray();
        $breedData = $breeds->sole()->toArray();
        $playerData = $player->toArray();

        expect(DB::getQueryLog())->toBe([]);
        expect($petData)->toMatchArray(['sex' => 'female', 'size' => 'small', 'breed' => 'Hound', 'coatColor' => 'Black']);
        expect($breedData)->toMatchArray(['name' => 'Hound', 'description' => 'A friendly hound.', 'size' => 'small']);
        expect($playerData)->toMatchArray(['dogsCount' => 1, 'level' => 1, 'experience' => 0]);
    } finally {
        DB::disableQueryLog();
    }
});

test('adoption rejects a withdrawn breed outside HTTP and rolls back the claim', function () {
    $user = User::factory()->create();
    $dog = Dog::factory()->create(['is_starter' => true]);
    $data = new AdoptStarterPetData($dog->id, 'Рэй');
    $dog->update(['is_starter' => false]);

    expect(fn () => app(AdoptStarterPet::class)->handle($user, $data))
        ->toThrow(StarterBreedUnavailable::class);

    expect($user->refresh()->starter_pet_claimed_at)->toBeNull();
    $this->assertDatabaseCount('pets', 0);
});

test('the two factor factory state stores encrypted credentials hidden from serialization', function () {
    $user = User::factory()->withTwoFactor()->create()->refresh();

    expect(decrypt($user->two_factor_secret))->toBe('JBSWY3DPEHPK3PXP');
    expect(json_decode(decrypt($user->two_factor_recovery_codes), true, flags: JSON_THROW_ON_ERROR))->toBe(['test-recovery-code']);
    expect($user->two_factor_confirmed_at)->not->toBeNull();
    expect($user->toArray())->not->toHaveKeys(['two_factor_secret', 'two_factor_recovery_codes']);
});
