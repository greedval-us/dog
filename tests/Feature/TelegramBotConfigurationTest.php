<?php

use App\Modules\Telegram\Queries\GetTelegramBotUrl;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Models\TelegraphBot;
use Dotenv\Repository\Adapter\PutenvAdapter;
use Dotenv\Repository\RepositoryBuilder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    config([
        'telegraph.webhook.secret' => 'test-webhook-secret',
        'telegraph.webhook.domain' => 'https://example.test',
    ]);
});

test('configuration requires an existing database bot without asking for credentials', function () {
    Http::preventStrayRequests();
    Telegraph::fake();

    $this->artisan('telegram:configure', ['--register-webhook' => true])
        ->expectsOutputToContain('No Telegraph bot was found.')
        ->doesntExpectOutputToContain('Please, enter the bot token')
        ->assertFailed();

    $this->assertDatabaseCount('telegraph_bots', 0);
    Telegraph::assertNothingSent();
});

test('configuration requires an explicit database ID when multiple bots exist', function () {
    TelegraphBot::factory()->count(2)->create();
    Http::preventStrayRequests();
    Telegraph::fake();

    $this->artisan('telegram:configure', ['--register-webhook' => true])
        ->expectsOutputToContain('Several Telegraph bots are registered.')
        ->assertFailed();

    $this->assertDatabaseCount('telegraph_bots', 2);
    Telegraph::assertNothingSent();
});

test('configuration refreshes the selected database bot identity and preserves existing bot records', function () {
    $firstBot = TelegraphBot::factory()->create(['token' => '111111:first-private-token', 'name' => 'First companion']);
    $selectedBot = TelegraphBot::factory()->create(['token' => '222222:second-private-token', 'name' => 'Second companion']);
    $apiUrl = 'https://api.telegram.org/bot'.$selectedBot->token;
    Http::preventStrayRequests();
    Http::fake([
        $apiUrl.'/getMe' => Http::sequence()
            ->push(['ok' => true, 'result' => ['username' => 'previous_bot']])
            ->push(['ok' => true, 'result' => ['username' => 'selected_live_bot']]),
        $apiUrl.'/setWebhook' => Http::response(['ok' => true, 'result' => true]),
        $apiUrl.'/setMyCommands' => Http::response(['ok' => true, 'result' => true]),
    ]);
    expect(app(GetTelegramBotUrl::class)->handle($selectedBot))->toBe('https://t.me/previous_bot');

    $this->artisan('telegram:configure', ['bot' => (string) $selectedBot->id, '--register-webhook' => true])
        ->expectsOutput('DogLive Telegram bot configured: @selected_live_bot')
        ->doesntExpectOutputToContain($firstBot->token)
        ->doesntExpectOutputToContain($selectedBot->token)
        ->doesntExpectOutputToContain('test-webhook-secret')
        ->assertSuccessful();

    $this->assertDatabaseCount('telegraph_bots', 2);
    $this->assertDatabaseHas('telegraph_bots', ['id' => $firstBot->id, 'token' => $firstBot->token, 'name' => 'First companion']);
    $this->assertDatabaseHas('telegraph_bots', ['id' => $selectedBot->id, 'token' => $selectedBot->token, 'name' => 'Second companion']);
    Http::assertSentInOrder([
        fn (Request $request): bool => $request->url() === $apiUrl.'/getMe',
        fn (Request $request): bool => $request->url() === $apiUrl.'/getMe',
        fn (Request $request): bool => $request->url() === $apiUrl.'/setWebhook'
            && $request['url'] === 'https://example.test/telegraph/'.$selectedBot->token.'/webhook'
            && $request['secret_token'] === 'test-webhook-secret'
            && $request['allowed_updates'] === ['message', 'callback_query'],
        fn (Request $request): bool => $request->url() === $apiUrl.'/setMyCommands'
            && $request['commands'] === [
                ['command' => 'menu', 'description' => 'DogLive: game menu / Игровое меню'],
                ['command' => 'help', 'description' => 'Help / Помощь'],
            ],
    ]);
});

test('configuration rejects invalid database IDs without contacting Telegram', function (string $botId) {
    TelegraphBot::factory()->create();
    Http::preventStrayRequests();
    Telegraph::fake();

    $this->artisan('telegram:configure', ['bot' => $botId, '--register-webhook' => true])
        ->expectsOutput('Provide a valid Telegraph bot database ID.')
        ->assertFailed();

    $this->assertDatabaseCount('telegraph_bots', 1);
    Telegraph::assertNothingSent();
})->with([
    'text' => ['not-an-id'],
    'zero' => ['0'],
]);

test('configuration does not fall back to an existing bot when the requested database ID is missing', function () {
    $bot = TelegraphBot::factory()->create();
    Http::preventStrayRequests();
    Telegraph::fake();

    $this->artisan('telegram:configure', ['bot' => (string) ($bot->id + 1), '--register-webhook' => true])
        ->expectsOutputToContain('No Telegraph bot was found.')
        ->assertFailed();

    $this->assertDatabaseCount('telegraph_bots', 1);
    Telegraph::assertNothingSent();
});

test('configuration rejects a missing webhook secret before contacting Telegram', function () {
    TelegraphBot::factory()->create();
    config(['telegraph.webhook.secret' => null]);
    Http::preventStrayRequests();
    Telegraph::fake();

    $this->artisan('telegram:configure', ['--register-webhook' => true])
        ->expectsOutput('Set a valid TELEGRAPH_WEBHOOK_SECRET in the environment.')
        ->assertFailed();

    $this->assertDatabaseCount('telegraph_bots', 1);
    Telegraph::assertNothingSent();
});

test('configuration uses the HTTPS application URL when the webhook domain environment value is blank', function () {
    $bot = TelegraphBot::factory()->create();
    $environment = RepositoryBuilder::createWithDefaultAdapters()->addAdapter(PutenvAdapter::class)->make();
    $originalDomain = $environment->get('TELEGRAM_WEBHOOK_DOMAIN');
    $environment->set('TELEGRAM_WEBHOOK_DOMAIN', '');
    try {
        $telegraphConfig = require config_path('telegraph.php');
    } finally {
        if ($originalDomain === null) {
            $environment->clear('TELEGRAM_WEBHOOK_DOMAIN');
        } else {
            $environment->set('TELEGRAM_WEBHOOK_DOMAIN', $originalDomain);
        }
    }
    config([
        'app.url' => 'https://app.example.test',
        'telegraph.webhook.domain' => $telegraphConfig['webhook']['domain'],
    ]);
    URL::useOrigin('https://app.example.test');
    URL::forceScheme('https');
    Http::preventStrayRequests();
    Telegraph::fake();

    $this->artisan('telegram:configure', ['--register-webhook' => true])->assertSuccessful();

    Telegraph::assertRegisteredWebhook(['url' => 'https://app.example.test/telegraph/'.$bot->token.'/webhook']);
});
