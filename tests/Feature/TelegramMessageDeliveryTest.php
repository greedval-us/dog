<?php

use App\Http\Telegram\MenuMessenger;
use App\Http\Telegram\TelegramDeliveryFailed;
use App\Models\TelegramAccount;
use App\Modules\Telegram\Services\MenuSession;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function telegramDeliveryAccount(): TelegramAccount
{
    return TelegramAccount::factory()->create(['menu_message_id' => 800, 'menu_revision' => 3,
        'menu_state' => ['commands' => [['action' => 'home']], 'data' => []]]);
}

/** @return list<string> */
function telegramDeliveryEndpoints(): array
{
    return Http::recorded()->map(fn (array $request): string => basename(parse_url($request[0]->url(), PHP_URL_PATH)))->all();
}

test('telegram status sends a quiet message before deleting the old menu', function () {
    $account = telegramDeliveryAccount();
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 901]]),
        'api.telegram.org/bot*/deleteMessage' => Http::response(['ok' => true, 'result' => true]),
    ]);

    app(MenuMessenger::class)->status($account);

    expect(telegramDeliveryEndpoints())->toBe(['sendMessage', 'deleteMessage']);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/sendMessage')
        && $request['text'] === __('telegram.alerts.connected') && $request['disable_notification'] === true
        && $request['reply_markup']['inline_keyboard'] === [[['text' => __('telegram.alerts.open'), 'url' => route('dashboard')]]]);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/deleteMessage') && $request['message_id'] === 800);
    $this->assertDatabaseHas('telegram_accounts', ['id' => $account->id, 'menu_message_id' => 901]);
});

test('a failed status message preserves the previous message for cleanup', function () {
    $account = telegramDeliveryAccount();
    $state = $account->menu_state;
    Http::preventStrayRequests();
    Http::fake(['api.telegram.org/bot*/sendMessage' => Http::response(['ok' => false, 'error_code' => 429, 'parameters' => ['retry_after' => 12]], 429)]);

    expect(fn () => app(MenuMessenger::class)->status($account))->toThrow(TelegramDeliveryFailed::class);

    expect(telegramDeliveryEndpoints())->toBe(['sendMessage']);
    expect($account->fresh()->menu_state)->toBe($state);
    $this->assertDatabaseHas('telegram_accounts', ['id' => $account->id, 'menu_message_id' => 800, 'menu_revision' => 3]);
});

test('telegram welcome replaces the previous menu and its companion photo', function () {
    $account = telegramDeliveryAccount();
    $session = app(MenuSession::class);
    $session->rememberMessage($account->chat, 800, [799]);
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 901]]),
        'api.telegram.org/bot*/deleteMessage' => Http::response(['ok' => true, 'result' => true]),
    ]);

    app(MenuMessenger::class)->welcome($account->chat);

    expect($session->messageId($account->chat))->toBe(901);
    foreach ([799, 800] as $id) {
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/deleteMessage') && $request['message_id'] === $id);
    }
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/editMessage'));
});

test('telegram removes the persisted companion photo even after the menu cache is lost', function () {
    $account = telegramDeliveryAccount();
    $state = $account->menu_state;
    $state['mediaIds'] = [799];
    $account->update(['menu_state' => $state]);
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 901]]),
        'api.telegram.org/bot*/deleteMessage' => Http::response(['ok' => true, 'result' => true]),
    ]);

    app(MenuMessenger::class)->status($account);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/deleteMessage') && $request['message_id'] === 799);
});

test('disconnecting telegram removes the persisted photo when its cache is missing', function () {
    $account = telegramDeliveryAccount();
    $state = $account->menu_state;
    $state['mediaIds'] = [799];
    $account->update(['menu_state' => $state]);
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 901]]),
        'api.telegram.org/bot*/deleteMessage' => Http::response(['ok' => true, 'result' => true]),
    ]);

    $this->actingAs($account->user)->delete(route('telegram.unlink'))->assertRedirect(route('profile.edit'));

    $this->assertModelMissing($account);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/deleteMessage') && $request['message_id'] === 799);
});
