<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\Players\Actions\MarkAllSystemNotificationsRead;
use App\Modules\Players\Actions\MarkSystemNotificationRead;
use App\Modules\Players\Queries\GetSystemNotifications;
use App\Modules\Players\Queries\GetUnreadSystemNotificationCount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemNotificationController extends Controller
{
    public function index(Request $request, GetSystemNotifications $notifications): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $data = $request->validate(['cursor' => ['nullable', 'string', 'max:2048']]);

        return response()->json($notifications->handle($user, $data['cursor'] ?? null));
    }

    public function update(Request $request, string $notification, MarkSystemNotificationRead $markRead, GetUnreadSystemNotificationCount $unread): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $record = $markRead->handle($user, $notification);

        return response()->json([
            'readAt' => $record->read_at?->toISOString(),
            'unreadCount' => $unread->handle($user),
        ]);
    }

    public function markAllRead(Request $request, MarkAllSystemNotificationsRead $markRead, GetUnreadSystemNotificationCount $unread): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $readAt = $markRead->handle($user);

        return response()->json([
            'readAt' => $readAt->toISOString(),
            'unreadCount' => $unread->handle($user),
        ]);
    }
}
