<?php

use App\Http\Telegram\MenuMessenger;
use App\Http\Telegram\TelegramDeliveryFailed;
use App\Models\TelegramAccount;
use App\Modules\Telegram\DTO\TelegramScreen;
use App\Modules\Telegram\Services\MenuSession;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function telegramDeliveryPhoto(): string
{
    Storage::fake('local');
    Storage::disk('local')->put('breeds/test/portrait.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5XcAAAAASUVORK5CYII='));

    return Storage::disk('local')->path('breeds/test/portrait.png');
}

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

test('telegram navigation sends a quiet new menu before deleting the old one', function () {
    $account = telegramDeliveryAccount();
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 901]]),
        'api.telegram.org/bot*/deleteMessage' => Http::response(['ok' => true, 'result' => true]),
    ]);

    app(MenuMessenger::class)->publish($account, new TelegramScreen('New menu', [[['text' => 'Dogs', 'command' => ['action' => 'pets']]]]));

    expect(telegramDeliveryEndpoints())->toBe(['sendMessage', 'deleteMessage']);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/sendMessage')
        && $request['text'] === 'New menu' && $request['disable_notification'] === true
        && $request['reply_markup']['inline_keyboard'][0][0]['callback_data'] === 'action:tap;k:0;v:4;s:'.$account->id);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/deleteMessage') && $request['message_id'] === 800);
    $this->assertDatabaseHas('telegram_accounts', ['id' => $account->id, 'menu_message_id' => 901, 'menu_revision' => 4]);
});

test('a failed new telegram menu leaves the previous message and saved actions intact', function () {
    $account = telegramDeliveryAccount();
    $state = $account->menu_state;
    Http::preventStrayRequests();
    Http::fake(['api.telegram.org/bot*/sendMessage' => Http::response(['ok' => false, 'error_code' => 429, 'parameters' => ['retry_after' => 12]], 429)]);

    expect(fn () => app(MenuMessenger::class)->publish($account, new TelegramScreen('New', [])))->toThrow(TelegramDeliveryFailed::class);

    expect(telegramDeliveryEndpoints())->toBe(['sendMessage']);
    expect($account->fresh()->menu_state)->toBe($state);
    $this->assertDatabaseHas('telegram_accounts', ['id' => $account->id, 'menu_message_id' => 800, 'menu_revision' => 3]);
});

test('telegram photo cards upload the image with safe html caption and inline actions', function () {
    $account = telegramDeliveryAccount();
    $path = telegramDeliveryPhoto();
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendPhoto' => Http::response(['ok' => true, 'result' => ['message_id' => 901]]),
        'api.telegram.org/bot*/deleteMessage' => Http::response(['ok' => true, 'result' => true]),
    ]);

    app(MenuMessenger::class)->publish($account, new TelegramScreen('<b>Рэй &amp; Луна</b>', [[['text' => 'Care', 'command' => ['action' => 'pets']]]], photoPath: $path));

    expect(telegramDeliveryEndpoints())->toBe(['sendPhoto', 'deleteMessage']);
    Http::assertSent(function (Request $request): bool {
        if (! str_ends_with($request->url(), '/sendPhoto')) {
            return false;
        }
        $data = collect($request->data())->pluck('contents', 'name')->all();
        $keyboard = json_decode($data['reply_markup'], true);

        return $request->hasFile('photo') && $data['caption'] === '<b>Рэй &amp; Луна</b>' && $data['parse_mode'] === 'html'
            && isset($keyboard['inline_keyboard'][0][0]['callback_data']);
    });
    $this->assertDatabaseHas('telegram_accounts', ['id' => $account->id, 'menu_message_id' => 901]);
});

test('telegram keeps long photo details and removes both parts on the next screen', function () {
    $account = telegramDeliveryAccount();
    $path = telegramDeliveryPhoto();
    $body = '<b>'.str_repeat('П', 1100).'</b>';
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendPhoto' => Http::response(['ok' => true, 'result' => ['message_id' => 901]]),
        'api.telegram.org/bot*/sendMessage' => Http::sequence()->push(['ok' => true, 'result' => ['message_id' => 902]])->push(['ok' => true, 'result' => ['message_id' => 903]]),
        'api.telegram.org/bot*/deleteMessage' => Http::response(['ok' => true, 'result' => true]),
    ]);

    app(MenuMessenger::class)->publish($account, new TelegramScreen($body, [], photoPath: $path));
    expect($account->fresh()->menu_state['mediaIds'])->toBe([901]);
    app(MenuMessenger::class)->publish($account, new TelegramScreen('Home', []));

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/sendMessage') && $request['text'] === $body);
    foreach ([800, 901, 902] as $id) {
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/deleteMessage') && $request['message_id'] === $id);
    }
    $this->assertDatabaseHas('telegram_accounts', ['id' => $account->id, 'menu_message_id' => 903]);
});

test('telegram cleans up a partial photo screen when its text cannot be delivered', function () {
    $account = telegramDeliveryAccount();
    $path = telegramDeliveryPhoto();
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendPhoto' => Http::response(['ok' => true, 'result' => ['message_id' => 901]]),
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => false, 'error_code' => 429], 429),
        'api.telegram.org/bot*/deleteMessage' => Http::response(['ok' => true, 'result' => true]),
    ]);

    expect(fn () => app(MenuMessenger::class)->publish($account, new TelegramScreen(str_repeat('X', 1100), [], photoPath: $path)))->toThrow(TelegramDeliveryFailed::class);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/deleteMessage') && $request['message_id'] === 901);
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/deleteMessage') && $request['message_id'] === 800);
    $this->assertDatabaseHas('telegram_accounts', ['id' => $account->id, 'menu_message_id' => 800, 'menu_revision' => 3]);
});

test('telegram image errors fall back to text while transient delivery errors retain the old screen', function (int $code, bool $fallback) {
    $account = telegramDeliveryAccount();
    $path = telegramDeliveryPhoto();
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendPhoto' => Http::response(['ok' => false, 'error_code' => $code], $code),
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 901]]),
        'api.telegram.org/bot*/deleteMessage' => Http::response(['ok' => true, 'result' => true]),
    ]);

    $send = fn () => app(MenuMessenger::class)->publish($account, new TelegramScreen('Dog', [], photoPath: $path));
    if ($fallback) {
        $send();
        expect(telegramDeliveryEndpoints())->toBe(['sendPhoto', 'sendMessage', 'deleteMessage']);
    } else {
        expect($send)->toThrow(TelegramDeliveryFailed::class);
        expect(telegramDeliveryEndpoints())->toBe(['sendPhoto']);
    }
    $this->assertDatabaseHas('telegram_accounts', ['id' => $account->id, 'menu_message_id' => $fallback ? 901 : 800]);
})->with(['invalid media' => [400, true], 'rate limit' => [429, false], 'telegram unavailable' => [500, false]]);

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

    app(MenuMessenger::class)->publish($account, new TelegramScreen('Home', []));

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
