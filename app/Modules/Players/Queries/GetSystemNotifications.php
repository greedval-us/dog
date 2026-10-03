<?php

namespace App\Modules\Players\Queries;

use App\Models\User;
use App\Modules\Players\DTO\SystemNotificationData;
use Illuminate\Notifications\DatabaseNotification;

final class GetSystemNotifications
{
    public function __construct(private GetSystemNotificationRecords $records, private GetUnreadSystemNotificationCount $unread) {}

    /** @return array{items: list<array{id: string, kind: string, title: array<string, string>, message: array<string, string>, createdAt: string|null, readAt: string|null}>, nextCursor: string|null, unreadCount: int} */
    public function handle(User $user, ?string $cursor = null): array
    {
        $notifications = $this->records->handle($user)->whereNull('read_at')->orderByDesc('id')->cursorPaginate(20, cursor: $cursor);

        return [
            'items' => array_values($notifications->getCollection()->map(fn (DatabaseNotification $notification): array => SystemNotificationData::fromModel($notification)->toArray())->all()),
            'nextCursor' => $notifications->nextCursor()?->encode(),
            'unreadCount' => $this->unread->handle($user),
        ];
    }
}
