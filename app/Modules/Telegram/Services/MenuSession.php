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

    /** @return list<int> */
    public function mediaMessageIds(TelegraphChat $chat): array
    {
        $ids = Cache::get($this->messageKey($chat).':media', []);

        return is_array($ids) ? array_values(array_filter($ids, fn (mixed $id): bool => is_int($id) && $id > 0)) : [];
    }

    /** @param list<int> $mediaIds */
    public function rememberMessage(TelegraphChat $chat, int $messageId, array $mediaIds = []): void
    {
        Cache::put($this->messageKey($chat), $messageId, now()->addDays(2));
        Cache::put($this->messageKey($chat).':media', $mediaIds, now()->addDays(2));
    }

    private function messageKey(TelegraphChat $chat): string
    {
        return 'telegram-welcome:'.$chat->telegraph_bot_id.':'.$chat->chat_id;
    }
}
