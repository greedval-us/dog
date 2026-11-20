<?php

use App\Jobs\SendTelegramNotification;
use App\Models\TelegramAccount;
use App\Models\User;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Players\Services\PlayerNotifications;
use App\Notifications\SystemNotification;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

test('a player receives a localized competition result after commit and a retry keeps a single chat message', function () {
    $user = User::factory()->create(['locale' => 'en']);
    $account = TelegramAccount::factory()->for($user)->create(['menu_message_id' => 17]);
    config(['app.url' => 'https://doglive.test']);
    Http::preventStrayRequests();
    Http::fake(['api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 521]])]);

    DB::transaction(function () use ($user): void {
        app(PlayerNotifications::class)->eventCompleted($user, 24, 'agility', false, 'Rex <&>');
        Http::assertNothingSent();
    });
    $notification = $user->notifications()->sole();
    expect($notification->data['telegramMessageId'])->toBe(521);
    app()->call([new SendTelegramNotification($account->id, $notification->id), 'handle']);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request['chat_id'] === $account->telegram_user_id
        && str_contains($request['text'], 'Competition results are ready')
        && str_contains($request['text'], 'Rex &lt;&amp;&gt;')
        && empty($request['disable_notification'])
        && $request['reply_markup']['inline_keyboard'][0][0]['url'] === 'https://doglive.test'.route('game-events.show', 24, absolute: false));
    expect($notification->read_at)->toBeNull();
    expect($account->fresh()->menu_message_id)->toBe(17);
});

test('rolling back a gameplay event also cancels the telegram alert', function () {
    $account = TelegramAccount::factory()->create();
    Http::preventStrayRequests();
    Http::fake(['api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 521]])]);

    DB::beginTransaction();
    app(PlayerNotifications::class)->litterBorn($account->user, 2);
    DB::rollBack();

    Http::assertNothingSent();
    expect($account->user->notifications()->count())->toBe(0);
});

test('only linked players gameplay notifications enter the telegram queue', function () {
    Queue::fake([SendTelegramNotification::class]);
    $account = TelegramAccount::factory()->create();
    $writer = app(PlayerNotifications::class);
    $user = $account->user;

    $writer->achievementUnlocked($user, ['en' => 'A', 'ru' => 'А']);
    $writer->petDied($user, 22, 'Rex');
    $writer->eventCompleted($user, 12, 'agility', false, 'Rex');
    $writer->eventCompleted($user, 12, 'conformation', true, 'Rex');
    $writer->litterBorn($user, 3);
    $writer->petSold($user, 23, 'Rex', 500);
    $writer->petSold(User::factory()->create(), 23, 'Rex', 500);
    $user->notify(SystemNotification::login('192.0.2.50'));
    $user->notify(SystemNotification::passwordReset());
    $user->notify(SystemNotification::passwordChanged());
    $user->notify(SystemNotification::siteUpdate(['ru' => 'А', 'en' => 'A'], ['ru' => 'Б', 'en' => 'B']));

    $pushed = Queue::pushed(SendTelegramNotification::class);
    expect($pushed)->toHaveCount(6);
    expect($pushed->pluck('accountId')->unique()->all())->toBe([$account->id]);
    $kinds = $user->notifications()->whereKey($pushed->pluck('notificationId'))->get()->pluck('data.kind')->all();
    expect($kinds)->toEqualCanonicalizing(['achievement', 'pet_death', 'competition_result', 'show_result', 'breeding_success', 'pet_sold']);
});

test('a queued alert rechecks its original active account before contacting telegram', function (string $change) {
    Queue::fake([SendTelegramNotification::class]);
    $account = TelegramAccount::factory()->create();
    app(PlayerNotifications::class)->litterBorn($account->user, 2);
    $job = Queue::pushed(SendTelegramNotification::class)->sole();
    if ($change === 'relinked') {
        $account->delete();
        TelegramAccount::factory()->for($account->user)->create();
    } else {
        $account->user->forceFill(['status' => PlayerStatus::Blocked])->save();
    }
    Http::preventStrayRequests();

    app()->call([$job, 'handle']);

    Http::assertNothingSent();
})->with(['relinked', 'blocked']);

test('a rate limited notification waits for telegram and retries without unsafe action links', function () {
    Queue::fake([SendTelegramNotification::class]);
    $account = TelegramAccount::factory()->create();
    $account->user->notify(SystemNotification::gameplay('achievement', ['en' => ['name' => 'Rex'], 'ru' => ['name' => 'Рекс']], '//foreign.test'));
    $job = Queue::pushed(SendTelegramNotification::class)->sole()->withFakeQueueInteractions();
    config(['app.url' => 'https://doglive.test']);
    Http::preventStrayRequests();
    Http::fake(['api.telegram.org/bot*/sendMessage' => Http::sequence()
        ->push(['ok' => false, 'error_code' => 429, 'parameters' => ['retry_after' => 17]], 429)
        ->push(['ok' => true, 'result' => ['message_id' => 552]])]);

    app()->call([$job, 'handle']);
    $job->assertReleased(17);
    app()->call([$job, 'handle']);

    Http::assertSentCount(2);
    Http::assertNotSent(fn (Request $request): bool => isset($request['reply_markup']));
    expect($account->user->notifications()->sole()->data['telegramMessageId'])->toBe(552);
});
