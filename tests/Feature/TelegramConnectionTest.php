<?php

use App\Models\TelegramAccount;
use App\Models\TelegramLinkToken;
use App\Models\User;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Telegram\Actions\ConnectTelegramAccount;
use App\Modules\Telegram\Actions\CreateTelegramLink;
use App\Modules\Telegram\Exceptions\TelegramUnavailable;
use App\Modules\Telegram\Queries\GetTelegramConnection;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Models\TelegraphBot;
use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config([
        'telegraph.webhook.secret' => 'test-webhook-secret',
        'doglive_telegram.link_ttl_minutes' => 10,
        'inertia.ssr.enabled' => false,
    ]);
});

function telegramConnectionBot(): TelegraphBot
{
    Http::preventStrayRequests();
    Telegraph::fake(['getMe' => ['ok' => true, 'result' => ['username' => 'doglive_test_bot']]]);

    return TelegraphBot::factory()->create(['token' => '123456:test-token', 'name' => 'DogLive companion']);
}

test('guests cannot create a telegram connection link', function () {
    $this->post('/settings/telegram/link')->assertRedirect(route('login'));
});

test('unavailable telegram configuration does not create a link', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->from(route('profile.edit'))->post('/settings/telegram/link')
        ->assertRedirect(route('profile.edit'))->assertSessionHasErrors('telegram');
    $this->assertDatabaseCount('telegram_link_tokens', 0);
});

test('players receive a ten minute link with only its hash stored and can replace it', function () {
    $this->freezeSecond();
    $bot = telegramConnectionBot();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('telegram.link'))->assertRedirect(route('profile.edit'))->assertSessionHasNoErrors();

    $token = session('telegram.link_token');
    expect($token)->toMatch('/^[a-zA-Z0-9]{48}$/D');
    $this->assertDatabaseHas('telegram_link_tokens', ['user_id' => $user->id, 'telegraph_bot_id' => $bot->id, 'token_hash' => hash('sha256', $token)]);
    expect(TelegramLinkToken::query()->first()->expires_at->equalTo(now()->addMinutes(10)))->toBeTrue();
    expect(TelegramLinkToken::query()->first()->toArray())->not->toHaveKey('token_hash');
    $this->get(route('profile.edit'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('telegram.status', 'pending')
        ->where('telegram.link.url', 'https://t.me/doglive_test_bot?start='.$token)
        ->missing('telegram.token')->missing('telegram.token_hash')->missing('telegram.account.telegraph_chat_id'));

    $this->post(route('telegram.link'))->assertSessionHasNoErrors();

    expect(session('telegram.link_token'))->not->toBe($token);
    $this->assertDatabaseCount('telegram_link_tokens', 1);
    $this->assertDatabaseMissing('telegram_link_tokens', ['token_hash' => hash('sha256', $token)]);
});

test('a valid telegram link creates one account and cannot be replayed', function () {
    $bot = telegramConnectionBot();
    $chat = TelegraphChat::factory()->for($bot, 'bot')->create(['chat_id' => '12345']);
    $user = User::factory()->create();
    $link = app(CreateTelegramLink::class)->handle($user);

    $account = app(ConnectTelegramAccount::class)->handle($bot, $chat, '12345', 'Alice', 'alice', $link->token);

    $this->assertModelExists($account);
    $this->assertDatabaseHas('telegram_accounts', ['user_id' => $user->id, 'telegram_user_id' => '12345', 'first_name' => 'Alice']);
    $this->assertDatabaseCount('telegram_link_tokens', 0);
    expect(app(GetTelegramConnection::class)->handle($user, $link->token))->toMatchArray([
        'status' => 'connected', 'link' => null,
        'account' => ['firstName' => 'Alice', 'username' => 'alice', 'connectedAt' => $account->linked_at->toIso8601String()],
    ]);
    expect(fn () => app(ConnectTelegramAccount::class)->handle($bot, $chat, '12345', 'Changed', null, $link->token))
        ->toThrow(TelegramUnavailable::class, 'telegram.errors.invalid_link');
    $this->assertDatabaseCount('telegram_accounts', 1);
    expect($account->fresh()->first_name)->toBe('Alice');
});

