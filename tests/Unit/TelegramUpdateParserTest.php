<?php

use App\Http\Telegram\TelegramUpdateParser;

test('a private start link preserves the token and sender information', function () {
    $update = (new TelegramUpdateParser)->parse(['message' => [
        'message_id' => 8, 'chat' => ['id' => 123, 'type' => 'private'],
        'from' => ['id' => 123, 'first_name' => 'Alice', 'username' => 'alice'], 'text' => '/start abc_123',
    ]]);

    expect($update)->not->toBeNull()->senderId->toBe('123')->messageId->toBe(8)
        ->command->toBe('start')->parameter->toBe('abc_123')->firstName->toBe('Alice')->username->toBe('alice');
    expect($update->isCallback())->toBeFalse();
});

test('notification commands are accepted without collecting sender names', function (string $text, string $command) {
    $update = (new TelegramUpdateParser)->parse(['message' => [
        'chat' => ['id' => 123, 'type' => 'private'],
        'from' => ['id' => 123, 'first_name' => 'Alice', 'username' => 'alice'], 'text' => $text,
    ]]);

    expect($update)->not->toBeNull()->command->toBe($command)->parameter->toBeNull()->firstName->toBe('')->username->toBeNull();
})->with([
    'welcome' => ['/start', 'start'],
    'connection status' => ['/status', 'status'],
    'help' => ['/help', 'help'],
    'legacy menu' => ['/menu', 'menu'],
    'addressed command' => ['/status@doglive_bot', 'status'],
]);

test('game input and unsupported commands are ignored', function (mixed $text) {
    $update = (new TelegramUpdateParser)->parse(['message' => [
        'chat' => ['id' => 123, 'type' => 'private'], 'from' => ['id' => 123], 'text' => $text,
    ]]);

    expect($update)->toBeNull();
})->with([
    'dog name' => ['Шарик'],
    'sale price' => ['100'],
    'unknown command' => ['/chatid'],
    'unexpected payload' => ['/status abc_123'],
    'invalid link token' => ['/start invalid!'],
    'oversized link token' => ['/start '.str_repeat('a', 65)],
    'missing text' => [null],
    'malformed text' => [[]],
]);

test('only private messages from the chat owner are accepted', function (string $type, int $senderId, bool $isBot) {
    $update = (new TelegramUpdateParser)->parse(['message' => [
        'chat' => ['id' => 123, 'type' => $type], 'from' => ['id' => $senderId, 'is_bot' => $isBot], 'text' => '/status',
    ]]);

    expect($update)->toBeNull();
})->with([
    'group' => ['group', 123, false],
    'other sender' => ['private', 456, false],
    'bot sender' => ['private', 123, true],
]);

test('legacy buttons identify their linked account and all private callbacks can be acknowledged', function (mixed $data, ?int $accountId) {
    $update = (new TelegramUpdateParser)->parse(['callback_query' => [
        'id' => 'opaque-id', 'from' => ['id' => 123], 'data' => $data,
        'message' => ['message_id' => 8, 'chat' => ['id' => 123, 'type' => 'private']],
    ]]);

    expect($update)->not->toBeNull()->callbackQueryId->toBe('opaque-id')->legacyAccountId->toBe($accountId)->command->toBeNull();
    expect($update->isCallback())->toBeTrue();
})->with([
    'old menu button' => ['action:tap;k:0;v:23;s:17', 17],
    'unrecognized action' => ['action:chatid', null],
    'missing account' => ['action:tap;k:0;v:1', null],
    'zero account id' => ['action:tap;k:0;v:1;s:0', null],
    'overflowing account id' => ['action:tap;k:0;v:1;s:9999999999999999999', null],
    'oversized command key' => ['action:tap;k:10000;v:1;s:17', null],
    'no callback data' => [null, null],
]);
