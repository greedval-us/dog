<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\CurrencyTransaction;
use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'username' => 'test_player',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
        'username' => 'test_player',
        'status' => 'active',
        'coins' => 300,
        'gems' => 0,
        'experience' => 0,
        'locale' => 'ru',
        'timezone' => 'UTC',
    ]);
    $this->assertDatabaseHas('currency_transactions', [
        'user_id' => auth()->id(),
        'currency' => 'coins',
        'amount' => 300,
        'balance_before' => 0,
        'balance_after' => 300,
        'operation_key' => 'registration:'.auth()->id(),
        'reason' => 'registration_bonus',
    ]);
    $this->assertDatabaseCount('currency_transactions', 1);
    expect(auth()->user()->coins)->toBe(300);
});

test('registration rejects invalid public usernames', function (?string $username) {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'username' => $username,
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('username');
    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with([null, str_repeat('a', 33), 'Player', 'player/name']);

test('registration rejects an occupied public username', function () {
    User::factory()->create(['username' => 'taken']);

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'username' => 'taken',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('username');
    $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
});

test('registration cannot set protected player state', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'username' => str_repeat('a', 32),
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'status' => 'blocked',
        'coins' => 1000,
        'gems' => 1000,
        'experience' => 1000,
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
        'username' => str_repeat('a', 32),
        'status' => 'active',
        'coins' => 300,
        'gems' => 0,
        'experience' => 0,
    ]);
});

test('failed registration bonus rolls back the new account and ledger', function () {
    CurrencyTransaction::creating(function (): void {
        throw new RuntimeException('Registration bonus unavailable.');
    });

    try {
        expect(fn () => app(CreateNewUser::class)->create([
            'name' => 'Test User',
            'username' => 'test_player',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]))->toThrow(RuntimeException::class, 'Registration bonus unavailable.');
    } finally {
        CurrencyTransaction::flushEventListeners();
    }

    $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    $this->assertDatabaseCount('currency_transactions', 0);
});
