<?php

namespace App\Http\Telegram;

use App\Modules\Telegram\DTO\TelegramScreen;
use DefStudio\Telegraph\Client\TelegraphResponse;
use DefStudio\Telegraph\Exceptions\FileException;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Models\TelegraphChat;
use DefStudio\Telegraph\Telegraph;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Throwable;

final class TelegramMessageSender
{
    /** @return array{messageId:int,mediaIds:list<int>} */
    public function createScreen(TelegraphChat $chat, TelegramScreen $screen, Keyboard $keyboard): array
    {
        $plain = html_entity_decode(strip_tags($screen->text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $captionFits = strlen(mb_convert_encoding($plain, 'UTF-16LE', 'UTF-8')) <= 2048;
        $photoId = $screen->photoPath === null ? null : $this->createPhoto($chat, $screen->photoPath, $captionFits ? $screen->text : null, $captionFits ? $keyboard : null);
        if ($photoId !== null && $captionFits) {
            return ['messageId' => $photoId, 'mediaIds' => []];
        }
        try {
            $messageId = $this->createMessage($chat, $screen->text, $keyboard);
        } catch (Throwable $exception) {
            if ($photoId !== null) {
                $this->remove($chat, $photoId);
            }
            throw $exception;
        }

        return ['messageId' => $messageId, 'mediaIds' => $photoId === null ? [] : [$photoId]];
    }

    private function createPhoto(TelegraphChat $chat, string $path, ?string $caption, ?Keyboard $keyboard): ?int
    {
        if (! is_file($path) || ! is_readable($path)) {
            Log::notice('Telegram menu photo was unavailable; sending text.', ['chat_id' => $chat->id]);

            return null;
        }
        try {
            $request = $chat->photo($path)->withData('disable_notification', true);
        } catch (FileException) {
            Log::notice('Telegram menu photo was invalid; sending text.', ['chat_id' => $chat->id]);

            return null;
        }
        if ($caption !== null) {
            $request = $request->html($caption);
        }
        if ($keyboard !== null) {
            $request = $request->keyboard($keyboard);
        }
        $response = $this->send($request);
        if ($response->telegraphError() && (int) $response->json('error_code', $response->status()) === 400) {
            Log::notice('Telegram rejected a menu photo; sending text.', ['chat_id' => $chat->id]);

            return null;
        }

        return $this->messageIdentifier($response);
    }

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
