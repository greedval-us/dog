<?php

use App\Models\User;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('guests see Russian by default', function () {
    $this->get(route('home'))
        ->assertSee('lang="ru"', false)
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'ru'));
});

test('guests can save either supported language in a cookie', function (string $locale) {
    $this->from(route('home'))->post(route('locale.update'), ['locale' => $locale])
        ->assertRedirect(route('home'))
        ->assertCookie('locale', $locale);
})->with(['ru', 'en']);

test('guest pages use the saved language and ignore unsupported cookies', function (string $cookie, string $expected) {
    $this->withCookie('locale', $cookie)->get(route('login'))
        ->assertSee('lang="'.$expected.'"', false)
        ->assertInertia(fn (Assert $page) => $page->where('locale', $expected));
})->with([
    'English' => ['en', 'en'],
    'Russian' => ['ru', 'ru'],
    'unsupported' => ['de', 'ru'],
]);

test('the account language takes precedence over the browser cookie', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $this->actingAs($user)->withCookie('locale', 'ru')->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
});

test('changing language updates only the current account preference', function () {
    $user = User::factory()->create(['locale' => 'ru', 'coins' => 0]);
    $otherUser = User::factory()->create(['locale' => 'ru']);

    $this->actingAs($user)->from(route('profile.edit'))->post(route('locale.update'), [
        'locale' => 'en',
        'id' => $otherUser->id,
        'coins' => 999,
    ])->assertRedirect(route('profile.edit'))->assertCookie('locale', 'en');

    $this->assertDatabaseHas('users', ['id' => $user->id, 'locale' => 'en', 'coins' => 0]);
    $this->assertDatabaseHas('users', ['id' => $otherUser->id, 'locale' => 'ru']);
});

test('invalid language choices do not change the account or cookie', function (mixed $locale, string $message) {
    $user = User::factory()->create(['locale' => 'ru']);

    $this->actingAs($user)->post(route('locale.update'), ['locale' => $locale])
        ->assertSessionHasErrors(['locale' => $message])
        ->assertCookieMissing('locale');

    $this->assertDatabaseHas('users', ['id' => $user->id, 'locale' => 'ru']);
})->with([
    'missing' => [null, 'Поле «язык» обязательно для заполнения.'],
    'unsupported' => ['de', 'Выбранное значение поля «язык» недопустимо.'],
    'array' => [['en'], 'Поле «язык» должно быть строкой.'],
]);

test('registration remembers the language selected by the guest', function () {
    $this->withCookie('locale', 'en')->post(route('register.store'), [
        'name' => 'Test Player',
        'username' => 'english_player',
        'email' => 'player@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('users', ['username' => 'english_player', 'locale' => 'en']);
});

test('login errors use the selected language', function (string $locale, string $message) {
    $this->withCookie('locale', $locale)->post(route('login.store'), [
        'email' => 'unknown@example.com',
        'password' => 'incorrect-password',
    ])->assertSessionHasErrors(['email' => $message]);
})->with([
    'Russian' => ['ru', 'Неверная почта или пароль.'],
    'English' => ['en', 'These credentials do not match our records.'],
]);

test('password reset errors use the selected language', function (string $locale, string $message) {
    $this->withCookie('locale', $locale)->post(route('password.email'), [
        'email' => 'unknown@example.com',
    ])->assertSessionHasErrors(['email' => $message]);
})->with([
    'Russian' => ['ru', 'Пользователь с такой почтой не найден.'],
    'English' => ['en', "We can't find a user with that email address."],
]);

test('password reset emails use the account language rather than the browser language', function (string $locale, string $subject, string $greeting) {
    $user = User::factory()->create(['locale' => $locale]);
    $mail = null;
    Event::listen(MessageSending::class, function (MessageSending $event) use (&$mail): bool {
        $mail = $event->message;

        return false;
    });

    $this->withCookie('locale', $locale === 'ru' ? 'en' : 'ru')
        ->post(route('password.email'), ['email' => $user->email])
        ->assertSessionHasNoErrors();

    expect($mail)->not->toBeNull();
    expect($mail->getSubject())->toBe($subject);
    expect($mail->getHtmlBody())->toContain($greeting);
})->with([
    'Russian' => ['ru', 'Восстановление пароля', 'Привет!'],
    'English' => ['en', 'Reset your password', 'Hello!'],
]);

test('profile confirmation uses the account language', function (string $locale, string $message) {
    $user = User::factory()->create(['locale' => $locale]);

    $this->actingAs($user)->followingRedirects()->patch(route('profile.update'), [
        'name' => 'Updated Name',
        'email' => $user->email,
    ])->assertInertia(fn (Assert $page) => $page->hasFlash('toast.message', $message));
})->with([
    'Russian' => ['ru', 'Профиль обновлён.'],
    'English' => ['en', 'Profile updated.'],
]);
