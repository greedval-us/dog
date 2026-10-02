<?php

use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('notification endpoints require authentication', function (string $method, string $routeName, array $parameters) {
    $this->{$method}(route($routeName, $parameters))->assertUnauthorized();
})->with([
    'list' => ['getJson', 'notifications.index', []],
    'read one' => ['patchJson', 'notifications.update', ['notification' => '92ec7ea1-69f5-49e2-8a65-5d82b7135a12']],
    'read all' => ['patchJson', 'notifications.read-all', []],
]);

test('players only receive their own system notifications', function () {
    $this->freezeTime();
    $user = User::factory()->create();
    $other = User::factory()->create();
    $user->notify(SystemNotification::login('192.0.2.10'));
    $this->travel(1)->seconds();
    $user->notify(SystemNotification::passwordChanged());
    $other->notify(SystemNotification::login('192.0.2.20'));
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'game.pet-hungry',
        'data' => ['message' => 'Your dog is hungry'],
    ]);

    $this->actingAs($user)->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonCount(2, 'items')
        ->assertJsonPath('items.0.kind', 'password_changed')
        ->assertJsonPath('items.1.kind', 'login')
        ->assertJsonPath('items.1.message.ru', 'Ты вошёл в свой аккаунт DogLive. IP-адрес: 192.0.2.10.')
        ->assertJsonPath('items.1.message.en', 'You signed in to your DogLive account. IP address: 192.0.2.10.')
        ->assertJsonPath('items.1.readAt', null)
        ->assertJsonPath('unreadCount', 2)
        ->assertJsonPath('nextCursor', null)
        ->assertJsonMissing(['message' => 'Your dog is hungry']);
});

test('the header shares only the system unread count and guests see zero', function () {
    $user = User::factory()->create();
    $user->notify(SystemNotification::passwordChanged());
    $user->notify(SystemNotification::passwordReset());
    $user->notifications()->first()->markAsRead();
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'game.pet-hungry',
        'data' => [],
    ]);

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('systemNotificationUnreadCount', 0));
    $this->actingAs($user)->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('systemNotificationUnreadCount', 1));
});

test('an empty notification history returns an empty list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson(route('notifications.index'))
        ->assertExactJson(['items' => [], 'nextCursor' => null, 'unreadCount' => 0]);
});

test('reading a notification is persistent and repeated reads keep the original time', function () {
    $this->freezeSecond();
    $user = User::factory()->create();
    $user->notify(SystemNotification::passwordChanged());
    $notification = $user->notifications()->sole();
    $readAt = now()->toISOString();

    $this->actingAs($user)->patchJson(route('notifications.update', $notification->id))
        ->assertExactJson(['readAt' => $readAt, 'unreadCount' => 0]);

    expect($notification->refresh()->read_at->toISOString())->toBe($readAt);
    $this->travel(1)->minutes();
    $this->patchJson(route('notifications.update', $notification->id))
        ->assertExactJson(['readAt' => $readAt, 'unreadCount' => 0]);
    expect($notification->refresh()->read_at->toISOString())->toBe($readAt);
    $this->getJson(route('notifications.index'))->assertJsonPath('items.0.readAt', $readAt);
});

test('a player cannot read another player or game notification', function (string $kind) {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $other->notify(SystemNotification::passwordChanged());
    $foreign = $other->notifications()->sole();
    $game = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'game.pet-hungry',
        'data' => [],
    ]);
    $id = match ($kind) {
        'foreign' => $foreign->id,
        'game' => $game->id,
        'missing' => (string) Str::uuid(),
    };

    $this->actingAs($user)->patchJson(route('notifications.update', $id))->assertNotFound();

    expect($foreign->refresh()->read_at)->toBeNull();
    expect($game->refresh()->read_at)->toBeNull();
})->with(['foreign', 'game', 'missing']);

