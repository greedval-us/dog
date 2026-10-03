<?php

namespace App\Modules\Players\Actions;

use App\Models\User;
use App\Modules\Players\Queries\GetSystemNotificationRecords;
use Illuminate\Notifications\DatabaseNotification;

final class MarkSystemNotificationRead
{
    public function __construct(private GetSystemNotificationRecords $records) {}

    public function handle(User $user, string $notificationId): DatabaseNotification
    {
        $notification = $this->records->handle($user)->findOrFail($notificationId);
        $notification->markAsRead();

        return $notification;
    }
}
