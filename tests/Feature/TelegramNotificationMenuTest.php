<?php

use App\Http\Telegram\Menus\NotificationMenu;
use App\Models\User;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Telegram\DTO\TelegramScreen;
use App\Notifications\SystemNotification;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/** @return list<array<string, mixed>> */
function telegramNotificationCommands(TelegramScreen $screen, ?string $action = null): array
{
    return collect($screen->rows)->flatten(1)->pluck('command')->filter()
        ->when($action !== null, fn ($commands) => $commands->where('action', $action))->values()->all();
}

test('telegram notifications show localized escaped content without reading it on open', function () {
    $this->travelTo('2026-11-02 12:00:00 UTC');
    $user = User::factory()->create(['timezone' => 'Europe/Moscow']);
    $user->notify(SystemNotification::siteUpdate(
        ['ru' => '<Новости>', 'en' => '<News>'],
        ['ru' => '<Обновление> & подробности', 'en' => '<Update> & details'],
    ));
    $notification = $user->notifications()->sole();
    $menu = app(NotificationMenu::class);

    $index = $menu->index($user, 'ru', ['action' => 'notifications']);
    $screen = $menu->show($user, 'ru', telegramNotificationCommands($index, 'notifications.show')[0]);

    expect($index->text)->toContain('Уведомления', 'Непрочитано: 1');
    expect($index->rows[0][0]['text'])->toBe('02.11 15:00 MSK · <Новости>');
    expect($screen->text)->toContain('&lt;Новости&gt;', '&lt;Обновление&gt; &amp; подробности', '02.11 15:00 MSK')
        ->not->toContain('<Новости>', '<Обновление>', 'News', 'Update');
    expect(telegramNotificationCommands($screen, 'notifications.read'))->toBe([['action' => 'notifications.read']]);
    expect($screen->state)->toBe(['notification' => ['notificationId' => $notification->id, 'cursor' => null]]);
    $this->assertDatabaseHas('notifications', ['id' => $notification->id, 'read_at' => null]);
});

test('telegram notification cursors keep older unread items available after new arrivals and reads', function () {
    $this->freezeSecond();
    $user = User::factory()->create();
    $ids = [];
    foreach (range(1, 8) as $number) {
        $user->notify(SystemNotification::siteUpdate(['ru' => 'Новость '.$number, 'en' => 'News '.$number], ['ru' => 'Текст', 'en' => 'Message']));
        $ids[] = $user->notifications()->first()->id;
        $this->travel(1)->second();
    }
    $user->notify(SystemNotification::passwordChanged());
    $user->notifications()->first()->markAsRead();
    $other = User::factory()->create();
    $other->notify(SystemNotification::passwordReset());
    $foreignId = $other->notifications()->sole()->id;
    $unrelated = $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'game.other', 'data' => []]);
    $menu = app(NotificationMenu::class);
    $first = $menu->index($user, 'en', ['action' => 'notifications']);
    $next = collect(telegramNotificationCommands($first, 'notifications'))->firstWhere('cursor');
    $detail = $menu->show($user, 'en', telegramNotificationCommands($first, 'notifications.show')[0]);
    $menu->read($user, 'en', $detail->state);
    $this->travel(1)->second();
    $user->notify(SystemNotification::passwordReset());

    $second = $menu->index($user, 'en', $next);
    $previous = collect(telegramNotificationCommands($second, 'notifications'))->firstWhere('cursor');
    $back = $menu->index($user, 'en', $previous);

    expect(array_column(telegramNotificationCommands($first, 'notifications.show'), 'notificationId'))->toBe(array_reverse(array_slice($ids, 2)))
        ->not->toContain($foreignId, $unrelated->id);
    expect($first->text)->toContain('Unread: 8');
    expect(array_column(telegramNotificationCommands($second, 'notifications.show'), 'notificationId'))->toBe([$ids[1], $ids[0]]);
    expect(telegramNotificationCommands($back, 'notifications.show'))->toHaveCount(6);
    expect(telegramNotificationCommands($second, 'notifications.show')[0]['cursor'])->toBe($next['cursor']);
    $detail = $menu->show($user, 'en', telegramNotificationCommands($second, 'notifications.show')[0]);
    expect(telegramNotificationCommands($detail, 'notifications'))->toContain(['action' => 'notifications', 'cursor' => $next['cursor']]);
});

