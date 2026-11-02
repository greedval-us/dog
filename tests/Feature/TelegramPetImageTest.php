<?php

use App\Http\Telegram\TelegramPetImage;
use App\Models\AssetUnlock;
use App\Models\Dog;
use App\Models\GameAsset;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function putTelegramPetImage(string $path): void
{
    Storage::disk('local')->put($path, (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', true));
}

test('telegram dog images use the owners unlocked selected portrait before free or breed artwork', function () {
    Storage::fake('local');
    Http::preventStrayRequests();
    putTelegramPetImage('appearance/selected.png');
    putTelegramPetImage('appearance/free.png');
    putTelegramPetImage('appearance/icon.png');
    putTelegramPetImage('breeds/german_shepherd/portrait.png');
    $breed = Dog::factory()->create(['breed' => 'german_shepherd']);
    $pet = Pet::factory()->for($breed)->create();
    GameAsset::factory()->for($breed)->create([
        'coat_color' => $pet->coat_color, 'image_path' => 'appearance/free.png', 'icon_path' => 'appearance/icon.png',
    ]);
    $selected = GameAsset::factory()->paid()->for($breed)->create([
        'coat_color' => $pet->coat_color, 'pose' => 'sitting',
        'image_path' => 'appearance/selected.png', 'icon_path' => 'appearance/icon.png',
    ]);
    AssetUnlock::factory()->for($pet->user)->create(['game_asset_id' => $selected->id]);
    $pet->forceFill(['portrait_asset_id' => $selected->id])->save();

    $path = app(TelegramPetImage::class)->forPet($pet->user, $pet->id);

    expect($path)->toBe(realpath(Storage::disk('local')->path('appearance/selected.png')));
    Http::assertNothingSent();
});

test('telegram dog images use the matching free portrait when a selected portrait is unavailable', function (array $attributes) {
    Storage::fake('local');
    Http::preventStrayRequests();
    putTelegramPetImage('appearance/selected.png');
    putTelegramPetImage('appearance/free.png');
    putTelegramPetImage('appearance/icon.png');
    $pet = Pet::factory()->create();
    GameAsset::factory()->create([
        'dog_id' => $pet->dog_id, 'coat_color' => $pet->coat_color,
        'image_path' => 'appearance/free.png', 'icon_path' => 'appearance/icon.png',
    ]);
    $selected = GameAsset::factory()->create([
        'dog_id' => $pet->dog_id, 'coat_color' => $pet->coat_color,
        'image_path' => 'appearance/selected.png', 'icon_path' => 'appearance/icon.png', ...$attributes,
    ]);
    $pet->forceFill(['portrait_asset_id' => $selected->id])->save();

    $path = app(TelegramPetImage::class)->forPet($pet->user, $pet->id);

    expect($path)->toBe(realpath(Storage::disk('local')->path('appearance/free.png')));
    Http::assertNothingSent();
})->with([
    'locked paid portrait' => [['coins_price' => 100]],
    'inactive portrait' => [['is_active' => false]],
    'incompatible coat' => [['coat_color' => 'another_coat']],
    'invalid price' => [['coins_price' => 0]],
    'missing portrait image' => [['image_path' => 'appearance/missing.png']],
    'missing portrait icon' => [['icon_path' => 'appearance/missing.png']],
    'remote portrait URL' => [['image_path' => 'https://example.test/private.png']],
    'path outside private artwork' => [['image_path' => '../secret.png']],
]);

test('telegram dog images use the first matching free portrait when no portrait was selected', function () {
    Storage::fake('local');
    putTelegramPetImage('appearance/first.png');
    putTelegramPetImage('appearance/second.png');
    putTelegramPetImage('appearance/icon.png');
    $pet = Pet::factory()->create();
    GameAsset::factory()->create([
        'dog_id' => $pet->dog_id, 'coat_color' => $pet->coat_color,
        'image_path' => 'appearance/second.png', 'icon_path' => 'appearance/icon.png', 'sort_order' => 10,
    ]);
    GameAsset::factory()->create([
        'dog_id' => $pet->dog_id, 'coat_color' => $pet->coat_color,
        'image_path' => 'appearance/first.png', 'icon_path' => 'appearance/icon.png', 'sort_order' => 0,
    ]);

    $path = app(TelegramPetImage::class)->forPet($pet->user, $pet->id);

    expect($path)->toBe(realpath(Storage::disk('local')->path('appearance/first.png')));
});

test('telegram dog images fall back to the existing breed portrait when no appearance is available', function () {
    Storage::fake('local');
    putTelegramPetImage('breeds/german_shepherd/portrait.png');
    $breed = Dog::factory()->create(['breed' => 'german_shepherd']);
    $pet = Pet::factory()->for($breed)->create();

    $path = app(TelegramPetImage::class)->forPet($pet->user, $pet->id);

    expect($path)->toBe(realpath(Storage::disk('local')->path('breeds/german_shepherd/portrait.png')));
});

test('telegram dog images reject a corrupt selected image and use the breed portrait', function () {
    Storage::fake('local');
    Storage::disk('local')->put('appearance/corrupt.png', 'not-an-image');
    putTelegramPetImage('appearance/icon.png');
    putTelegramPetImage('breeds/german_shepherd/portrait.png');
    $breed = Dog::factory()->create(['breed' => 'german_shepherd']);
    $pet = Pet::factory()->for($breed)->create();
    $selected = GameAsset::factory()->for($breed)->create([
        'coat_color' => $pet->coat_color, 'image_path' => 'appearance/corrupt.png', 'icon_path' => 'appearance/icon.png',
    ]);
    $pet->forceFill(['portrait_asset_id' => $selected->id])->save();

    $path = app(TelegramPetImage::class)->forPet($pet->user, $pet->id);

    expect($path)->toBe(realpath(Storage::disk('local')->path('breeds/german_shepherd/portrait.png')));
});

test('telegram dog images cannot resolve another players dog', function () {
    Storage::fake('local');
    $pet = Pet::factory()->create();
    $viewer = User::factory()->create();

    expect(fn () => app(TelegramPetImage::class)->forPet($viewer, $pet->id))
        ->toThrow(ModelNotFoundException::class);
});

test('telegram breed previews resolve only existing allowlisted private portraits', function (string $breedCode) {
    Storage::fake('local');
    putTelegramPetImage('breeds/'.$breedCode.'/portrait.png');
    $breed = Dog::factory()->create(['breed' => $breedCode]);

    $path = app(TelegramPetImage::class)->forBreed($breed->id);

    expect($path)->toBe(realpath(Storage::disk('local')->path('breeds/'.$breedCode.'/portrait.png')));
})->with(['german_shepherd', 'pit_bull', 'dachshund']);

test('telegram images stay absent for breeds without a usable private portrait', function (string $breedCode, ?string $contents) {
    Storage::fake('local');
    Http::preventStrayRequests();
    if ($contents !== null) {
        Storage::disk('local')->put('breeds/'.$breedCode.'/portrait.png', $contents);
    }
    $breed = Dog::factory()->create(['breed' => $breedCode]);
    $pet = Pet::factory()->for($breed)->create();
    $images = app(TelegramPetImage::class);

    expect($images->forBreed($breed->id))->toBeNull();
    expect($images->forPet($pet->user, $pet->id))->toBeNull();
    Http::assertNothingSent();
})->with([
    'unillustrated breed' => ['unillustrated', null],
    'missing portrait' => ['german_shepherd', null],
    'corrupt portrait' => ['german_shepherd', 'not-an-image'],
    'empty portrait' => ['german_shepherd', ''],
    'another image format renamed PNG' => ['german_shepherd', (string) base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==', true)],
]);

test('telegram breed previews do not resolve unknown catalogue ids', function () {
    expect(app(TelegramPetImage::class)->forBreed(999))->toBeNull();
});
