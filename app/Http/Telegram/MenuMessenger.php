<?php

namespace App\Http\Telegram;

use App\Models\TelegramAccount;
use App\Modules\Telegram\Services\MenuSession;
use DefStudio\Telegraph\Client\TelegraphResponse;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Models\TelegraphChat;
use DefStudio\Telegraph\Telegraph;

final class MenuMessenger
{
    public function __construct(private MenuSession $session, private TelegramMessageSender $sender) {}

    public function status(TelegramAccount $account, bool $help = false, ?string $notice = null): void
    {
        $chat = $account->chat;
        $messageId = $account->menu_message_id;
        $mediaIds = $this->mediaMessageIds($account);
        $button = $help
            ? Button::make(__('telegram.alerts.settings'))->url(route('profile.edit'))
            : Button::make(__('telegram.alerts.open'))->url(route('dashboard'));
        $text = $this->withNotice(__($help ? 'telegram.alerts.help' : 'telegram.alerts.connected'), $notice);
        $newId = $this->sender->createMessage($chat, $text, Keyboard::make()->row([$button]));

        $account->forceFill(['menu_message_id' => $newId, 'menu_state' => []])->save();
        $this->replacePreviousMessage($chat, $newId, $messageId, $mediaIds);
    }

    /** @param list<int> $mediaIds */
    public function welcome(TelegraphChat $chat, ?string $notice = null, ?int $messageId = null, array $mediaIds = []): void
    {
        $keyboard = Keyboard::make()->row([Button::make(__('telegram.connect_site'))->url(route('profile.edit'))]);
        $newId = $this->sender->createMessage($chat, $this->withNotice(__('telegram.welcome'), $notice), $keyboard);
        $this->replacePreviousMessage($chat, $newId, $messageId, $mediaIds);
    }

    private function withNotice(string $text, ?string $notice): string
    {
        return ($notice === null ? '' : htmlspecialchars($notice, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')."\n\n").$text;
    }

    /** @return list<int> */
    public function mediaMessageIds(TelegramAccount $account): array
    {
        $saved = $account->menu_state['mediaIds'] ?? [];

        return is_array($saved) ? array_values(array_filter($saved, fn (mixed $id): bool => is_int($id) && $id > 0)) : [];
    }

    /** @param list<int> $mediaIds */
    private function replacePreviousMessage(TelegraphChat $chat, int $newId, ?int $messageId, array $mediaIds): void
    {
        $oldIds = [$messageId ?? $this->session->messageId($chat), ...$mediaIds, ...$this->session->mediaMessageIds($chat)];
        $this->session->rememberMessage($chat, $newId);

        foreach (array_unique($oldIds) as $id) {
            if ($id !== null && $id !== $newId) {
                $this->sender->remove($chat, $id, true);
            }
        }
    }

    public function remove(TelegraphChat $chat, int $messageId): void
    {
        $this->sender->remove($chat, $messageId);
    }

    public function send(Telegraph $request): TelegraphResponse
    {
        return $this->sender->send($request);
    }
}
