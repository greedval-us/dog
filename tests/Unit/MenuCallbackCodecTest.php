<?php

use App\Modules\Telegram\Calculators\MenuCallbackCodec;

test('menu callback data preserves the existing protocol and connection identity', function () {
    $encoded = MenuCallbackCodec::encode(17, 23, 0);
    $decoded = MenuCallbackCodec::decode($encoded);

    expect($encoded)->toBe('action:tap;k:0;v:23;s:17');
    expect($decoded)->not->toBeNull()->key->toBe('0')->revision->toBe(23)->accountId->toBe(17);
    expect(strlen(MenuCallbackCodec::encode(PHP_INT_MAX, 9999999999, 9999)))->toBeLessThanOrEqual(64);
});

test('menu callback data rejects incomplete and unknown commands', function (string $value) {
    expect(MenuCallbackCodec::decode($value))->toBeNull();
})->with(['malformed', 'action:chatid', 'action:tap;k:0;v:1', 'action:tap;k:0;v:1;s:0', 'action:tap;k:0;v:1;s:10000000000000000000', 'action:tap;k:10000;v:1;s:1']);

test('callback keys retain their original string identity', function () {
    expect(MenuCallbackCodec::decode('action:tap;k:00;v:1;s:1'))->key->toBe('00');
});