test('expired links are shown as expired and cannot connect at the expiry boundary', function () {
    $this->freezeTime();
    $bot = telegramConnectionBot();
    $chat = TelegraphChat::factory()->for($bot, 'bot')->create(['chat_id' => '12345']);
    $user = User::factory()->create();
    $link = app(CreateTelegramLink::class)->handle($user);
    $this->travel(10)->minutes();

    expect(app(GetTelegramConnection::class)->handle($user, $link->token))->toMatchArray(['status' => 'expired', 'link' => null]);
    expect(fn () => app(ConnectTelegramAccount::class)->handle($bot, $chat, '12345', 'Alice', null, $link->token))
        ->toThrow(TelegramUnavailable::class, 'telegram.errors.invalid_link');
    $this->assertDatabaseCount('telegram_accounts', 0);
});

test('a foreign bot cannot consume a link', function () {
    telegramConnectionBot();
    $user = User::factory()->create();
    $link = app(CreateTelegramLink::class)->handle($user);
    $foreignBot = TelegraphBot::factory()->create();
    $chat = TelegraphChat::factory()->for($foreignBot, 'bot')->create(['chat_id' => '12345']);

    expect(fn () => app(ConnectTelegramAccount::class)->handle($foreignBot, $chat, '12345', 'Alice', null, $link->token))
        ->toThrow(TelegramUnavailable::class, 'telegram.errors.invalid_link');

    $this->assertDatabaseCount('telegram_accounts', 0);
    $this->assertDatabaseCount('telegram_link_tokens', 1);
});

test('a mismatched bot or sender cannot consume a link', function (string $mismatch) {
    $bot = telegramConnectionBot();
    $user = User::factory()->create();
    $link = app(CreateTelegramLink::class)->handle($user);
    $chatBot = $mismatch === 'bot' ? TelegraphBot::factory()->create() : $bot;
    $chat = TelegraphChat::factory()->for($chatBot, 'bot')->create(['chat_id' => '12345']);

    expect(fn () => app(ConnectTelegramAccount::class)->handle($bot, $chat, $mismatch === 'sender' ? '54321' : '12345', 'Alice', null, $link->token))
        ->toThrow(TelegramUnavailable::class, 'telegram.errors.invalid_chat');

    $this->assertDatabaseCount('telegram_accounts', 0);
    $this->assertDatabaseCount('telegram_link_tokens', 1);
})->with(['bot', 'sender']);

test('a telegram account cannot be transferred to another player by using a new link', function () {
    $bot = telegramConnectionBot();
    $chat = TelegraphChat::factory()->for($bot, 'bot')->create(['chat_id' => '12345']);
    $account = TelegramAccount::factory()->for($chat, 'chat')->create();
    $otherUser = User::factory()->create();
    $link = app(CreateTelegramLink::class)->handle($otherUser);

    expect(fn () => app(ConnectTelegramAccount::class)->handle($bot, $chat, '12345', 'New name', null, $link->token))
        ->toThrow(TelegramUnavailable::class, 'telegram.errors.already_connected');

    $this->assertDatabaseCount('telegram_accounts', 1);
    $this->assertDatabaseHas('telegram_accounts', ['id' => $account->id, 'user_id' => $account->user_id]);
    $this->assertDatabaseCount('telegram_link_tokens', 1);
});

test('connected players cannot generate a replacement link', function () {
    telegramConnectionBot();
    $account = TelegramAccount::factory()->create();

    $this->actingAs($account->user)->post(route('telegram.link'))->assertSessionHasErrors('telegram');

    $this->assertDatabaseCount('telegram_link_tokens', 0);
    $this->assertModelExists($account);
});

test('inactive players cannot create or consume links', function () {
    $bot = telegramConnectionBot();
    $chat = TelegraphChat::factory()->for($bot, 'bot')->create(['chat_id' => '12345']);
    $user = User::factory()->create();
    $link = app(CreateTelegramLink::class)->handle($user);
    $user->status = PlayerStatus::Blocked;
    $user->save();

    expect(fn () => app(CreateTelegramLink::class)->handle($user))->toThrow(TelegramUnavailable::class, 'telegram.errors.inactive');
    expect(fn () => app(ConnectTelegramAccount::class)->handle($bot, $chat, '12345', 'Alice', null, $link->token))
        ->toThrow(TelegramUnavailable::class, 'telegram.errors.inactive');
    $this->assertDatabaseCount('telegram_accounts', 0);
});

test('connection queries do not expose another players session token', function () {
    telegramConnectionBot();
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $link = app(CreateTelegramLink::class)->handle($otherUser);

    expect(app(GetTelegramConnection::class)->handle($user, $link->token))->toMatchArray(['status' => 'disconnected', 'link' => null]);
    $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('telegram.status', 'disconnected')->where('telegram.link', null)->missing('telegram.token_hash'));
});

