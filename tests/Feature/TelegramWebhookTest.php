<?php

use App\Http\Telegram\GameMenu;
use App\Http\Telegram\MenuMessenger;
use App\Http\Telegram\TelegramDeliveryFailed;
use App\Jobs\RefreshTelegramCare;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\Skill;
use App\Models\TelegramAccount;
use App\Models\User;
use App\Models\WorkType;
use App\Modules\Pets\Actions\StartPetCare;
use App\Modules\Players\Actions\CompleteDailyWork;
use App\Modules\Telegram\Actions\ConnectTelegramAccount;
use App\Modules\Telegram\Actions\CreateTelegramLink;
use App\Modules\Telegram\DTO\TelegramScreen;
use App\Modules\Telegram\Services\MenuSession;
use App\Notifications\SystemNotification;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Models\TelegraphBot;
use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function telegramWebhookBot(): TelegraphBot
{
    $bot = TelegraphBot::factory()->create();
    config([
        'telegraph.webhook.secret' => 'test-secret',
    ]);
    Http::preventStrayRequests();
    Http::fake(['api.telegram.org/bot*/getMe' => Http::response(['ok' => true, 'result' => ['username' => 'doglive_test_bot']])]);

    return $bot;
}

/** @return array<string, mixed> */
function telegramMessageUpdate(string $text, int $sender = 123456): array
{
    return [
        'update_id' => 1,
        'message' => [
            'message_id' => 10, 'date' => now()->timestamp,
            'from' => ['id' => $sender, 'is_bot' => false, 'first_name' => 'Player'],
            'chat' => ['id' => $sender, 'type' => 'private', 'first_name' => 'Player'],
            'text' => $text,
        ],
    ];
}

/** @return array<string, mixed> */
function telegramButtonUpdate(string $data, int $sender = 123456, int $message = 900): array
{
    return [
        'update_id' => 2,
        'callback_query' => [
            'id' => '12345', 'chat_instance' => 'test', 'data' => $data,
            'from' => ['id' => $sender, 'is_bot' => false, 'first_name' => 'Player'],
            'message' => [
                'message_id' => $message, 'date' => now()->timestamp,
                'chat' => ['id' => $sender, 'type' => 'private'],
                'from' => ['id' => 987654, 'is_bot' => true, 'first_name' => 'DogLive'],
                'text' => 'Menu',
            ],
        ],
    ];
}

function fakeTelegramMenu(int $messageId = 900): void
{
    Http::preventStrayRequests();
    Telegraph::fake([
        'getMe' => ['ok' => true, 'result' => ['username' => 'doglive_test_bot']],
        'sendMessage' => ['ok' => true, 'result' => ['message_id' => $messageId]],
        'answerCallbackQuery' => ['ok' => true, 'result' => true],
        'deleteMessage' => ['ok' => true, 'result' => true],
    ]);
}

function linkedTelegramPlayer(TelegraphBot $bot): TelegramAccount
{
    $user = User::factory()->create(['locale' => 'ru']);
    $chat = TelegraphChat::factory()->for($bot, 'bot')->create(['chat_id' => '123456']);
    $link = app(CreateTelegramLink::class)->handle($user);

    return app(ConnectTelegramAccount::class)->handle($bot, $chat, '123456', 'Player', null, $link->token);
}

/** @return array<string,mixed> */
function telegramActionUpdate(TelegramAccount $account, string $action): array
{
    $account->refresh();
    foreach ($account->menu_state['commands'] as $key => $command) {
        if ($command['action'] === $action) {
            return telegramButtonUpdate('action:tap;k:'.$key.';v:'.$account->menu_revision.';s:'.$account->id, message: $account->menu_message_id);
        }
    }

    throw new RuntimeException('The current Telegram screen has no '.$action.' action.');
}

test('webhook rejects a missing secret before handling an update', function () {
    $bot = telegramWebhookBot();
    fakeTelegramMenu();

    $this->postJson(route('telegraph.webhook', $bot->token), telegramMessageUpdate('/menu'))->assertForbidden();

    Telegraph::assertNothingSent();
    $this->assertDatabaseCount('telegram_accounts', 0);
});

