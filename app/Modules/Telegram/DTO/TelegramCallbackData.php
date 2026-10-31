<?php

namespace App\Modules\Telegram\DTO;

final readonly class TelegramCallbackData
{
    public function __construct(public string $key, public int $revision, public int $accountId) {}
}
