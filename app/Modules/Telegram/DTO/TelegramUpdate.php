<?php

namespace App\Modules\Telegram\DTO;

final readonly class TelegramUpdate
{
    public function __construct(
        public string $senderId,
        public int $messageId,
        public string $firstName,
        public ?string $username,
        public ?string $command = null,
        public ?string $parameter = null,
        public ?string $callbackQueryId = null,
        public ?TelegramCallbackData $callback = null,
    ) {}

    public function isCallback(): bool
    {
        return $this->callbackQueryId !== null;
    }
}
