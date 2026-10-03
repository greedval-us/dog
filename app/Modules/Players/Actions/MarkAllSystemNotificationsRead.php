<?php

namespace App\Modules\Players\Actions;

use App\Models\User;
use App\Modules\Players\Queries\GetSystemNotificationRecords;
use Carbon\CarbonInterface;

final class MarkAllSystemNotificationsRead
{
    public function __construct(private GetSystemNotificationRecords $records) {}

    public function handle(User $user): CarbonInterface
    {
        $readAt = now()->startOfSecond();
        $this->records->handle($user)->unread()->update(['read_at' => $readAt]);

        return $readAt;
    }
}