test('disconnect deletes only the current players account and pending link and clears the session', function () {
    $bot = telegramConnectionBot();
    $account = TelegramAccount::factory()->create();
    $otherAccount = TelegramAccount::factory()->create();
    TelegramLinkToken::factory()->create(['user_id' => $account->user_id, 'telegraph_bot_id' => $bot->id]);

    $this->actingAs($account->user)->withSession(['telegram.link_token' => 'old-token'])->delete(route('telegram.unlink'))
        ->assertRedirect(route('profile.edit'))->assertSessionMissing('telegram.link_token');

    $this->assertModelMissing($account);
    $this->assertModelExists($otherAccount);
    $this->assertDatabaseCount('telegram_link_tokens', 0);
});

test('bot links use the native username and remain available when another bot is added', function () {
    $bot = telegramConnectionBot();
    $user = User::factory()->create();
    $link = app(CreateTelegramLink::class)->handle($user);
    TelegraphBot::factory()->create(['name' => 'Another bot']);

    $pending = app(GetTelegramConnection::class)->handle($user, $link->token);
    expect($pending)->toMatchArray(['status' => 'pending', 'available' => true, 'botUrl' => 'https://t.me/doglive_test_bot']);
    expect($pending['link']['url'])->toBe($link->url);
    $chat = TelegraphChat::factory()->for($bot, 'bot')->create(['chat_id' => '12345']);
    app(ConnectTelegramAccount::class)->handle($bot, $chat, '12345', 'Player', null, $link->token);

    expect(app(GetTelegramConnection::class)->handle($user))->toMatchArray(['status' => 'connected', 'available' => true, 'botUrl' => 'https://t.me/doglive_test_bot']);
});

test('new connections do not select an arbitrary database bot when several are registered', function () {
    telegramConnectionBot();
    TelegraphBot::factory()->create();
    $user = User::factory()->create();

    expect(fn () => app(CreateTelegramLink::class)->handle($user))->toThrow(TelegramUnavailable::class, 'telegram.errors.unavailable');
    expect(app(GetTelegramConnection::class)->handle($user))->toMatchArray(['status' => 'unavailable', 'available' => false, 'botUrl' => null]);
    $this->assertDatabaseCount('telegram_link_tokens', 0);
    Telegraph::assertNothingSent();
});

test('a Telegram API failure keeps the connection page usable without generating links', function () {
    telegramConnectionBot();
    Telegraph::fake(['getMe' => ['ok' => false, 'error_code' => 401, 'description' => 'Unauthorized']]);
    $user = User::factory()->create();

    expect(app(GetTelegramConnection::class)->handle($user))->toMatchArray(['status' => 'unavailable', 'botUrl' => null]);
    expect(fn () => app(CreateTelegramLink::class)->handle($user))->toThrow(TelegramUnavailable::class, 'telegram.errors.unavailable');
    $this->assertDatabaseCount('telegram_link_tokens', 0);
});

test('native bot URLs are cached and changing the database token invalidates the cached identity', function () {
    $bot = TelegraphBot::factory()->create(['token' => '111:first-token', 'name' => 'Free text bot name']);
    $user = User::factory()->create();
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot111:first-token/getMe' => Http::response(['ok' => true, 'result' => ['username' => 'first_bot']]),
        'api.telegram.org/bot222:second-token/getMe' => Http::response(['ok' => true, 'result' => ['username' => 'second_bot']]),
    ]);

    expect(app(GetTelegramConnection::class)->handle($user)['botUrl'])->toBe('https://t.me/first_bot');
    expect(app(CreateTelegramLink::class)->handle($user)->url)->toStartWith('https://t.me/first_bot?start=');
    Http::assertSentCount(1);
    $bot->update(['token' => '222:second-token']);

    expect(app(GetTelegramConnection::class)->handle($user)['botUrl'])->toBe('https://t.me/second_bot');
    Http::assertSentCount(2);
    expect($bot->fresh()->name)->toBe('Free text bot name');
});

test('a native bot connection failure does not break the player settings page', function () {
    TelegraphBot::factory()->create();
    $user = User::factory()->create();
    Http::preventStrayRequests();
    Http::fake(['api.telegram.org/bot*/getMe' => Http::failedConnection()]);

    $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('telegram.status', 'unavailable')->where('telegram.botUrl', null));
    $this->assertDatabaseCount('telegram_link_tokens', 0);
});
