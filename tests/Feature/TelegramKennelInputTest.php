<?php

use App\Models\Dog;
use App\Models\Pet;
use App\Models\TelegramAccount;
use App\Models\User;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Models\TelegraphBot;
use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Support\Facades\Http;

function telegramKennelInputBot(): TelegraphBot
{
    config(['telegraph.webhook.secret' => 'test-secret']);
    Http::preventStrayRequests();
    Http::fake(['api.telegram.org/bot*/getMe' => Http::response(['ok' => true, 'result' => ['username' => 'doglive_test_bot']])]);

    return TelegraphBot::factory()->create();
}

function telegramKennelInputAccount(TelegraphBot $bot): TelegramAccount
{
    $chat = TelegraphChat::factory()->for($bot, 'bot')->create(['chat_id' => '123456']);

    return TelegramAccount::factory()->for(User::factory()->state(['locale' => 'ru']))->for($chat, 'chat')->create();
}

function fakeTelegramKennelInput(): void
{
    Http::preventStrayRequests();
    Telegraph::fake([
        'getMe' => ['ok' => true, 'result' => ['username' => 'doglive_test_bot']],
        'sendMessage' => ['ok' => true, 'result' => ['message_id' => 901]],
        'answerCallbackQuery' => ['ok' => true, 'result' => true],
        'deleteMessage' => ['ok' => true, 'result' => true],
    ]);
}

/** @return array<string, mixed> */
function telegramKennelInputMessage(string $text): array
{
    return ['update_id' => 1, 'message' => [
        'message_id' => 10, 'date' => now()->timestamp,
        'from' => ['id' => 123456, 'is_bot' => false, 'first_name' => 'Player'],
        'chat' => ['id' => 123456, 'type' => 'private'], 'text' => $text,
    ]];
}

/** @return array<string, mixed> */
function telegramKennelNameDraft(TelegramAccount $account, int $dogId): array
{
    return [
        'userId' => $account->user_id, 'dogId' => $dogId, 'ageMonths' => 3,
        'mode' => 'starter', 'expectedPrice' => 0, 'token' => '00000000-0000-4000-8000-000000000001',
        'name' => null, 'awaitingName' => true, 'nameRequestedAt' => now()->timestamp,
    ];
}

