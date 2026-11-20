<?php

use App\Http\Telegram\MenuMessenger;
use App\Http\Telegram\TelegramDeliveryFailed;
use App\Jobs\RefreshTelegramCare;
use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\TelegramAccount;
use App\Models\User;
use App\Modules\Pets\Actions\StartPetCare;
use App\Modules\Telegram\Actions\ConnectTelegramAccount;
use App\Modules\Telegram\Actions\CreateTelegramLink;
use App\Modules\Telegram\Services\MenuSession;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Models\TelegraphBot;
use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Support\Facades\Http;
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

test('start consumes a site link and the old menu command now shows notification status', function () {
    $bot = telegramWebhookBot();
    $user = User::factory()->create(['locale' => 'ru']);
    $link = app(CreateTelegramLink::class)->handle($user);
    fakeTelegramMenu();
    $headers = ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'];

    $this->postJson(route('telegraph.webhook', $bot->token), telegramMessageUpdate('/start '.$link->token), $headers)
        ->assertNoContent();

    $account = TelegramAccount::query()->where('user_id', $user->id)->sole();
    expect($account->menu_message_id)->toBe(900);
    Telegraph::assertSentData('sendMessage', ['chat_id' => '123456', 'text' => trans('telegram.alerts.connected', [], 'ru'),
        'reply_markup' => ['inline_keyboard' => [[['text' => trans('telegram.alerts.open', [], 'ru'), 'url' => route('dashboard')]]]],
    ]);

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

    app(MenuMessenger::class)->status($account);

    expect($account->fresh()->menu_message_id)->toBe(900);
    Telegraph::assertSentData('deleteMessage', ['message_id' => 800]);
    Telegraph::assertSentData('editMessageReplyMarkup', ['message_id' => 800]);
    Telegraph::assertNotSentData('editMessageText', []);
});

test('a queued legacy care refresh leaves care completion to the site', function () {
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

    expect(PetCareAction::query()->findOrFail($care->id)->completed_at)->toBeNull();
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
        app(MenuMessenger::class)->status($account);
        $this->fail('Rate limiting must defer delivery.');
    } catch (TelegramDeliveryFailed $exception) {
        expect($exception->retryAfter)->toBe(23);
    }
    expect($account->fresh()->menu_revision)->toBe(0);
    expect($account->fresh()->menu_message_id)->toBe(900);
    Telegraph::assertSentData('sendMessage', ['chat_id' => '123456', 'disable_notification' => true]);
    Telegraph::assertNotSentData('deleteMessage', []);
});

test('linking confirms the subscription and replaces the welcome', function () {
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

test('unlink sends a welcome and relinking confirms the subscription', function () {
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

test('old care buttons show where to play and cannot start an action', function () {
    $bot = telegramWebhookBot();
    $account = linkedTelegramPlayer($bot);
    $pet = Pet::factory()->for($account->user)->create(['hydration' => 10]);
    $account->forceFill(['menu_message_id' => 900, 'menu_revision' => 2, 'menu_state' => [
        'commands' => [['action' => 'care.start']],
        'data' => ['draft' => ['petId' => $pet->id, 'variant' => 'water', 'itemIds' => [], 'skipped' => [], 'token' => (string) Str::uuid()]],
    ]])->save();
    fakeTelegramMenu(901);
    $update = telegramButtonUpdate('action:tap;k:0;v:2;s:'.$account->id);
    $update['callback_query']['id'] = 'legacy-button';
    $headers = ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'];

    $this->postJson(route('telegraph.webhook', $bot->token), $update, $headers)->assertNoContent();

    Telegraph::assertSentData('answerCallbackQuery', ['callback_query_id' => 'legacy-button']);
    Telegraph::assertSentData('sendMessage', ['text' => trans('telegram.alerts.old_menu', [], 'ru')."\n\n".trans('telegram.alerts.connected', [], 'ru')]);
    expect($account->fresh()->menu_state)->toBe([]);
    $this->assertDatabaseCount('pet_care_actions', 0);
});

test('free text cannot continue an outstanding kennel draft', function () {
    $bot = telegramWebhookBot();
    $account = linkedTelegramPlayer($bot);
    $draft = ['data' => ['kennel' => ['userId' => $account->user_id, 'awaitingName' => true, 'nameRequestedAt' => now()->subMinute()->timestamp]]];
    $account->update(['menu_state' => $draft]);
    fakeTelegramMenu();

    $this->postJson(route('telegraph.webhook', $bot->token), telegramMessageUpdate('Шарик'), ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'])->assertNoContent();

    Telegraph::assertNothingSent();
    expect($account->fresh()->menu_state)->toBe($draft);
    $this->assertDatabaseCount('pets', 0);
});

test('a button from a previous connection is acknowledged without replacing the current status', function () {
    $bot = telegramWebhookBot();
    $oldAccount = linkedTelegramPlayer($bot);
    $oldButton = telegramButtonUpdate('action:tap;k:0;v:1;s:'.$oldAccount->id);
    $oldAccount->delete();
    $account = TelegramAccount::factory()->for($oldAccount->chat, 'chat')->create(['menu_message_id' => 901]);
    fakeTelegramMenu();

    $this->postJson(route('telegraph.webhook', $bot->token), $oldButton, ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'])->assertNoContent();

    Telegraph::assertSentData('answerCallbackQuery', ['callback_query_id' => '12345']);
    Telegraph::assertNotSentData('sendMessage', []);
    expect($account->fresh()->menu_message_id)->toBe(901);
});

test('status and help open the corresponding site page', function (string $command, string $message, string $label, string $route) {
    $bot = telegramWebhookBot();
    linkedTelegramPlayer($bot);
    fakeTelegramMenu();

    $this->postJson(route('telegraph.webhook', $bot->token), telegramMessageUpdate($command), ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'])->assertNoContent();

    Telegraph::assertSentData('sendMessage', ['chat_id' => '123456', 'text' => trans($message, [], 'ru'),
        'reply_markup' => ['inline_keyboard' => [[['text' => trans($label, [], 'ru'), 'url' => route($route)]]]],
    ]);
})->with([
    ['/help', 'telegram.alerts.help', 'telegram.alerts.settings', 'profile.edit'],
    ['/status', 'telegram.alerts.connected', 'telegram.alerts.open', 'dashboard'],
]);