test('reading all changes only the current player unread system notifications', function () {
    $this->freezeSecond();
    $user = User::factory()->create();
    $other = User::factory()->create();
    $user->notify(SystemNotification::passwordChanged());
    $oldRead = $user->notifications()->sole();
    $oldRead->markAsRead();
    $oldReadAt = $oldRead->read_at->toISOString();
    $this->travel(1)->minutes();
    $user->notify(SystemNotification::login(null));
    $unread = $user->unreadNotifications()->sole();
    $other->notify(SystemNotification::passwordChanged());
    $game = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'game.pet-hungry',
        'data' => [],
    ]);
    $readAt = now()->toISOString();

    $this->actingAs($user)->patchJson(route('notifications.read-all'))
        ->assertExactJson(['readAt' => $readAt, 'unreadCount' => 0]);

    expect($unread->refresh()->read_at->toISOString())->toBe($readAt);
    expect($oldRead->refresh()->read_at->toISOString())->toBe($oldReadAt);
    expect($game->refresh()->read_at)->toBeNull();
    expect($other->unreadNotifications()->count())->toBe(1);
});

test('older notifications remain accessible when new notifications arrive', function () {
    $this->freezeTime();
    $user = User::factory()->create();
    $ids = [];
    for ($i = 0; $i < 23; $i++) {
        $user->notify(SystemNotification::login(null));
        $ids[] = $user->notifications()->first()->id;
        $this->travel(1)->seconds();
    }

    $firstPage = $this->actingAs($user)->getJson(route('notifications.index'))
        ->assertJsonCount(20, 'items')
        ->assertJsonPath('items.0.id', $ids[22]);
    $user->notify(SystemNotification::passwordChanged());

    $secondPage = $this->getJson(route('notifications.index', ['cursor' => $firstPage->json('nextCursor')]))
        ->assertJsonCount(3, 'items')
        ->assertJsonPath('nextCursor', null)
        ->assertJsonPath('unreadCount', 24);

    expect(array_column($secondPage->json('items'), 'id'))->toBe([$ids[2], $ids[1], $ids[0]]);
});

test('oversized notification cursors are rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson(route('notifications.index', ['cursor' => str_repeat('a', 2049)]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cursor');
});

test('site updates are published to players in both languages', function () {
    $users = User::factory()->count(2)->sequence(['locale' => 'ru'], ['locale' => 'en'])->create();

    $this->artisan('notifications:announce', [
        '--title-ru' => 'Обновление DogLive',
        '--message-ru' => 'Добавлены системные уведомления.',
        '--title-en' => 'DogLive update',
        '--message-en' => 'System notifications are now available.',
    ])->expectsOutput('Announcement published to 2 players.')->assertSuccessful();

    foreach ($users as $user) {
        $notification = $user->notifications()->sole();
        expect($notification->data)->toBe([
            'kind' => 'site_update',
            'title' => ['ru' => 'Обновление DogLive', 'en' => 'DogLive update'],
            'message' => ['ru' => 'Добавлены системные уведомления.', 'en' => 'System notifications are now available.'],
        ]);
        expect($notification->read_at)->toBeNull();
    }
});

test('invalid site announcements create no notifications', function (array $invalidOptions) {
    User::factory()->create();
    $options = [
        '--title-ru' => 'Обновление',
        '--message-ru' => 'Сайт обновлён.',
        '--title-en' => 'Update',
        '--message-en' => 'The site was updated.',
        ...$invalidOptions,
    ];

    $this->artisan('notifications:announce', $options)->assertFailed();

    $this->assertDatabaseCount('notifications', 0);
})->with([
    'missing English message' => [['--message-en' => '   ']],
    'oversized title' => [['--title-ru' => str_repeat('a', 161)]],
]);

test('admin sign-ins and non-player accounts do not produce player notifications', function () {
    $user = User::factory()->create();

    event(new Login('moonshine', $user, false));
    event(new Login('web', new GenericUser(['id' => 1]), false));

    $this->assertDatabaseCount('notifications', 0);
});
