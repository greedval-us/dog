<?php

namespace App\Modules\Telegram\Queries;

use DefStudio\Telegraph\Models\TelegraphBot;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class GetTelegramBotUrl
{
    public function handle(TelegraphBot $bot, bool $refresh = false): ?string
    {
        if (! $bot->exists) {
            return null;
        }
        $key = 'telegram-bot-url:'.$bot->id.':'.hash('sha256', $bot->token);
        if ($refresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, 300, function () use ($bot): ?string {
            try {
                $url = $bot->url();
            } catch (Exception $exception) {
                Log::notice('Telegram bot identity is unavailable.', ['bot_id' => $bot->id, 'exception' => $exception::class]);

                return null;
            }

            return preg_match('~^https://t\.me/[a-zA-Z0-9_]+$~D', $url) === 1 ? $url : null;
        });
    }
}
