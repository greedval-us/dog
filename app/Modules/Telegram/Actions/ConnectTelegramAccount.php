<?php

namespace App\Modules\Telegram\Actions;

use App\Models\TelegramAccount;
use App\Models\TelegramLinkToken;
use App\Models\User;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Telegram\Exceptions\TelegramUnavailable;
use DefStudio\Telegraph\Models\TelegraphBot;
use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class ConnectTelegramAccount
{
    public function handle(TelegraphBot $bot, TelegraphChat $chat, string $telegramUserId, string $firstName, ?string $username, string $token): TelegramAccount
    {
        if ((int) $chat->telegraph_bot_id !== (int) $bot->id || (string) $chat->chat_id !== $telegramUserId || ! preg_match('/^[1-9][0-9]*$/D', $telegramUserId)) {
            throw new TelegramUnavailable('telegram.errors.invalid_chat');
        }

        $tokenHash = hash('sha256', $token);
        $ownerId = TelegramLinkToken::query()->where('token_hash', $tokenHash)->where('telegraph_bot_id', $bot->id)->value('user_id');

        if ($ownerId === null) {
            throw new TelegramUnavailable('telegram.errors.invalid_link');
        }

        try {
            return DB::transaction(function () use ($ownerId, $bot, $chat, $telegramUserId, $firstName, $username, $tokenHash): TelegramAccount {
                $owner = User::query()->whereKey($ownerId)->lockForUpdate()->first();
                $link = TelegramLinkToken::query()->where('user_id', $ownerId)->where('telegraph_bot_id', $bot->id)->where('token_hash', $tokenHash)->lockForUpdate()->first();

                if ($link === null || $link->expires_at->lessThanOrEqualTo(now())) {
                    throw new TelegramUnavailable('telegram.errors.invalid_link');
                }

                if ($owner === null || $owner->status !== PlayerStatus::Active) {
                    throw new TelegramUnavailable('telegram.errors.inactive');
                }

                if (TelegramAccount::query()->where('user_id', $owner->id)->orWhere('telegram_user_id', $telegramUserId)->orWhere('telegraph_chat_id', $chat->id)->exists()) {
                    throw new TelegramUnavailable('telegram.errors.already_connected');
                }

                $account = TelegramAccount::query()->create([
                    'user_id' => $owner->id, 'telegraph_chat_id' => $chat->id, 'telegram_user_id' => $telegramUserId,
                    'first_name' => $firstName, 'username' => $username, 'linked_at' => now(),
                ]);
                $link->delete();

                return $account->refresh();
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (! str_contains($exception->getSql(), '"telegram_accounts"')) {
                throw $exception;
            }

            throw new TelegramUnavailable('telegram.errors.already_connected', previous: $exception);
        }
    }
}
