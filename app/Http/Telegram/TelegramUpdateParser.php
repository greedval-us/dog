<?php

namespace App\Http\Telegram;

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
                legacyAccountId: $this->legacyAccountId($payload['data'] ?? null));
        }
        $text = $payload['text'] ?? null;
        if (! is_string($text) || mb_strlen($text) > 4096) {
            return null;
        }
        if (! preg_match('/^\/(start|status|menu|help)(?:@[a-zA-Z0-9_]+)?(?:\s+([a-zA-Z0-9_-]{1,64}))?\s*$/D', $text, $matches)
            || (isset($matches[2]) && $matches[1] !== 'start')) {
            return null;
        }
        $firstName = '';
        $username = null;
        if ($matches[1] === 'start' && isset($matches[2])) {
            $name = data_get($payload, 'from.first_name');
            $firstName = is_string($name) ? mb_substr($name, 0, 255) : '';
            $username = data_get($payload, 'from.username');
            $username = is_string($username) ? mb_substr($username, 0, 255) : null;
        }

        return new TelegramUpdate((string) $senderId, (int) ($payload['message_id'] ?? 0), $firstName, $username,
            command: $matches[1], parameter: $matches[2] ?? null);
    }

    private function legacyAccountId(mixed $data): ?int
    {
        if (! is_string($data) || ! preg_match('/^action:tap;k:[0-9]{1,4};v:[0-9]{1,10};s:([1-9][0-9]{0,18})$/D', $data, $matches)) {
            return null;
        }
        $id = filter_var($matches[1], FILTER_VALIDATE_INT);

        return is_int($id) ? $id : null;
    }
}
