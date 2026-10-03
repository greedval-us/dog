<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('player.username', $user->username)
        ->where('player.level', 1)
        ->where('player.experience', '0')
        ->where('player.dogsCount', 0)
        ->missing('player.email')
    );
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});

test('players can update their name and bio without changing their public username or statistics', function () {
    $user = User::factory()->create(['level' => 4, 'walks_count' => 15]);
    $otherPlayer = User::factory()->create(['bio' => 'Другая карточка']);
    $bio = str_repeat('я', 1000);

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => 'Новое имя', 'bio' => $bio, 'email' => $user->email,
        'id' => $otherPlayer->id, 'user_id' => $otherPlayer->id,
        'username' => 'changed_username', 'level' => 99, 'experience' => 90000,
        'exhibition_wins' => 999, 'competition_wins' => 999,
        'walks_count' => 999, 'trainings_count' => 999, 'pets_count' => 9,
    ])->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));

    $this->assertDatabaseHas('users', [
        'id' => $user->id, 'name' => 'Новое имя', 'bio' => $bio,
        'username' => $user->username, 'level' => 4, 'experience' => 0,
        'exhibition_wins' => 0, 'competition_wins' => 0, 'walks_count' => 15, 'trainings_count' => 0,
    ]);
    expect($otherPlayer->fresh()->bio)->toBe('Другая карточка');

    $this->actingAs($otherPlayer)->get(route('players.show', $user->username))
        ->assertInertia(fn (Assert $page) => $page
            ->where('player.name', 'Новое имя')->where('player.bio', $bio)
        );
});

test('players can clear their bio', function (?string $bio) {
    $user = User::factory()->create(['bio' => 'Старое описание']);

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name, 'email' => $user->email, 'bio' => $bio,
    ])->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));

    expect($user->fresh()->bio)->toBeNull();
})->with(['empty text' => [''], 'null' => [null]]);

test('an omitted bio is preserved when other profile fields change', function () {
    $user = User::factory()->create(['bio' => 'Сохранённое описание']);

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => 'Новое имя', 'email' => $user->email,
    ])->assertSessionHasNoErrors();

    expect($user->fresh()->bio)->toBe('Сохранённое описание');
});

test('invalid bios are rejected without changing the profile', function (mixed $bio, string $message) {
    $user = User::factory()->create(['bio' => 'Сохранённое описание']);

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => 'Не сохранять', 'email' => $user->email, 'bio' => $bio,
    ])->assertSessionHasErrors(['bio' => $message]);

    $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => $user->name, 'bio' => 'Сохранённое описание']);
})->with([
    'too long' => [str_repeat('я', 1001), 'Поле «о себе» не должно быть длиннее 1000 символов.'],
    'not text' => [['text'], 'Поле «о себе» должно быть строкой.'],
]);