test('unlinked ordinary text stays untouched without sending a welcome', function (bool $existingChat) {
    $this->freezeSecond();
    $bot = telegramKennelInputBot();
    if ($existingChat) {
        TelegraphChat::factory()->for($bot, 'bot')->create(['chat_id' => '123456']);
    }
    fakeTelegramKennelInput();

    $this->postJson(route('telegraph.webhook', $bot->token), telegramKennelInputMessage('Шарик'), ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'])
        ->assertNoContent();

    Telegraph::assertNothingSent();
    $this->assertDatabaseCount('telegraph_chats', $existingChat ? 1 : 0);
    $this->assertDatabaseCount('telegram_accounts', 0);
})->with(['no chat' => false, 'existing chat' => true]);

test('linked ordinary text outside the kennel name prompt preserves the current screen', function () {
    $this->freezeSecond();
    $bot = telegramKennelInputBot();
    $account = telegramKennelInputAccount($bot);
    $state = ['commands' => [['action' => 'help']], 'data' => []];
    $account->forceFill(['menu_message_id' => 900, 'menu_revision' => 2, 'menu_state' => $state])->save();
    fakeTelegramKennelInput();

    $this->postJson(route('telegraph.webhook', $bot->token), telegramKennelInputMessage('Шарик'), ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'])
        ->assertNoContent();

    Telegraph::assertNothingSent();
    expect($account->fresh())->menu_revision->toBe(2)->menu_state->toBe($state);
    $this->assertDatabaseCount('pets', 0);
});

test('name input needs the current owner and a timestamp at or after the prompt', function (array $draftChanges, ?int $messageDelay) {
    $this->freezeSecond();
    $bot = telegramKennelInputBot();
    $account = telegramKennelInputAccount($bot);
    $draft = array_replace(telegramKennelNameDraft($account, 1), $draftChanges);
    $state = ['commands' => [], 'data' => ['kennel' => $draft]];
    $account->forceFill(['menu_message_id' => 900, 'menu_revision' => 2, 'menu_state' => $state])->save();
    $update = telegramKennelInputMessage('Шарик');
    $update['message']['date'] = $messageDelay === null ? null : now()->timestamp + $messageDelay;
    fakeTelegramKennelInput();

    $this->postJson(route('telegraph.webhook', $bot->token), $update, ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'])
        ->assertNoContent();

    Telegraph::assertNothingSent();
    expect($account->fresh())->menu_revision->toBe(2)->menu_state->toBe($state);
    $this->assertDatabaseCount('pets', 0);
})->with([
    'another owner' => [['userId' => -1], 0],
    'finished prompt' => [['awaitingName' => false], 0],
    'non-boolean prompt flag' => [['awaitingName' => 1], 0],
    'missing prompt timestamp' => [['nameRequestedAt' => null], 0],
    'invalid prompt timestamp' => [['nameRequestedAt' => 'yesterday'], 0],
    'zero prompt timestamp' => [['nameRequestedAt' => 0], 0],
    'missing sent timestamp' => [[], null],
    'message predates prompt' => [[], -1],
]);

test('current kennel name input replaces the prompt with confirmation and removes the accepted incoming message', function (int $messageDelay) {
    $this->freezeSecond();
    $bot = telegramKennelInputBot();
    $account = telegramKennelInputAccount($bot);
    $dog = Dog::factory()->create(['is_starter' => true]);
    $draft = telegramKennelNameDraft($account, $dog->id);
    $account->forceFill(['menu_message_id' => 900, 'menu_revision' => 2, 'menu_state' => ['commands' => [], 'data' => ['kennel' => $draft]]])->save();
    $update = telegramKennelInputMessage('  Шарик  ');
    $update['message']['date'] = now()->timestamp + $messageDelay;
    fakeTelegramKennelInput();

    $this->postJson(route('telegraph.webhook', $bot->token), $update, ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'])
        ->assertNoContent();

    expect($account->fresh()->menu_state['data']['kennel'])->name->toBe('Шарик')->awaitingName->toBeFalse()->token->toBe($draft['token']);
    Telegraph::assertSentData('sendMessage', ['chat_id' => '123456', 'disable_notification' => true]);
    Telegraph::assertSentData('deleteMessage', ['message_id' => 900]);
    Telegraph::assertSentData('deleteMessage', ['message_id' => 10]);
    expect($account->fresh()->menu_message_id)->toBe(901);
    $this->assertDatabaseCount('pets', 0);
    $this->assertDatabaseCount('kennel_purchases', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with(['same second' => 0, 'later' => 1]);

test('invalid name input keeps the name prompt open for correction', function (string $name) {
    $this->freezeSecond();
    $bot = telegramKennelInputBot();
    $account = telegramKennelInputAccount($bot);
    $dog = Dog::factory()->create(['is_starter' => true]);
    $draft = telegramKennelNameDraft($account, $dog->id);
    $account->forceFill(['menu_message_id' => 900, 'menu_revision' => 2, 'menu_state' => ['commands' => [], 'data' => ['kennel' => $draft]]])->save();
    fakeTelegramKennelInput();

    $this->postJson(route('telegraph.webhook', $bot->token), telegramKennelInputMessage($name), ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'])
        ->assertNoContent();

    expect($account->fresh())->menu_revision->toBe(3);
    expect($account->fresh()->menu_state['data']['kennel'])->name->toBeNull()->awaitingName->toBeTrue();
    Telegraph::assertSentData('sendMessage', ['chat_id' => '123456', 'disable_notification' => true]);
    Telegraph::assertSentData('deleteMessage', ['message_id' => 900]);
    Telegraph::assertSentData('deleteMessage', ['message_id' => 10]);
    $this->assertDatabaseCount('pets', 0);
})->with(['empty' => [''], 'whitespace' => ['   '], 'long' => [str_repeat('я', 65)], 'multiple lines' => ["Шарик\nДруг"]]);

test('menu and help commands leave the pending kennel name prompt', function (string $command) {
    $this->freezeSecond();
    $bot = telegramKennelInputBot();
    $account = telegramKennelInputAccount($bot);
    $draft = telegramKennelNameDraft($account, 1);
    $account->forceFill(['menu_message_id' => 900, 'menu_revision' => 2, 'menu_state' => ['commands' => [], 'data' => ['kennel' => $draft]]])->save();
    fakeTelegramKennelInput();

    $this->postJson(route('telegraph.webhook', $bot->token), telegramKennelInputMessage($command), ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'])
        ->assertNoContent();

    expect($account->fresh()->menu_state['data'])->not->toHaveKey('kennel');
    Telegraph::assertSentData('sendMessage', ['chat_id' => '123456', 'disable_notification' => true]);
    Telegraph::assertSentData('deleteMessage', ['message_id' => 900]);
    Telegraph::assertSentData('deleteMessage', ['message_id' => 10]);
    $this->assertDatabaseCount('pets', 0);
})->with(['/menu', '/help']);

test('kennel adoption failures explain the domain reason in the player language', function (string $locale, int $slots, bool $existingPet, bool $availableBreed, string $message) {
    $this->freezeSecond();
    $bot = telegramKennelInputBot();
    $account = telegramKennelInputAccount($bot);
    $account->user->forceFill(['locale' => $locale, 'pet_slots' => $slots])->save();
    if ($existingPet) {
        Pet::factory()->for($account->user)->create();
    }
    $dog = Dog::factory()->create(['is_starter' => $availableBreed]);
    $draft = array_replace(telegramKennelNameDraft($account, $dog->id), ['name' => 'Шарик', 'awaitingName' => false]);
    $account->forceFill(['menu_message_id' => 900, 'menu_revision' => 2, 'menu_state' => [
        'commands' => [['action' => 'kennel.adopt']], 'data' => ['kennel' => $draft],
    ]])->save();
    $update = ['update_id' => 2, 'callback_query' => [
        'id' => 'kennel-callback', 'chat_instance' => 'test', 'data' => 'action:tap;k:0;v:2;s:'.$account->id,
        'from' => ['id' => 123456, 'is_bot' => false, 'first_name' => 'Player'],
        'message' => ['message_id' => 900, 'date' => now()->timestamp, 'chat' => ['id' => 123456, 'type' => 'private'],
            'from' => ['id' => 987654, 'is_bot' => true, 'first_name' => 'DogLive'], 'text' => 'Confirm'],
    ]];
    fakeTelegramKennelInput();

    $this->postJson(route('telegraph.webhook', $bot->token), $update, ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'])
        ->assertNoContent();

    Telegraph::assertSentData('answerCallbackQuery', ['callback_query_id' => 'kennel-callback']);
    Telegraph::assertSentData('sendMessage', ['chat_id' => '123456', 'disable_notification' => true, 'text' => $message]);
    Telegraph::assertSentData('deleteMessage', ['message_id' => 900]);
    $this->assertDatabaseCount('pets', $existingPet ? 1 : 0);
    $this->assertDatabaseCount('kennel_purchases', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with([
    'capacity in English' => ['en', 0, false, true, 'You need a free dog slot. Unlock a place on the My dog page.'],
    'breed unavailable in Russian' => ['ru', 1, false, false, 'Эта порода сейчас недоступна. Выбери другую.'],
    'starter unavailable in Russian' => ['ru', 2, true, true, 'Первую собаку можно получить только один раз, пока у тебя ещё нет питомцев.'],
]);
