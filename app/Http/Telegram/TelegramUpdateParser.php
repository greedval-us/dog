<?php

namespace App\Http\Telegram;

use App\Modules\Telegram\Calculators\MenuCallbackCodec;
use App\Modules\Telegram\DTO\TelegramUpdate;

final class TelegramUpdateParser
{
    /** @param array<string, mixed> $update */
    public function parse(array $update): ?TelegramUpdate
    {
        $isCallback = is_array($update['callback_query'] ?? null);
        $payload = $isCallback ? $update['callback_query'] : ($update['message'] ?? null);
        if (! is_array($payload)) {
            return null;
        }
        $chat = $isCallback ? data_get($payload, 'message.chat') : ($payload['chat'] ?? null);
        $senderId = data_get($payload, 'from.id');
        if (! is_array($chat) || ($chat['type'] ?? '') !== 'private'
            || (! is_int($senderId) && ! is_string($senderId)) || ! preg_match('/^[1-9][0-9]{0,19}$/D', (string) $senderId)
            || (string) ($chat['id'] ?? '') !== (string) $senderId || data_get($payload, 'from.is_bot', false)) {
            return null;
        }
        if ($isCallback) {
            $queryId = $payload['id'] ?? null;
            if (! is_string($queryId) && ! is_int($queryId)) {
                return null;
            }

            return new TelegramUpdate((string) $senderId, (int) data_get($payload, 'message.message_id'), '', null,
                callbackQueryId: (string) $queryId,
                callback: is_string($payload['data'] ?? null) ? MenuCallbackCodec::decode($payload['data']) : null);
        }
        $text = $payload['text'] ?? '';
        if (! is_string($text) || ! preg_match('/^\/(start|menu|help)(?:@[a-zA-Z0-9_]+)?(?:\s+([a-zA-Z0-9_-]{1,64}))?\s*$/D', $text, $matches)) {
            return null;
        }
        $firstName = '';
        $username = null;
        if ($matches[1] === 'start' && isset($matches[2])) {
            $firstName = mb_substr((string) data_get($payload, 'from.first_name', ''), 0, 255);
            $username = data_get($payload, 'from.username');
            $username = is_string($username) ? mb_substr($username, 0, 255) : null;
        }

        return new TelegramUpdate((string) $senderId, (int) ($payload['message_id'] ?? 0), $firstName, $username,
            command: $matches[1], parameter: $matches[2] ?? null);
    }
}
