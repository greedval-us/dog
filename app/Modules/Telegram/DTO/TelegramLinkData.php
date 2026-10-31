<?php

namespace App\Modules\Telegram\DTO;

final readonly class TelegramLinkData
{
    public function __construct(public string $token, public string $url, public string $expiresAt) {}
}
