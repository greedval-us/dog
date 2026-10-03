<?php

namespace App\Modules\Players\Queries;

use App\Models\User;

final class GetUnreadSystemNotificationCount
{
    public function __construct(private GetSystemNotificationRecords $records) {}

    public function handle(User $user): int
    {
        return $this->records->handle($user)->unread()->count();
    }
}
