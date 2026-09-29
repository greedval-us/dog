<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('shared auth exposes only the explicit frontend contract even with loaded relationships', function () {
    $user = User::factory()->create(['coins' => 123, 'gems' => 45, 'bio' => 'Private model attribute']);
    $user->load('pets');
    $user->setAttribute('future_sensitive_field', 'must remain private');

    $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('auth.user', [
            'id' => $user->id, 'name' => $user->name, 'username' => $user->username,
            'email' => $user->email, 'email_verified_at' => $user->email_verified_at->toISOString(),
            'coins' => 123, 'gems' => 45, 'avatarVersion' => null,
        ])
    );
});

test('guests receive a null shared auth user', function () {
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('auth.user', null));
});
