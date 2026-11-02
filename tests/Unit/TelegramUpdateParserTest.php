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

test('private text preserves its content and sent timestamp without collecting sender names', function () {
    $update = (new TelegramUpdateParser)->parse(['message' => [
        'message_id' => 12, 'date' => 1793620800,
        'chat' => ['id' => 123, 'type' => 'private'],
        'from' => ['id' => 123, 'first_name' => [], 'username' => ['irrelevant']],
        'text' => '  <b>Шарик</b>  ',
    ]]);

    expect($update)->not->toBeNull()
        ->senderId->toBe('123')->messageId->toBe(12)
        ->text->toBe('  <b>Шарик</b>  ')->sentAt->toBe(1793620800)
        ->command->toBeNull()->parameter->toBeNull()->firstName->toBe('')->username->toBeNull();
});

test('text parsing preserves invalid dog names for the active form to explain', function (string $text) {
    $update = (new TelegramUpdateParser)->parse(['message' => [
        'chat' => ['id' => 123, 'type' => 'private'], 'from' => ['id' => 123], 'text' => $text,
    ]]);

    expect($update)->not->toBeNull()->text->toBe($text)->sentAt->toBeNull();
})->with([
    'empty' => [''],
    'whitespace' => ['   '],
    'multiple lines' => ["First\nSecond"],
    'long dog name' => [str_repeat('я', 65)],
    'maximum message length' => [str_repeat('я', 4096)],
]);

test('invalid sent dates remain absent rather than becoming trusted timestamps', function (mixed $date) {
    $update = (new TelegramUpdateParser)->parse(['message' => [
        'date' => $date, 'chat' => ['id' => 123, 'type' => 'private'], 'from' => ['id' => 123], 'text' => 'Шарик',
    ]]);

    expect($update)->not->toBeNull()->sentAt->toBeNull();
})->with([
    'missing' => [null], 'string' => ['1793620800'], 'boolean' => [true],
    'float' => [1793620800.5], 'zero' => [0], 'negative' => [-1], 'array' => [[]],
]);

test('plain text follows the same private sender boundary and message size limit', function (array $message) {
    expect((new TelegramUpdateParser)->parse(['message' => $message]))->toBeNull();
})->with([
    'bot' => [['chat' => ['id' => 123, 'type' => 'private'], 'from' => ['id' => 123, 'is_bot' => true], 'text' => 'Шарик']],
    'mismatched' => [['chat' => ['id' => 123, 'type' => 'private'], 'from' => ['id' => 456], 'text' => 'Шарик']],
    'group' => [['chat' => ['id' => 123, 'type' => 'group'], 'from' => ['id' => 123], 'text' => 'Шарик']],
    'oversized' => [['chat' => ['id' => 123, 'type' => 'private'], 'from' => ['id' => 123], 'text' => str_repeat('я', 4097)]],
    'missing text' => [['chat' => ['id' => 123, 'type' => 'private'], 'from' => ['id' => 123]]],
    'non-string text' => [['chat' => ['id' => 123, 'type' => 'private'], 'from' => ['id' => 123], 'text' => []]],
    'malformed known command' => [['chat' => ['id' => 123, 'type' => 'private'], 'from' => ['id' => 123], 'text' => '/menu invalid!']],
]);

test('known commands keep precedence over pending text input', function (string $text, string $command) {
    $update = (new TelegramUpdateParser)->parse(['message' => [
        'chat' => ['id' => 123, 'type' => 'private'], 'from' => ['id' => 123], 'text' => $text,
    ]]);

    expect($update)->not->toBeNull()->command->toBe($command)->text->toBeNull();
})->with([
    'menu' => ['/menu', 'menu'],
    'help' => ['/help', 'help'],
    'addressed command' => ['/menu@doglive_bot', 'menu'],
]);
