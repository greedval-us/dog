<?php

namespace App\Modules\Players\Queries;

use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\DatabaseNotification;

final class GetSystemNotificationRecords
{
    /** @return MorphMany<DatabaseNotification, User> */
    public function handle(User $user): MorphMany
    {
        return $user->notifications()->where('type', SystemNotification::class);
    }
}
