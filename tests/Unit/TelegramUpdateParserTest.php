<?php

use App\Http\Telegram\TelegramUpdateParser;

test('private commands preserve linking parameters and sender information', function () {
    $update = (new TelegramUpdateParser)->parse(['message' => [
        'message_id' => 8, 'chat' => ['id' => 123, 'type' => 'private'],
        'from' => ['id' => 123, 'first_name' => 'Alice'], 'text' => '/start abc_123',
    ]]);

    expect($update)->not->toBeNull()->senderId->toBe('123')->messageId->toBe(8)->command->toBe('start')->parameter->toBe('abc_123')->firstName->toBe('Alice')->username->toBeNull();
    expect($update->isCallback())->toBeFalse();
});

test('malformed callbacks retain their query id so that the webhook can acknowledge them', function () {
    $update = (new TelegramUpdateParser)->parse(['callback_query' => [
        'id' => 'opaque-callback-id', 'from' => ['id' => 123, 'first_name' => []], 'data' => 'malformed',
        'message' => ['message_id' => 8, 'chat' => ['id' => 123, 'type' => 'private']],
    ]]);

    expect($update)->not->toBeNull()->callbackQueryId->toBe('opaque-callback-id')->callback->toBeNull();
    expect($update->isCallback())->toBeTrue();
});

test('unknown commands and mismatched or group senders are ignored', function (array $message) {
    expect((new TelegramUpdateParser)->parse(['message' => $message]))->toBeNull();
})->with([
    'unknown' => [['chat' => ['id' => 123, 'type' => 'private'], 'from' => ['id' => 123], 'text' => '/chatid']],
    'unknown with irrelevant malformed name' => [['chat' => ['id' => 123, 'type' => 'private'], 'from' => ['id' => 123, 'first_name' => []], 'text' => '/chatid']],
    'mismatched' => [['chat' => ['id' => 123, 'type' => 'private'], 'from' => ['id' => 456], 'text' => '/menu']],
    'group' => [['chat' => ['id' => 123, 'type' => 'group'], 'from' => ['id' => 123], 'text' => '/menu']],
]);
