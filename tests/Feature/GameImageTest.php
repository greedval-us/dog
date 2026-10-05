<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('the pet scene requires authentication', function () {
    $this->get(route('pet-scene'), ['If-None-Match' => '*'])->assertRedirect(route('login'));
});

test('players receive the fixed private scene and cannot select another file', function () {
    Storage::fake('local');
    Storage::disk('local')->put('scenes/pet-profile.png', 'pet-scene');
    Storage::disk('local')->put('secret.png', 'private-secret');

    $response = $this->actingAs(User::factory()->create())
        ->get(route('pet-scene', ['path' => 'secret.png']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'max-age=3600, private')
        ->assertHeader('Last-Modified')
        ->assertHeader('ETag')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->streamedContent())->toBe('pet-scene');
});

test('a missing scene returns not found', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create())->get(route('pet-scene'))->assertNotFound();
});

test('game images prefer a WebP sibling when both formats exist', function (string $route, array $parameters, string $path) {
    Storage::fake('local');
    Storage::disk('local')->put($path.'.png', 'original-png');
    Storage::disk('local')->put($path.'.webp', 'optimized-webp');
    touch(Storage::disk('local')->path($path.'.png'), 1735689600);
    touch(Storage::disk('local')->path($path.'.webp'), 1735776000);

    $response = $this->actingAs(User::factory()->create())->get(route($route, $parameters))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('Last-Modified', 'Thu, 02 Jan 2025 00:00:00 GMT')
        ->assertHeader('Cache-Control', 'max-age=3600, private');

    expect($response->streamedContent())->toBe('optimized-webp');
})->with([
    'scene' => ['pet-scene', [], 'scenes/pet-profile'],
    'breed icon' => ['breeds.image', ['breed' => 'german_shepherd', 'variant' => 'icon'], 'breeds/german_shepherd/icon'],
]);

test('unchanged game images return 304 without the image body', function (string $requestHeader, string $responseHeader) {
    Storage::fake('local');
    Storage::disk('local')->put('scenes/pet-profile.png', 'original-png');
    Storage::disk('local')->put('scenes/pet-profile.webp', 'optimized-webp');
    $this->actingAs(User::factory()->create());
    $original = $this->get(route('pet-scene'))->assertOk();

    $response = $this->get(route('pet-scene'), [$requestHeader => $original->headers->get($responseHeader)])
        ->assertNotModified()
        ->assertHeader('Cache-Control', 'max-age=3600, private')
        ->assertHeader('ETag', $original->headers->get('ETag'));

    expect($response->streamedContent())->toBeEmpty();
})->with([
    'ETag' => ['If-None-Match', 'ETag'],
    'modification date' => ['If-Modified-Since', 'Last-Modified'],
]);

test('changed image contents are returned instead of a stale 304', function () {
    Storage::fake('local');
    Storage::disk('local')->put('scenes/pet-profile.png', 'original-png');
    $this->actingAs(User::factory()->create());
    $original = $this->get(route('pet-scene'))->assertOk();
    Storage::disk('local')->put('scenes/pet-profile.png', 'modified-png');

    $response = $this->get(route('pet-scene'), ['If-None-Match' => $original->headers->get('ETag')])
        ->assertOk();

    expect($response->headers->get('ETag'))->not->toBe($original->headers->get('ETag'));
    expect($response->streamedContent())->toBe('modified-png');
});

test('a WebP sibling cannot bypass a missing original scene', function () {
    Storage::fake('local');
    Storage::disk('local')->put('scenes/pet-profile.webp', 'orphaned-webp');

    $this->actingAs(User::factory()->create())->get(route('pet-scene'))->assertNotFound();
});

test('unsupported breeds and variants cannot select private image paths', function (string $breed, string $variant) {
    Storage::fake('local');
    Storage::disk('local')->put('breeds/private/icon.png', 'private-png');
    Storage::disk('local')->put('breeds/private/icon.webp', 'private-webp');

    $this->actingAs(User::factory()->create())
        ->get(route('breeds.image', ['breed' => $breed, 'variant' => $variant]))
        ->assertNotFound();
})->with([
    'unknown breed' => ['private', 'icon'],
    'unknown variant' => ['german_shepherd', 'private'],
]);
