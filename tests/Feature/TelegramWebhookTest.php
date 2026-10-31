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
use App\Modules\Telegram\DTO\TelegramScreen;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Models\TelegraphBot;
use DefStudio\Telegraph\Models\TelegraphChat;
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

function fakeTelegramMenu(): void
{
    Http::preventStrayRequests();
    Telegraph::fake([
        'getMe' => ['ok' => true, 'result' => ['username' => 'doglive_test_bot']],
        'sendMessage' => ['ok' => true, 'result' => ['message_id' => 900]],
        'editMessageText' => ['ok' => true, 'result' => ['message_id' => 900]],
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

test('start consumes a site link and later menus edit the existing message', function () {
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

    fakeTelegramMenu();
    $this->postJson(route('telegraph.webhook', $bot->token), telegramMessageUpdate('/menu'), $headers)->assertNoContent();
    Telegraph::assertSentData('editMessageText', ['message_id' => 900, 'chat_id' => '123456']);
    Telegraph::assertNotSentData('sendMessage', []);
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

test('a missing menu message is replaced and the previous keyboard is retired', function () {
    $bot = telegramWebhookBot();
    $account = linkedTelegramPlayer($bot);
    $account->forceFill(['menu_message_id' => 800])->save();
    Http::preventStrayRequests();
    Telegraph::fake([
        'editMessageText' => ['ok' => false, 'error_code' => 400, 'description' => 'Bad Request: message to edit not found'],
        'sendMessage' => ['ok' => true, 'result' => ['message_id' => 900]],
        'deleteMessage' => ['ok' => false, 'error_code' => 400, 'description' => 'Bad Request: message cannot be deleted'],
        'editMessageReplyMarkup' => ['ok' => true, 'result' => true],
    ]);

    app(MenuMessenger::class)->publish($account, new TelegramScreen('Updated', [[['text' => 'Home', 'command' => ['action' => 'home']]]]));

    expect($account->fresh()->menu_message_id)->toBe(900);
    Telegraph::assertSentData('deleteMessage', ['message_id' => 800]);
    Telegraph::assertSentData('editMessageReplyMarkup', ['message_id' => 800]);
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

test('telegram rate limits preserve retry timing and do not create replacement messages', function () {
    $bot = telegramWebhookBot();
    $account = linkedTelegramPlayer($bot);
    $account->forceFill(['menu_message_id' => 900])->save();
    Http::preventStrayRequests();
    Telegraph::fake(['editMessageText' => ['ok' => false, 'error_code' => 429, 'parameters' => ['retry_after' => 23]]]);

    try {
        app(MenuMessenger::class)->publish($account, new TelegramScreen('Menu', []));
        $this->fail('Rate limiting must defer delivery.');
    } catch (TelegramDeliveryFailed $exception) {
        expect($exception->retryAfter)->toBe(23);
    }
    expect($account->fresh()->menu_revision)->toBe(0);
    Telegraph::assertNotSentData('sendMessage', []);
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
    Telegraph::assertSentData('editMessageText', ['message_id' => 900]);
    expect($account->fresh()->menu_revision)->toBe(3);
});

test('linking adopts the welcome message instead of sending another menu', function () {
    $bot = telegramWebhookBot();
    $user = User::factory()->create(['locale' => 'ru']);
    $headers = ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'];
    fakeTelegramMenu();
    $this->postJson(route('telegraph.webhook', $bot->token), telegramMessageUpdate('/start'), $headers)->assertNoContent();
    Telegraph::assertSentData('sendMessage', ['chat_id' => '123456']);
    $link = app(CreateTelegramLink::class)->handle($user);
    fakeTelegramMenu();

    $this->postJson(route('telegraph.webhook', $bot->token), telegramMessageUpdate('/start '.$link->token), $headers)->assertNoContent();

    Telegraph::assertSentData('editMessageText', ['message_id' => 900, 'chat_id' => '123456']);
    Telegraph::assertNotSentData('sendMessage', []);
    $this->assertDatabaseHas('telegram_accounts', ['user_id' => $user->id, 'menu_message_id' => 900]);
});

test('unlink replaces the game keyboard and relinking reuses the same message', function () {
    $bot = telegramWebhookBot();
    $account = linkedTelegramPlayer($bot);
    $user = $account->user;
    $account->forceFill(['menu_message_id' => 900, 'menu_revision' => 2, 'menu_state' => ['commands' => ['0' => ['action' => 'profile']], 'data' => []]])->save();
    fakeTelegramMenu();

    $this->actingAs($user)->delete(route('telegram.unlink'))->assertRedirect(route('profile.edit'));

    $this->assertModelMissing($account);
    Telegraph::assertSentData('editMessageText', ['message_id' => 900, 'text' => __('telegram.welcome'),
        'reply_markup' => ['inline_keyboard' => [[['text' => __('telegram.connect_site'), 'url' => route('profile.edit')]]]]]);
    $link = app(CreateTelegramLink::class)->handle($user);
    fakeTelegramMenu();
    $this->postJson(route('telegraph.webhook', $bot->token), telegramMessageUpdate('/start '.$link->token), ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'])->assertNoContent();
    Telegraph::assertSentData('editMessageText', ['message_id' => 900]);
    Telegraph::assertNotSentData('sendMessage', []);
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
        'api.telegram.org/bot*/editMessageText' => Http::response(['ok' => false, 'error_code' => 400, 'description' => 'Bad Request: message to edit not found']),
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 900]]),
        'api.telegram.org/bot*/deleteMessage' => Http::failedConnection(),
        'api.telegram.org/bot*/editMessageReplyMarkup' => Http::failedConnection(),
    ]);

    $this->postJson(route('telegraph.webhook', $bot->token), telegramButtonUpdate('action:tap;k:0;v:2;s:'.$account->id, message: 800), ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'])->assertNoContent();

    $this->assertDatabaseHas('pet_care_actions', ['pet_id' => $pet->id, 'token' => $token, 'completed_at' => null]);
    $this->assertDatabaseHas('telegram_accounts', ['id' => $account->id, 'menu_message_id' => 900, 'menu_revision' => 3]);
    Queue::assertPushed(RefreshTelegramCare::class, fn (RefreshTelegramCare $job): bool => $job->accountId === $account->id && $job->petId === $pet->id && $job->token === $token && $job->revision === 3);
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
    Http::fake(['api.telegram.org/bot*/editMessageText' => Http::failedConnection()]);

    $this->actingAs($account->user)->delete(route('telegram.unlink'))->assertRedirect(route('profile.edit'));

    $this->assertModelMissing($account);
    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/editMessageText') && $request['message_id'] === 900);
    fakeTelegramMenu();
    $this->postJson(route('telegraph.webhook', $bot->token), telegramButtonUpdate('action:tap;k:0;v:2;s:'.$account->id), ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'])->assertNoContent();
    Telegraph::assertSentData('answerCallbackQuery', ['callback_query_id' => '12345']);
    Telegraph::assertNotSentData('editMessageText', []);
    $this->assertDatabaseCount('pet_care_actions', 0);
});

test('welcome refresh failures preserve the message and do not send duplicates', function (int $status) {
    $bot = telegramWebhookBot();
    $chat = TelegraphChat::factory()->for($bot, 'bot')->create(['chat_id' => '123456']);
    fakeTelegramMenu();
    app(MenuMessenger::class)->welcome($chat);
    Telegraph::assertSentData('sendMessage', ['chat_id' => '123456']);
    Telegraph::fake(['editMessageText' => ['ok' => false, 'error_code' => $status, 'parameters' => ['retry_after' => 23]]]);

    expect(fn () => app(MenuMessenger::class)->welcome($chat))->toThrow(TelegramDeliveryFailed::class);

    Telegraph::assertNotSentData('sendMessage', []);
    fakeTelegramMenu();
    app(MenuMessenger::class)->welcome($chat);
    Telegraph::assertSentData('editMessageText', ['message_id' => 900]);
    Telegraph::assertNotSentData('sendMessage', []);
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
        'api.telegram.org/bot*/editMessageText' => Http::response(['ok' => true, 'result' => ['message_id' => 900]]),
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
