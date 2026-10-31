<?php

namespace App\Modules\Telegram\Calculators;

use App\Modules\Telegram\DTO\TelegramCallbackData;

final class MenuCallbackCodec
{
    public static function encode(int $accountId, int $revision, int $key): string
    {
        return 'action:tap;k:'.$key.';v:'.$revision.';s:'.$accountId;
    }

    public static function decode(string $value): ?TelegramCallbackData
    {
        if (! preg_match('/^action:tap;k:([0-9]{1,4});v:([0-9]{1,10});s:([1-9][0-9]{0,18})$/D', $value, $matches)) {
            return null;
        }

        return new TelegramCallbackData($matches[1], (int) $matches[2], (int) $matches[3]);
    }
}