test('webhook ignores group chats and spoofed private senders', function (bool $group) {
    $bot = telegramWebhookBot();
    fakeTelegramMenu();
    $update = telegramMessageUpdate('/start');
    $update['message']['chat']['type'] = $group ? 'group' : 'private';
    $update['message']['chat']['id'] = -123;

    $this->postJson(route('telegraph.webhook', $bot->token), $update, ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'])
        ->assertNoContent();

    Telegraph::assertNothingSent();
    $this->assertDatabaseCount('telegraph_chats', 0);
})->with([true, false]);

test('webhook does not expose inherited handler commands', function () {
    $bot = telegramWebhookBot();
    fakeTelegramMenu();

    $this->postJson(route('telegraph.webhook', $bot->token), telegramMessageUpdate('/chatid'), ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'])
        ->assertNoContent();

    Telegraph::assertNothingSent();
});

test('start consumes a site link and later menus replace the existing message quietly', function () {
    $bot = telegramWebhookBot();
    $user = User::factory()->create(['locale' => 'ru']);
    $link = app(CreateTelegramLink::class)->handle($user);
    fakeTelegramMenu();
    $headers = ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'];

    $this->postJson(route('telegraph.webhook', $bot->token), telegramMessageUpdate('/start '.$link->token), $headers)
        ->assertNoContent();

    $account = TelegramAccount::query()->where('user_id', $user->id)->sole();
    expect($account->menu_message_id)->toBe(900);
    Telegraph::assertSentData('sendMessage', ['chat_id' => '123456']);

    fakeTelegramMenu(901);
    $this->postJson(route('telegraph.webhook', $bot->token), telegramMessageUpdate('/menu'), $headers)->assertNoContent();
    Telegraph::assertSentData('sendMessage', ['chat_id' => '123456', 'disable_notification' => true]);
    Telegraph::assertSentData('deleteMessage', ['message_id' => 900]);
    Telegraph::assertNotSentData('editMessageText', []);
    expect($account->fresh()->menu_message_id)->toBe(901);
});

test('stale and malformed buttons cannot execute gameplay commands', function (string $data) {
    $bot = telegramWebhookBot();
    $account = linkedTelegramPlayer($bot);
    $account->forceFill(['menu_message_id' => 900, 'menu_revision' => 2, 'menu_state' => ['commands' => ['0' => ['action' => 'home']], 'data' => []]])->save();
    fakeTelegramMenu();

    $this->postJson(route('telegraph.webhook', $bot->token), telegramButtonUpdate(sprintf($data, $account->id)), ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'])
        ->assertNoContent();

    Telegraph::assertSentData('answerCallbackQuery', ['callback_query_id' => '12345']);
    $this->assertDatabaseCount('pet_care_actions', 0);
    $this->assertDatabaseCount('item_purchases', 0);
})->with(['action:tap;k:0;v:1;s:%d', 'action:tap;k:0;v:2', 'action:tap;k:0;v:2;s:0', 'action:tap;k:0;v:2;s:10000000000000000000', 'malformed', 'action:chatid']);

test('telegram configuration does not print the bot token and registers an explicit secret', function () {
    config([
        'telegraph.webhook.secret' => 'test-secret',
        'telegraph.webhook.domain' => 'https://example.test',
    ]);
    $bot = TelegraphBot::factory()->create(['token' => '987654:private-test-token', 'name' => 'DogLive companion']);
    Http::preventStrayRequests();
    Telegraph::fake([
        'getMe' => ['ok' => true, 'result' => ['id' => 987654, 'is_bot' => true, 'username' => 'doglive_test_bot']],
        'setWebhook' => ['ok' => true, 'result' => true],
        'setMyCommands' => ['ok' => true, 'result' => true],
    ]);

    $this->artisan('telegram:configure', ['--register-webhook' => true])
        ->doesntExpectOutputToContain('private-test-token')
        ->assertSuccessful();

    Telegraph::assertSentData('setWebhook', ['secret_token' => 'test-secret', 'allowed_updates' => ['message', 'callback_query']]);
    $this->assertDatabaseCount('telegraph_bots', 1);
    expect($bot->fresh()->name)->toBe('DogLive companion');
});

test('a new menu remains current when deleting the old message fails and its keyboard is retired', function () {
    $bot = telegramWebhookBot();
    $account = linkedTelegramPlayer($bot);
    $account->forceFill(['menu_message_id' => 800])->save();
    Http::preventStrayRequests();
    Telegraph::fake([
        'sendMessage' => ['ok' => true, 'result' => ['message_id' => 900]],
        'deleteMessage' => ['ok' => false, 'error_code' => 400, 'description' => 'Bad Request: message cannot be deleted'],
        'editMessageReplyMarkup' => ['ok' => true, 'result' => true],
    ]);

    app(MenuMessenger::class)->publish($account, new TelegramScreen('Updated', [[['text' => 'Home', 'command' => ['action' => 'home']]]]));

    expect($account->fresh()->menu_message_id)->toBe(900);
    Telegraph::assertSentData('deleteMessage', ['message_id' => 800]);
    Telegraph::assertSentData('editMessageReplyMarkup', ['message_id' => 800]);
    Telegraph::assertNotSentData('editMessageText', []);
});

test('background care completion preserves player activity and does not overwrite a newer screen', function () {
    $this->freezeSecond();
    $bot = telegramWebhookBot();
    $account = linkedTelegramPlayer($bot);
    $pet = Pet::factory()->for($account->user)->create(['hydration' => 10]);
    $care = app(StartPetCare::class)->handle($account->user, $pet->id, 'water', [], (string) Str::uuid());
    $lastSeen = $account->user->fresh()->last_seen_at?->toISOString();
    $account->forceFill(['menu_revision' => 4])->save();
    $this->travelTo($care->ends_at->addSecond());
    fakeTelegramMenu();

    app()->call([new RefreshTelegramCare($account->id, $pet->id, $care->token, 3), 'handle']);

    expect(PetCareAction::query()->findOrFail($care->id)->completed_at)->not->toBeNull();
    expect($account->user->fresh()->last_seen_at?->toISOString())->toBe($lastSeen);
    Telegraph::assertNothingSent();
});

test('telegram rate limits preserve retry timing and leave the previous menu intact', function () {
    $bot = telegramWebhookBot();
    $account = linkedTelegramPlayer($bot);
    $account->forceFill(['menu_message_id' => 900])->save();
    Http::preventStrayRequests();
    Telegraph::fake(['sendMessage' => ['ok' => false, 'error_code' => 429, 'parameters' => ['retry_after' => 23]]]);

    try {
        app(MenuMessenger::class)->publish($account, new TelegramScreen('Menu', []));
        $this->fail('Rate limiting must defer delivery.');
    } catch (TelegramDeliveryFailed $exception) {
        expect($exception->retryAfter)->toBe(23);
    }
    expect($account->fresh()->menu_revision)->toBe(0);
    expect($account->fresh()->menu_message_id)->toBe(900);
    Telegraph::assertSentData('sendMessage', ['chat_id' => '123456', 'disable_notification' => true]);
    Telegraph::assertNotSentData('deleteMessage', []);
});

test('a current button resolves only its saved command and acknowledges string callback ids', function () {
    $bot = telegramWebhookBot();
    $account = linkedTelegramPlayer($bot);
    $account->forceFill(['menu_message_id' => 900, 'menu_revision' => 2, 'menu_state' => ['commands' => ['0' => ['action' => 'profile']], 'data' => []]])->save();
    fakeTelegramMenu();
    $payload = telegramButtonUpdate('action:tap;k:0;v:2;s:'.$account->id);
    $payload['callback_query']['id'] = 'query-id-not-an-integer';

    $this->postJson(route('telegraph.webhook', $bot->token), $payload, ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'])->assertNoContent();

    Telegraph::assertSentData('answerCallbackQuery', ['callback_query_id' => 'query-id-not-an-integer']);
    Telegraph::assertSentData('sendMessage', ['chat_id' => '123456', 'disable_notification' => true]);
    expect($account->fresh()->menu_revision)->toBe(3);
});

test('telegram gameplay confirmations execute through saved inline callbacks once', function (string $screenAction, string $confirmAction, int $price, string $receiptTable, bool $deliveryFails) {
    $this->freezeSecond();
    $bot = telegramWebhookBot();
    $account = linkedTelegramPlayer($bot);
    $account->user->forceFill(['coins' => 500])->save();
    $pet = Pet::factory()->for($account->user)->create(['health' => 50, 'health_max' => 100, 'intelligence' => 50, 'obedience' => 50]);
    $skill = Skill::factory()->create();
    fakeTelegramMenu();
    $screen = app(GameMenu::class)->render($account->user->refresh(), ['action' => $screenAction, 'petId' => $pet->id, 'service' => 'checkup', 'skillId' => $skill->id]);
    app(MenuMessenger::class)->publish($account, $screen);
    $key = array_search($confirmAction, array_column($account->menu_state['commands'], 'action'), true);
    expect(array_column($account->menu_state['commands'], 'action'))->toContain($confirmAction);
    $update = telegramButtonUpdate('action:tap;k:'.$key.';v:'.$account->menu_revision.';s:'.$account->id);
    $headers = ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'];

    if ($deliveryFails) {
        Telegraph::fake([
            'answerCallbackQuery' => ['ok' => true, 'result' => true],
            'sendMessage' => ['ok' => false, 'error_code' => 429, 'parameters' => ['retry_after' => 23]],
        ]);
        $this->postJson(route('telegraph.webhook', $bot->token), $update, $headers)->assertStatus(500);
        expect($account->fresh()->menu_revision)->toBe($account->menu_revision);
        expect($account->user->fresh()->coins)->toBe(500 - $price);
        fakeTelegramMenu();
    }
    $this->postJson(route('telegraph.webhook', $bot->token), $update, $headers)->assertNoContent();
    $this->postJson(route('telegraph.webhook', $bot->token), $update, $headers)->assertNoContent();

    expect($account->user->fresh()->coins)->toBe(500 - $price);
    $this->assertDatabaseCount($receiptTable, 1);
    $this->assertDatabaseCount('currency_transactions', 1);
    Telegraph::assertSentData('answerCallbackQuery', ['callback_query_id' => '12345']);
    Telegraph::assertSentData('sendMessage', ['chat_id' => '123456', 'disable_notification' => true]);
})->with([
    'veterinary service' => ['clinic.service', 'clinic.perform', 60, 'veterinary_visits', false],
    'skill lesson' => ['skills.lesson', 'skills.train', 100, 'pet_skill_lessons', false],
    'retry after Telegram rate limit' => ['clinic.service', 'clinic.perform', 60, 'veterinary_visits', true],
]);

test('a damaged saved confirmation returns a localized stale screen through the webhook', function () {
    $bot = telegramWebhookBot();
    $account = linkedTelegramPlayer($bot);
    $account->forceFill(['menu_message_id' => 900, 'menu_revision' => 2, 'menu_state' => [
        'commands' => [['action' => 'clinic.perform']],
        'data' => ['veterinary' => ['petId' => 1, 'service' => []]],
    ]])->save();
    fakeTelegramMenu();

    $this->postJson(route('telegraph.webhook', $bot->token), telegramButtonUpdate('action:tap;k:0;v:2;s:'.$account->id), [
        'X-Telegram-Bot-Api-Secret-Token' => 'test-secret',
    ])->assertNoContent();

    Telegraph::assertSentData('sendMessage', ['chat_id' => '123456', 'disable_notification' => true, 'text' => trans('telegram.errors.stale', [], 'ru')]);
    expect($account->fresh()->menu_state['commands'])->toBe([['action' => 'home']]);
    $this->assertDatabaseCount('veterinary_visits', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('daily work callbacks replay the earned reward after Telegram delivery fails', function () {
    $this->freezeSecond();
    $bot = telegramWebhookBot();
    $account = linkedTelegramPlayer($bot);
    $work = WorkType::factory()->create(['coins_reward' => 50]);
    fakeTelegramMenu();
    $screen = app(GameMenu::class)->render($account->user, ['action' => 'daily_work.confirm', 'workTypeId' => $work->id]);
    app(MenuMessenger::class)->publish($account, $screen);
    $key = array_search('daily_work.complete', array_column($account->menu_state['commands'], 'action'), true);
    expect($key)->not->toBeFalse();
    $update = telegramButtonUpdate('action:tap;k:'.$key.';v:'.$account->menu_revision.';s:'.$account->id);
    $headers = ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'];
    Telegraph::fake([
        'answerCallbackQuery' => ['ok' => true, 'result' => true],
        'sendMessage' => ['ok' => false, 'error_code' => 429, 'parameters' => ['retry_after' => 23]],
    ]);

    $this->postJson(route('telegraph.webhook', $bot->token), $update, $headers)->assertStatus(500);
    fakeTelegramMenu();
    $this->postJson(route('telegraph.webhook', $bot->token), $update, $headers)->assertNoContent();
    $this->postJson(route('telegraph.webhook', $bot->token), $update, $headers)->assertNoContent();

    expect($account->user->fresh()->coins)->toBe(50);
    $this->assertDatabaseCount('work_shifts', 1);
    $this->assertDatabaseCount('currency_transactions', 1);
    Telegraph::assertSentData('sendMessage', ['chat_id' => '123456', 'disable_notification' => true]);
    Telegraph::assertNotSentData('editMessageText', []);
});

test('daily work completed on the website returns a localized refusal in Telegram', function () {
    $this->freezeSecond();
    $bot = telegramWebhookBot();
    $account = linkedTelegramPlayer($bot);
    $selected = WorkType::factory()->create();
    $other = WorkType::factory()->create(['coins_reward' => 70]);
    fakeTelegramMenu();
    $screen = app(GameMenu::class)->render($account->user, ['action' => 'daily_work.confirm', 'workTypeId' => $selected->id]);
    app(MenuMessenger::class)->publish($account, $screen);
    $key = array_search('daily_work.complete', array_column($account->menu_state['commands'], 'action'), true);
    app(CompleteDailyWork::class)->handle($account->user, $other->id);

    $this->postJson(route('telegraph.webhook', $bot->token), telegramButtonUpdate('action:tap;k:'.$key.';v:'.$account->menu_revision.';s:'.$account->id), [
        'X-Telegram-Bot-Api-Secret-Token' => 'test-secret',
    ])->assertNoContent();

    expect($account->user->fresh()->coins)->toBe(70);
    Telegraph::assertSentData('sendMessage', ['chat_id' => '123456', 'disable_notification' => true, 'text' => trans('You have already worked today. Come back tomorrow.', [], 'ru')]);
    $this->assertDatabaseCount('work_shifts', 1);
});

test('notifications use saved inline state and update the home unread count', function () {
    $bot = telegramWebhookBot();
    $account = linkedTelegramPlayer($bot);
    $account->user->notify(SystemNotification::siteUpdate(['ru' => 'Новость', 'en' => 'News'], ['ru' => 'Текст', 'en' => 'Message']));
    $notification = $account->user->notifications()->sole();
    $menu = app(GameMenu::class);
    expect(collect($menu->render($account->user)->rows)->flatten(1)->pluck('text')->all())->toContain('🔔 Уведомления (1)');
    fakeTelegramMenu();
    app(MenuMessenger::class)->publish($account, $menu->render($account->user, ['action' => 'notifications.show', 'notificationId' => $notification->id]));
    $key = array_search('notifications.read', array_column($account->menu_state['commands'], 'action'), true);

    $this->postJson(route('telegraph.webhook', $bot->token), telegramButtonUpdate('action:tap;k:'.$key.';v:'.$account->menu_revision.';s:'.$account->id), [
        'X-Telegram-Bot-Api-Secret-Token' => 'test-secret',
    ])->assertNoContent();

    expect($notification->fresh()->read_at)->not->toBeNull();
    expect(collect($menu->render($account->user)->rows)->flatten(1)->pluck('text')->all())->toContain('🔔 Уведомления')->not->toContain('🔔 Уведомления (1)');
    Telegraph::assertSentData('sendMessage', ['chat_id' => '123456', 'disable_notification' => true]);
});

test('event callbacks preserve native transaction ownership through registration editing and cancellation', function () {
    $this->freezeSecond();
    $bot = telegramWebhookBot();
    $account = linkedTelegramPlayer($bot);
    $account->user->forceFill(['coins' => 500])->save();
    $pet = Pet::factory()->for($account->user)->create();
    $event = GameEvent::factory()->create();
    $values = ['userId' => $account->user_id, 'eventId' => $event->id, 'petId' => $pet->id, 'entryId' => null,
        'fee' => 25, 'token' => (string) Str::uuid(), 'plan' => ['stages' => ['balanced', 'careful', 'bold']], 'gearIds' => []];
    $baseTransaction = DB::transactionLevel();
    $eventLockLevels = [];
    DB::listen(function (QueryExecuted $query) use (&$eventLockLevels): void {
        if (str_contains($query->sql, '"game_events"') && str_contains($query->sql, 'for update')) {
            $eventLockLevels[] = DB::transactionLevel();
        }
    });
    fakeTelegramMenu();
    $messenger = app(MenuMessenger::class);
    $messenger->publish($account, new TelegramScreen('Confirm registration', [[['text' => 'Register', 'command' => ['action' => 'events.register']]]], ['eventDraft' => $values]));
    $headers = ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'];

    $this->postJson(route('telegraph.webhook', $bot->token), telegramActionUpdate($account, 'events.register'), $headers)->assertNoContent();
    $entry = GameEventEntry::query()->where('user_id', $account->user_id)->sole();
    expect($account->user->fresh()->coins)->toBe(475);
    $values['entryId'] = $entry->id;
    $values['plan']['stages'] = ['careful', 'careful', 'careful'];
    $values['token'] = (string) Str::uuid();
    $messenger->publish($account->refresh(), new TelegramScreen('Confirm changes', [[['text' => 'Save', 'command' => ['action' => 'events.update']]]], ['eventDraft' => $values]));
    $this->postJson(route('telegraph.webhook', $bot->token), telegramActionUpdate($account, 'events.update'), $headers)->assertNoContent();
    expect($entry->fresh()->plan['stages'])->toBe(['careful', 'careful', 'careful']);
    $this->postJson(route('telegraph.webhook', $bot->token), telegramActionUpdate($account, 'events.cancel-confirm'), $headers)->assertNoContent();
    $cancel = telegramActionUpdate($account, 'events.cancel');
    $this->postJson(route('telegraph.webhook', $bot->token), $cancel, $headers)->assertNoContent();
    $this->postJson(route('telegraph.webhook', $bot->token), $cancel, $headers)->assertNoContent();

    expect($account->user->fresh()->coins)->toBe(500);
    expect($entry->fresh()->status)->toBe('cancelled');
    expect($eventLockLevels)->toBe([$baseTransaction + 1, $baseTransaction + 1, $baseTransaction + 1]);
    $this->assertDatabaseCount('currency_transactions', 2);
    Telegraph::assertSentData('sendMessage', ['chat_id' => '123456', 'disable_notification' => true]);
});

test('event registration retries after Telegram rate limits do not charge a second fee', function () {
    $this->freezeSecond();
    $bot = telegramWebhookBot();
    $account = linkedTelegramPlayer($bot);
    $account->user->forceFill(['coins' => 500])->save();
    $pet = Pet::factory()->for($account->user)->create();
    $event = GameEvent::factory()->create();
    $values = ['userId' => $account->user_id, 'eventId' => $event->id, 'petId' => $pet->id, 'entryId' => null, 'fee' => 25,
        'token' => (string) Str::uuid(), 'plan' => ['stages' => ['balanced', 'careful', 'bold']], 'gearIds' => []];
    fakeTelegramMenu();
    app(MenuMessenger::class)->publish($account, new TelegramScreen('Confirm', [[['text' => 'Register', 'command' => ['action' => 'events.register']]]], ['eventDraft' => $values]));
    $update = telegramActionUpdate($account, 'events.register');
    $headers = ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'];
    Telegraph::fake([
        'answerCallbackQuery' => ['ok' => true, 'result' => true],
        'sendMessage' => ['ok' => false, 'error_code' => 429, 'parameters' => ['retry_after' => 23]],
    ]);

    $this->postJson(route('telegraph.webhook', $bot->token), $update, $headers)->assertStatus(500);
    fakeTelegramMenu();
    $this->postJson(route('telegraph.webhook', $bot->token), $update, $headers)->assertNoContent();

    expect($account->user->fresh()->coins)->toBe(475);
    $this->assertDatabaseCount('game_event_entries', 1);
    $this->assertDatabaseCount('currency_transactions', 1);
    Telegraph::assertSentData('sendMessage', ['chat_id' => '123456', 'disable_notification' => true]);
    Telegraph::assertNotSentData('editMessageText', []);
});

test('linking sends a game menu and removes the previous welcome', function () {
    $bot = telegramWebhookBot();
    $user = User::factory()->create(['locale' => 'ru']);
    $headers = ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'];
    fakeTelegramMenu();
    $this->postJson(route('telegraph.webhook', $bot->token), telegramMessageUpdate('/start'), $headers)->assertNoContent();
    Telegraph::assertSentData('sendMessage', ['chat_id' => '123456']);
    $link = app(CreateTelegramLink::class)->handle($user);
    fakeTelegramMenu(901);

    $this->postJson(route('telegraph.webhook', $bot->token), telegramMessageUpdate('/start '.$link->token), $headers)->assertNoContent();

    Telegraph::assertSentData('sendMessage', ['chat_id' => '123456', 'disable_notification' => true]);
    Telegraph::assertSentData('deleteMessage', ['message_id' => 900]);
    Telegraph::assertNotSentData('editMessageText', []);
    $this->assertDatabaseHas('telegram_accounts', ['user_id' => $user->id, 'menu_message_id' => 901]);
});

test('unlink sends a welcome and relinking replaces it with a new game menu', function () {
    $bot = telegramWebhookBot();
    $account = linkedTelegramPlayer($bot);
    $user = $account->user;
    $account->forceFill(['menu_message_id' => 900, 'menu_revision' => 2, 'menu_state' => ['commands' => ['0' => ['action' => 'profile']], 'data' => []]])->save();
    fakeTelegramMenu(901);

    $this->actingAs($user)->delete(route('telegram.unlink'))->assertRedirect(route('profile.edit'));

    $this->assertModelMissing($account);
    Telegraph::assertSentData('sendMessage', ['chat_id' => '123456', 'disable_notification' => true, 'text' => __('telegram.welcome'),
        'reply_markup' => ['inline_keyboard' => [[['text' => __('telegram.connect_site'), 'url' => route('profile.edit')]]]]]);
    Telegraph::assertSentData('deleteMessage', ['message_id' => 900]);
    $link = app(CreateTelegramLink::class)->handle($user);
    fakeTelegramMenu(902);
    $this->postJson(route('telegraph.webhook', $bot->token), telegramMessageUpdate('/start '.$link->token), ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'])->assertNoContent();
    Telegraph::assertSentData('sendMessage', ['chat_id' => '123456', 'disable_notification' => true]);
    Telegraph::assertSentData('deleteMessage', ['message_id' => 901]);
    Telegraph::assertNotSentData('editMessageText', []);
    $this->assertDatabaseHas('telegram_accounts', ['user_id' => $user->id, 'menu_message_id' => 902]);
});

test('cleanup timeouts do not prevent scheduling completion after a replacement care menu is delivered', function () {
    $this->freezeSecond();
    $bot = telegramWebhookBot();
    $account = linkedTelegramPlayer($bot);
    $pet = Pet::factory()->for($account->user)->create(['hydration' => 10]);
    $token = (string) Str::uuid();
    $account->forceFill(['menu_message_id' => 800, 'menu_revision' => 2, 'menu_state' => [
        'commands' => ['0' => ['action' => 'care.start']],
        'data' => ['draft' => ['petId' => $pet->id, 'variant' => 'water', 'itemIds' => [], 'skipped' => [], 'token' => $token]],
    ]])->save();
    config(['queue.default' => 'redis']);
    Queue::fake([RefreshTelegramCare::class]);
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/answerCallbackQuery' => Http::response(['ok' => true, 'result' => true]),
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 900]]),
        'api.telegram.org/bot*/deleteMessage' => Http::failedConnection(),
        'api.telegram.org/bot*/editMessageReplyMarkup' => Http::failedConnection(),
    ]);

    $this->postJson(route('telegraph.webhook', $bot->token), telegramButtonUpdate('action:tap;k:0;v:2;s:'.$account->id, message: 800), ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'])->assertNoContent();

    $this->assertDatabaseHas('pet_care_actions', ['pet_id' => $pet->id, 'token' => $token, 'completed_at' => null]);
    $this->assertDatabaseHas('telegram_accounts', ['id' => $account->id, 'menu_message_id' => 900, 'menu_revision' => 3]);
    Queue::assertPushed(RefreshTelegramCare::class, fn (RefreshTelegramCare $job): bool => $job->accountId === $account->id && $job->petId === $pet->id && $job->token === $token && $job->revision === 3);
    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/sendMessage') && $request['disable_notification'] === true);
    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/deleteMessage') && $request['message_id'] === 800);
    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/editMessageReplyMarkup') && $request['message_id'] === 800);
});

test('failed unlink refresh still disconnects and old game buttons cannot start care', function () {
    $bot = telegramWebhookBot();
    $account = linkedTelegramPlayer($bot);
    $pet = Pet::factory()->for($account->user)->create(['hydration' => 10]);
    $account->forceFill(['menu_message_id' => 900, 'menu_revision' => 2, 'menu_state' => [
        'commands' => ['0' => ['action' => 'care.start']],
        'data' => ['draft' => ['petId' => $pet->id, 'variant' => 'water', 'itemIds' => [], 'skipped' => [], 'token' => (string) Str::uuid()]],
    ]])->save();
    Http::preventStrayRequests();
    Http::fake(['api.telegram.org/bot*/sendMessage' => Http::failedConnection()]);

    $this->actingAs($account->user)->delete(route('telegram.unlink'))->assertRedirect(route('profile.edit'));

    $this->assertModelMissing($account);
    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/sendMessage') && $request['disable_notification'] === true);
    Http::assertNotSent(fn ($request): bool => str_ends_with($request->url(), '/deleteMessage') && $request['message_id'] === 900);
    fakeTelegramMenu();
    $this->postJson(route('telegraph.webhook', $bot->token), telegramButtonUpdate('action:tap;k:0;v:2;s:'.$account->id), ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'])->assertNoContent();
    Telegraph::assertSentData('answerCallbackQuery', ['callback_query_id' => '12345']);
    Telegraph::assertNotSentData('sendMessage', []);
    $this->assertDatabaseCount('pet_care_actions', 0);
});

test('welcome delivery failures preserve the previous message until a replacement is sent', function (int $status) {
    $bot = telegramWebhookBot();
    $chat = TelegraphChat::factory()->for($bot, 'bot')->create(['chat_id' => '123456']);
    fakeTelegramMenu();
    app(MenuMessenger::class)->welcome($chat);
    Telegraph::assertSentData('sendMessage', ['chat_id' => '123456']);
    Telegraph::fake(['sendMessage' => ['ok' => false, 'error_code' => $status, 'parameters' => ['retry_after' => 23]]]);

    expect(fn () => app(MenuMessenger::class)->welcome($chat))->toThrow(TelegramDeliveryFailed::class);

    Telegraph::assertSentData('sendMessage', ['chat_id' => '123456', 'disable_notification' => true]);
    Telegraph::assertNotSentData('deleteMessage', []);
    expect(app(MenuSession::class)->messageId($chat))->toBe(900);
    fakeTelegramMenu(901);
    app(MenuMessenger::class)->welcome($chat);
    Telegraph::assertSentData('sendMessage', ['chat_id' => '123456', 'disable_notification' => true]);
    Telegraph::assertSentData('deleteMessage', ['message_id' => 900]);
    expect(app(MenuSession::class)->messageId($chat))->toBe(901);
})->with([429, 500]);

test('buttons from a previous connection cannot confirm a new care draft with the same message and revision', function () {
    $this->freezeSecond();
    $bot = telegramWebhookBot();
    $account = linkedTelegramPlayer($bot);
    $user = $account->user;
    $pet = Pet::factory()->for($user)->create(['hydration' => 10]);
    $headers = ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'];
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 900]]),
        'api.telegram.org/bot*/answerCallbackQuery' => Http::response(['ok' => true, 'result' => true]),
    ]);
    $account->forceFill(['menu_message_id' => 900, 'menu_revision' => 5])->save();
    app(MenuMessenger::class)->publish($account, new TelegramScreen('Home', [[['text' => 'Home', 'command' => ['action' => 'home']]]]));
    $oldButton = Http::recorded()->last()[0]['reply_markup']['inline_keyboard'][0][0]['callback_data'];
    $this->actingAs($user)->delete(route('telegram.unlink'))->assertRedirect(route('profile.edit'));
    $link = app(CreateTelegramLink::class)->handle($user);
    $newAccount = app(ConnectTelegramAccount::class)->handle($bot, $account->chat, '123456', 'Player', null, $link->token);
    $newAccount->forceFill(['menu_message_id' => 900, 'menu_revision' => 5])->save();
    $token = (string) Str::uuid();
    app(MenuMessenger::class)->publish($newAccount, new TelegramScreen('Confirm care', [[['text' => 'Start', 'command' => ['action' => 'care.start']]]], [
        'draft' => ['petId' => $pet->id, 'variant' => 'water', 'itemIds' => [], 'skipped' => [], 'token' => $token],
    ]));
    $newButton = Http::recorded()->last()[0]['reply_markup']['inline_keyboard'][0][0]['callback_data'];

    $this->postJson(route('telegraph.webhook', $bot->token), telegramButtonUpdate($oldButton), $headers)->assertNoContent();

    $this->assertDatabaseCount('pet_care_actions', 0);
    $this->assertDatabaseHas('telegram_accounts', ['id' => $newAccount->id, 'menu_message_id' => 900, 'menu_revision' => 6]);
    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/answerCallbackQuery') && $request['callback_query_id'] === '12345');
    expect($newButton)->toBe('action:tap;k:0;v:6;s:'.$newAccount->id);

    $this->postJson(route('telegraph.webhook', $bot->token), telegramButtonUpdate($newButton), $headers)->assertNoContent();

    $this->assertDatabaseHas('pet_care_actions', ['pet_id' => $pet->id, 'token' => $token, 'completed_at' => null]);
    $this->assertDatabaseHas('telegram_accounts', ['id' => $newAccount->id, 'menu_revision' => 7]);
});
