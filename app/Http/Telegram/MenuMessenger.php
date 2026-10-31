<?php

namespace App\Http\Telegram;

use App\Models\TelegramAccount;
use App\Modules\Telegram\Calculators\MenuCallbackCodec;
use App\Modules\Telegram\DTO\TelegramScreen;
use App\Modules\Telegram\Services\MenuSession;
use DefStudio\Telegraph\Client\TelegraphResponse;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Models\TelegraphChat;
use DefStudio\Telegraph\Telegraph;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class MenuMessenger
{
    public function __construct(private MenuSession $session) {}

    public function publish(TelegramAccount $account, TelegramScreen $screen): void
    {
        $revision = $account->menu_revision + 1;
        $commands = [];
        $rows = [];
        foreach ($screen->rows as $row) {
            $buttons = [];
            foreach ($row as $item) {
                $button = ['text' => $item['text']];
                if (isset($item['url'])) {
                    $button['url'] = $item['url'];
                } elseif (isset($item['command'])) {
                    $key = count($commands);
                    $commands[$key] = $item['command'];
                    $button['callback_data'] = MenuCallbackCodec::encode($account->id, $revision, $key);
                } else {
                    throw new \InvalidArgumentException('A Telegram menu button requires a command or URL.');
                }
                $buttons[] = $button;
            }
            $rows[] = $buttons;
        }
        $keyboard = Keyboard::fromArray($rows);
        $state = ['commands' => $commands, 'data' => $screen->state];

        $chat = $account->chat;
        $oldMessageId = $account->menu_message_id ?? $this->session->messageId($chat);
        if ($oldMessageId !== null) {
            if ($this->editMessage($chat, $oldMessageId, $screen->text, $keyboard)) {
                $this->remember($account, $oldMessageId, $revision, $state);

                return;
            }
            Log::notice('Telegram menu message was unavailable; replacing it.', ['account_id' => $account->id]);
        }

        $messageId = $this->createMessage($chat, $screen->text, $keyboard);
        if ($messageId === null || $messageId < 1) {
            throw new RuntimeException('Telegram did not return a menu message identifier.');
        }
        $this->remember($account, $messageId, $revision, $state);
        $this->retireMessage($chat, $oldMessageId, $messageId);
    }

    /** @param array<string, mixed> $state */
    private function remember(TelegramAccount $account, int $messageId, int $revision, array $state): void
    {
        $account->forceFill(['menu_message_id' => $messageId, 'menu_revision' => $revision, 'menu_state' => $state])->save();
        $this->session->rememberMessage($account->chat, $messageId);
    }

    public function welcome(TelegraphChat $chat, ?string $notice = null, ?int $messageId = null): void
    {
        $keyboard = Keyboard::make()->row([Button::make(__('telegram.connect_site'))->url(route('profile.edit'))]);
        $text = ($notice === null ? '' : htmlspecialchars($notice, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')."\n\n").__('telegram.welcome');
        $messageId ??= $this->session->messageId($chat);
        if ($messageId !== null) {
            $this->session->rememberMessage($chat, $messageId);
            if ($this->editMessage($chat, $messageId, $text, $keyboard)) {
                return;
            }
        }
        $newId = $this->createMessage($chat, $text, $keyboard);
        if ($newId === null || $newId < 1) {
            throw new TelegramDeliveryFailed;
        }
        $this->session->rememberMessage($chat, $newId);
        $this->retireMessage($chat, $messageId, $newId);
    }

    private function editMessage(TelegraphChat $chat, int $messageId, string $text, Keyboard $keyboard): bool
    {
        $response = $this->send($chat->edit($messageId)->html($text)->keyboard($keyboard));
        if ($response->telegraphOk() || str_contains((string) $response->json('description', ''), 'message is not modified')) {
            return true;
        }
        $description = strtolower((string) $response->json('description', ''));
        if (! str_contains($description, 'message to edit not found') && ! str_contains($description, "message can't be edited")) {
            $this->assertSuccess($response);
        }

        return false;
    }

    private function createMessage(TelegraphChat $chat, string $text, Keyboard $keyboard): ?int
    {
        $response = $this->send($chat->html($text)->keyboard($keyboard));
        $this->assertSuccess($response);

        return $response->telegraphMessageId();
    }

    private function retireMessage(TelegraphChat $chat, ?int $oldMessageId, int $messageId): void
    {
        if ($oldMessageId !== null && $oldMessageId !== $messageId) {
            $this->remove($chat, $oldMessageId, true);
        }
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
