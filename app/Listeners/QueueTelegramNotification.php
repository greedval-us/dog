<?php

namespace App\Listeners;

use App\Jobs\SendTelegramNotification;
use App\Models\TelegramAccount;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSent;

final class QueueTelegramNotification
{
    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database' || ! $event->notification instanceof SystemNotification
            || ! $event->notifiable instanceof User || ! $event->response instanceof DatabaseNotification) {
            return;
        }

        $kind = $event->response->data['kind'] ?? null;
        if (! in_array($kind, ['competition_result', 'show_result', 'pet_death', 'pet_sold', 'breeding_success', 'achievement'], true)) {
            return;
        }

        $accountId = TelegramAccount::query()->where('user_id', $event->notifiable->id)->value('id');
        if ($accountId !== null) {
            SendTelegramNotification::dispatch($accountId, $event->response->id)->afterCommit();
        }
    }
}
