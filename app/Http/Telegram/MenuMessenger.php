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

final class MenuMessenger
{
    public function __construct(private MenuSession $session, private TelegramMessageSender $sender) {}

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
        $oldMediaIds = $this->mediaMessageIds($account);
        $delivery = $this->sender->createScreen($chat, $screen, $keyboard);
        if ($delivery['mediaIds'] !== []) {
            $state['mediaIds'] = $delivery['mediaIds'];
        }
        $this->remember($account, $delivery['messageId'], $revision, $state, $delivery['mediaIds']);
        $this->retireMessages($chat, [$oldMessageId, ...$oldMediaIds], [$delivery['messageId'], ...$delivery['mediaIds']]);
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  list<int>  $mediaIds
     */
    private function remember(TelegramAccount $account, int $messageId, int $revision, array $state, array $mediaIds): void
    {
        $account->forceFill(['menu_message_id' => $messageId, 'menu_revision' => $revision, 'menu_state' => $state])->save();
        $this->session->rememberMessage($account->chat, $messageId, $mediaIds);
    }

    /** @param list<int> $mediaIds */
    public function welcome(TelegraphChat $chat, ?string $notice = null, ?int $messageId = null, array $mediaIds = []): void
    {
        $keyboard = Keyboard::make()->row([Button::make(__('telegram.connect_site'))->url(route('profile.edit'))]);
        $text = ($notice === null ? '' : htmlspecialchars($notice, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')."\n\n").__('telegram.welcome');
        $messageId ??= $this->session->messageId($chat);
        $oldMediaIds = [...$mediaIds, ...$this->session->mediaMessageIds($chat)];
        $newId = $this->sender->createMessage($chat, $text, $keyboard);
        $this->session->rememberMessage($chat, $newId);
        $this->retireMessages($chat, [$messageId, ...$oldMediaIds], [$newId]);
    }

    /** @return list<int> */
    public function mediaMessageIds(TelegramAccount $account): array
    {
        $saved = $account->menu_state['mediaIds'] ?? [];
        $ids = is_array($saved) ? array_filter($saved, fn (mixed $id): bool => is_int($id) && $id > 0) : [];

        return array_values(array_unique([...$ids, ...$this->session->mediaMessageIds($account->chat)]));
    }

    /**
     * @param  list<int|null>  $oldIds
     * @param  list<int>  $newIds
     */
    private function retireMessages(TelegraphChat $chat, array $oldIds, array $newIds): void
    {
        foreach (array_unique($oldIds) as $id) {
            if ($id !== null && ! in_array($id, $newIds, true)) {
                $this->remove($chat, $id, true);
            }
        }
    }

    public function remove(TelegraphChat $chat, int $messageId, bool $clearKeyboard = false): void
    {
        $this->sender->remove($chat, $messageId, $clearKeyboard);
    }

    public function send(Telegraph $request): TelegraphResponse
    {
        return $this->sender->send($request);
    }
}
