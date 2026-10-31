<?php

namespace App\Modules\Telegram\Services;

use Closure;
use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Support\Facades\Cache;

final class MenuSession
{
    public function withLock(int $botId, string $senderId, Closure $operation): mixed
    {
        return Cache::lock('telegram-menu:'.$botId.':'.$senderId, (int) config('doglive_telegram.menu_lock_seconds', 60))
            ->block(5, $operation);
    }

    public function messageId(TelegraphChat $chat): ?int
    {
        $messageId = Cache::get($this->messageKey($chat));

        return is_int($messageId) && $messageId > 0 ? $messageId : null;
    }

    public function rememberMessage(TelegraphChat $chat, int $messageId): void
    {
        Cache::put($this->messageKey($chat), $messageId, now()->addDays(2));
    }

    private function messageKey(TelegraphChat $chat): string
    {
        return 'telegram-welcome:'.$chat->telegraph_bot_id.':'.$chat->chat_id;
    }
}
