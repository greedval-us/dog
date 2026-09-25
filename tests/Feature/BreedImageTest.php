<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('breed illustrations require authentication', function () {
    $this->get(route('breeds.image', ['breed' => 'german_shepherd', 'variant' => 'portrait']))
        ->assertRedirect(route('login'));
});

test('authenticated players receive only the requested private breed image', function (string $variant) {
    Storage::fake('local');
    Storage::disk('local')->put('breeds/german_shepherd/'.$variant.'.png', 'private-image');

    $response = $this->actingAs(User::factory()->create())
        ->get(route('breeds.image', ['breed' => 'german_shepherd', 'variant' => $variant]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->streamedContent())->toBe('private-image');
})->with(['portrait', 'icon']);

test('unknown or missing assets cannot expose private storage files', function (string $breed, string $variant) {
    Storage::fake('local');
    Storage::disk('local')->put('secret.png', 'secret');
    Storage::disk('local')->put('breeds/unknown/portrait.png', 'secret');

    $this->actingAs(User::factory()->create())
        ->get(route('breeds.image', compact('breed', 'variant')))->assertNotFound();
})->with([
    ['german_shepherd', 'portrait'],
    ['unknown', 'portrait'],
    ['german_shepherd', 'secret'],
    ['..', 'portrait'],
]);
