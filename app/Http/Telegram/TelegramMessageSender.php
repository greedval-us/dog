<?php

namespace App\Http\Telegram;

use DefStudio\Telegraph\Client\TelegraphResponse;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Models\TelegraphChat;
use DefStudio\Telegraph\Telegraph;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;

final class TelegramMessageSender
{
    public function createMessage(TelegraphChat $chat, string $text, Keyboard $keyboard): int
    {
        return $this->messageIdentifier($this->send($chat->html($text)->keyboard($keyboard)
            ->withData('disable_notification', true)->withData('link_preview_options', ['is_disabled' => true])));
    }

    private function messageIdentifier(TelegraphResponse $response): int
    {
        $this->assertSuccess($response);
        $id = $response->telegraphMessageId();
        if ($id === null || $id < 1) {
            throw new TelegramDeliveryFailed;
        }

        return $id;
    }

    public function remove(TelegraphChat $chat, int $messageId, bool $clearKeyboard = false): void
    {
        try {
            $response = $this->send($chat->deleteMessage($messageId));
            if ($response->telegraphOk()) {
                return;
            }
        } catch (TelegramDeliveryFailed) {
        }
        Log::notice('Telegram could not delete an old message.', ['chat_id' => $chat->id, 'message_id' => $messageId]);
        if ($clearKeyboard) {
            try {
                $this->send($chat->deleteKeyboard($messageId));
            } catch (TelegramDeliveryFailed) {
                Log::notice('Telegram could not clear an old keyboard.', ['chat_id' => $chat->id, 'message_id' => $messageId]);
            }
        }
    }

    public function send(Telegraph $request): TelegraphResponse
    {
        try {
            return $request->send();
        } catch (ConnectionException) {
            throw new TelegramDeliveryFailed;
        }
    }

    private function assertSuccess(TelegraphResponse $response): void
    {
        if ($response->telegraphError()) {
            $retryAfter = (int) $response->json('parameters.retry_after', 0);
            throw new TelegramDeliveryFailed($retryAfter, (int) $response->json('error_code', $response->status()));
        }
    }
}
