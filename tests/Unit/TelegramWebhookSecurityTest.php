<?php

use App\Http\Middleware\VerifyTelegramWebhook;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class);

test('telegram webhook accepts only the configured secret', function (?string $header, int $status) {
    config(['telegraph.webhook.secret' => 'test-webhook-secret']);
    $request = Request::create('/telegraph/test/webhook', 'POST');
    if ($header !== null) {
        $request->headers->set('X-Telegram-Bot-Api-Secret-Token', $header);
    }

    try {
        $response = app(VerifyTelegramWebhook::class)->handle($request, fn () => response()->noContent());
        expect($response->getStatusCode())->toBe($status);
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe($status);
    }
})->with([
    'missing' => [null, 403],
    'invalid' => ['invalid', 403],
    'valid' => ['test-webhook-secret', 204],
]);

test('telegram webhook stays disabled until a secret is configured', function () {
    config(['telegraph.webhook.secret' => '']);
    $request = Request::create('/telegraph/test/webhook', 'POST');

    expect(fn () => app(VerifyTelegramWebhook::class)->handle($request, fn () => response()->noContent()))
        ->toThrow(HttpException::class);
});