test('telegram notification cursors preserve every tied timestamp record in UUID order in both directions', function () {
    $this->freezeSecond();
    $user = User::factory()->create();
    $ids = [
        '00000000-0000-4000-8000-000000000001',
        '00000000-0000-4000-8000-000000000002',
        '00000000-0000-4000-8000-000000000003',
        '00000000-0000-4000-8000-000000000004',
        '00000000-0000-4000-8000-000000000005',
        '00000000-0000-4000-8000-000000000006',
        '00000000-0000-4000-8000-000000000007',
    ];
    foreach ([3, 1, 6, 0, 5, 2, 4] as $index) {
        $user->notifications()->create([
            'id' => $ids[$index],
            'type' => SystemNotification::class,
            'data' => SystemNotification::passwordChanged()->toDatabase($user),
        ]);
    }
    $menu = app(NotificationMenu::class);

    $first = $menu->index($user, 'en', ['action' => 'notifications']);
    $next = collect(telegramNotificationCommands($first, 'notifications'))->firstWhere('cursor');
    $second = $menu->index($user, 'en', $next);
    $previous = collect(telegramNotificationCommands($second, 'notifications'))->firstWhere('cursor');
    $back = $menu->index($user, 'en', $previous);

    expect(array_column(telegramNotificationCommands($first, 'notifications.show'), 'notificationId'))
        ->toBe([$ids[6], $ids[5], $ids[4], $ids[3], $ids[2], $ids[1]]);
    expect(array_column(telegramNotificationCommands($second, 'notifications.show'), 'notificationId'))->toBe([$ids[0]]);
    expect(array_column(telegramNotificationCommands($back, 'notifications.show'), 'notificationId'))
        ->toBe([$ids[6], $ids[5], $ids[4], $ids[3], $ids[2], $ids[1]]);
});

test('telegram notifications mark only the selected item read and keep repeated reads idempotent', function () {
    $this->freezeSecond();
    $user = User::factory()->create();
    $user->notify(SystemNotification::passwordChanged());
    $notification = $user->notifications()->sole();
    $user->notify(SystemNotification::passwordReset());
    $menu = app(NotificationMenu::class);
    $detail = $menu->show($user, 'en', ['notificationId' => $notification->id]);

    $screen = $menu->read($user, 'en', $detail->state);
    $readAt = $notification->fresh()->read_at->toISOString();
    $this->travel(1)->minute();
    $again = $menu->read($user, 'en', $detail->state);

    expect($screen->text)->toContain('Marked as read.', 'Read:');
    expect($again->text)->toBe($screen->text);
    expect(telegramNotificationCommands($screen, 'notifications.read'))->toBeEmpty();
    expect($screen->state)->toBeEmpty();
    expect($notification->fresh()->read_at->toISOString())->toBe($readAt);
    expect(telegramNotificationCommands($menu->index($user, 'en', ['action' => 'notifications']), 'notifications.show'))->toHaveCount(1);
    $this->assertDatabaseCount('notifications', 2);
});

test('telegram notifications refuse foreign and unrelated records for details and reads', function (string $kind) {
    $user = User::factory()->create();
    $owner = $kind === 'foreign' ? User::factory()->create() : $user;
    $record = $owner->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => $kind === 'foreign' ? SystemNotification::class : 'game.other',
        'data' => ['kind' => 'site_update', 'title' => ['en' => 'Private'], 'message' => ['en' => 'Private message']],
    ]);
    $menu = app(NotificationMenu::class);

    expect(fn () => $menu->show($user, 'en', ['notificationId' => $record->id]))->toThrow(ModelNotFoundException::class);
    expect(fn () => $menu->read($user, 'en', ['notification' => ['notificationId' => $record->id, 'cursor' => null]]))->toThrow(ModelNotFoundException::class);

    $this->assertDatabaseHas('notifications', ['id' => $record->id, 'read_at' => null]);
})->with(['foreign player' => 'foreign', 'other notification type' => 'unrelated']);

