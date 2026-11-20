<?php

namespace App\Http\Telegram;

use RuntimeException;

final class TelegramDeliveryFailed extends RuntimeException
{
    public function __construct(public int $retryAfter = 0, int $status = 0)
    {
        parent::__construct('Telegram delivery failed (code '.$status.').');
    }
}
