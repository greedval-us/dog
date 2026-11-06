<?php

namespace App\Http\Telegram;

use App\Models\TelegramAccount;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Telegram\Actions\ConnectTelegramAccount;
use App\Modules\Telegram\DTO\TelegramUpdate;
use App\Modules\Telegram\Exceptions\TelegramUnavailable;
use DefStudio\Telegraph\Models\TelegraphBot;
use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;

final class HandleTelegramUpdate
{
    public function __construct(private ConnectTelegramAccount $connect, private MenuMessenger $messenger) {}

    public function handle(TelegraphBot $bot, TelegramUpdate $update): void
    {
        $chat = $bot->chats()->firstOrNew(['chat_id' => $update->senderId], ['name' => 'DogLive player']);
        $account = $chat->exists ? TelegramAccount::query()->where('telegraph_chat_id', $chat->id)
            ->where('telegram_user_id', $update->senderId)->first() : null;

        try {
            $notice = null;
            if ($update->command === 'start' && $update->parameter !== null) {
                try {
                    $account = $this->connectAccount($bot, $chat, $update, $update->parameter);
                } catch (TelegramUnavailable $exception) {
                    $notice = $exception->getMessage();
                }
            }

            if ($account === null) {
                if (! $update->isCallback()) {
                    $this->messenger->welcome($chat, $notice === null ? null : Lang::string($notice));
                }

                return;
            }
            if ($account->user->status !== PlayerStatus::Active) {
                return;
            }
            if ($update->isCallback()) {
                if ($update->legacyAccountId !== $account->id) {
                    return;
                }
                $notice = 'telegram.alerts.old_menu';
            }

            $account->setRelation('chat', $chat);
            app()->setLocale($account->user->preferredLocale());
            $this->messenger->status($account, $update->command === 'help', $notice === null ? null : Lang::string($notice));
        } finally {
            if (! $update->isCallback() && $update->messageId > 0) {
                $this->messenger->remove($chat, $update->messageId);
            }
        }
    }

    private function connectAccount(TelegraphBot $bot, TelegraphChat $chat, TelegramUpdate $update, string $token): TelegramAccount
    {
        return DB::transaction(function () use ($bot, $chat, $update, $token): TelegramAccount {
            if (! $chat->exists) {
                $chat->save();
            }

            return $this->connect->handle($bot, $chat, $update->senderId, $update->firstName, $update->username, $token);
        });
    }
}
