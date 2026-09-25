<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('the pet scene requires authentication', function () {
    $this->get(route('pet-scene'))->assertRedirect(route('login'));
});

test('players receive the fixed private scene and cannot select another file', function () {
    Storage::fake('local');
    Storage::disk('local')->put('scenes/pet-profile.png', 'pet-scene');
    Storage::disk('local')->put('secret.png', 'private-secret');

    $response = $this->actingAs(User::factory()->create())
        ->get(route('pet-scene', ['path' => 'secret.png']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'no-store, private');

    expect($response->streamedContent())->toBe('pet-scene');
});

test('a missing scene returns not found', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create())->get(route('pet-scene'))->assertNotFound();
});