test('telegram notifications reject missing or malformed read state before changing a record', function (array $draft) {
    $user = User::factory()->create();
    $user->notify(SystemNotification::passwordChanged());
    $record = $user->notifications()->sole();
    $state = $draft === [] ? [] : ['notification' => ['notificationId' => $record->id, 'cursor' => null, ...$draft]];

    expect(fn () => app(NotificationMenu::class)->read($user, 'en', $state))->toThrow(PetUnavailable::class, 'telegram.errors.stale');

    $this->assertDatabaseHas('notifications', ['id' => $record->id, 'read_at' => null]);
})->with([
    'missing draft' => [[]],
    'invalid notification id' => [['notificationId' => 'not-a-uuid']],
    'non-scalar notification id' => [['notificationId' => []]],
    'non-scalar cursor' => [['cursor' => []]],
    'oversized cursor' => [['cursor' => str_repeat('a', 2049)]],
    'invalid encoded cursor' => [['cursor' => 'invalid-cursor']],
]);

test('telegram notification lists reject malformed cursor data instead of querying invalid values', function (mixed $cursor) {
    $user = User::factory()->create();

    expect(fn () => app(NotificationMenu::class)->index($user, 'en', ['action' => 'notifications', 'cursor' => $cursor]))
        ->toThrow(PetUnavailable::class, 'telegram.errors.stale');
})->with([
    'non-scalar cursor' => [[]],
    'invalid encoding' => ['invalid-cursor'],
    'missing columns' => [base64_encode(json_encode(['_pointsToNextItems' => true]))],
    'non-scalar direction' => [base64_encode(json_encode(['id' => '00000000-0000-4000-8000-000000000000', 'created_at' => '2026-11-02 12:00:00', '_pointsToNextItems' => []]))],
    'non-boolean direction' => [base64_encode(json_encode(['id' => '00000000-0000-4000-8000-000000000000', 'created_at' => '2026-11-02 12:00:00', '_pointsToNextItems' => 'true']))],
    'invalid id' => [base64_encode(json_encode(['id' => 'invalid', 'created_at' => '2026-11-02 12:00:00', '_pointsToNextItems' => true]))],
    'invalid date' => [base64_encode(json_encode(['id' => '00000000-0000-4000-8000-000000000000', 'created_at' => '2026-13-32 12:00:00', '_pointsToNextItems' => true]))],
]);

test('telegram notifications preserve status and safe output when the message is long', function () {
    $this->freezeSecond();
    $user = User::factory()->create();
    $user->notify(SystemNotification::siteUpdate(['ru' => 'Новость', 'en' => '<News>'], ['ru' => 'Текст', 'en' => str_repeat('<&', 3000)]));
    $record = $user->notifications()->sole();
    $record->update(['data' => [...$record->data, 'actionUrl' => 'javascript:alert(1)']]);
    $menu = app(NotificationMenu::class);
    $detail = $menu->show($user, 'en', ['notificationId' => $record->id]);

    $screen = $menu->read($user, 'en', $detail->state);

    expect($screen->text)->toContain('Marked as read.', 'Read:', '&lt;News&gt;', '&lt;&amp;')->not->toContain('javascript:', '<News>', '<&');
    expect(mb_strlen($screen->text))->toBeLessThanOrEqual(4000);
    expect(collect($screen->rows)->flatten(1)->whereNotNull('url'))->toBeEmpty();
    expect($screen->text)->not->toMatch('/&[^;]*$/u');
});

test('telegram notifications have clear empty feedback and a way to refresh or leave', function () {
    $user = User::factory()->create();

    $screen = app(NotificationMenu::class)->index($user, 'en', ['action' => 'notifications']);

    expect($screen->text)->toContain('Notifications', 'Unread: 0', 'No unread notifications.');
    expect(telegramNotificationCommands($screen, 'notifications.show'))->toBeEmpty();
    expect(telegramNotificationCommands($screen, 'notifications'))->toBe([['action' => 'notifications']]);
    expect(telegramNotificationCommands($screen, 'home'))->not->toBeEmpty();
});
