<?php

namespace App\Modules\Telegram\Actions;

use App\Models\TelegramAccount;
use App\Models\TelegramLinkToken;
use App\Models\User;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Telegram\DTO\TelegramLinkData;
use App\Modules\Telegram\Exceptions\TelegramUnavailable;
use App\Modules\Telegram\Queries\GetTelegramBot;
use App\Modules\Telegram\Queries\GetTelegramBotUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateTelegramLink
{
    public function __construct(private GetTelegramBot $bots, private GetTelegramBotUrl $botUrls) {}

    public function handle(User $user): TelegramLinkData
    {
        $bot = $this->bots->handle();

        if ($bot === null || (string) config('telegraph.webhook.secret') === '') {
            throw new TelegramUnavailable('telegram.errors.unavailable');
        }
        $botUrl = $this->botUrls->handle($bot);
        if ($botUrl === null) {
            throw new TelegramUnavailable('telegram.errors.unavailable');
        }

        return DB::transaction(function () use ($user, $bot, $botUrl): TelegramLinkData {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($owner->status !== PlayerStatus::Active) {
                throw new TelegramUnavailable('telegram.errors.inactive');
            }

            if (TelegramAccount::query()->where('user_id', $owner->id)->exists()) {
                throw new TelegramUnavailable('telegram.errors.already_connected');
            }

            $token = Str::random(48);
            $expiresAt = now()->addMinutes((int) config('doglive_telegram.link_ttl_minutes', 10));
            TelegramLinkToken::query()->updateOrCreate(['user_id' => $owner->id], [
                'telegraph_bot_id' => $bot->id,
                'token_hash' => hash('sha256', $token),
                'expires_at' => $expiresAt,
            ]);

            return new TelegramLinkData($token, $botUrl.'?start='.$token, $expiresAt->toIso8601String());
        });
    }
}
