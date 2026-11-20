<?php

namespace App\Modules\Telegram\Queries;

use DefStudio\Telegraph\Models\TelegraphBot;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\MultipleRecordsFoundException;

final class GetTelegramBot
{
    public function handle(?int $botId = null): ?TelegraphBot
    {
        try {
            return TelegraphBot::fromId($botId);
        } catch (ModelNotFoundException|MultipleRecordsFoundException) {
            return null;
        }
    }
}
