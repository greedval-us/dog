<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class SystemNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $request->validate(['cursor' => ['nullable', 'string', 'max:2048']]);

        $notifications = $user->notifications()
            ->where('type', SystemNotification::class)
            ->orderByDesc('id')
            ->cursorPaginate(20);

        return response()->json([
            'items' => $notifications->getCollection()->map(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'kind' => $notification->data['kind'],
                'title' => $notification->data['title'],
                'message' => $notification->data['message'],
                'createdAt' => $notification->created_at?->toISOString(),
                'readAt' => $notification->read_at?->toISOString(),
            ])->all(),
            'nextCursor' => $notifications->nextCursor()?->encode(),
            'unreadCount' => $user->unreadNotifications()->where('type', SystemNotification::class)->count(),
        ]);
    }

    public function update(Request $request, string $notification): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $record = $user->notifications()->where('type', SystemNotification::class)->findOrFail($notification);
        $record->markAsRead();

        return response()->json([
            'readAt' => $record->read_at?->toISOString(),
            'unreadCount' => $user->unreadNotifications()->where('type', SystemNotification::class)->count(),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $readAt = now()->startOfSecond();
        $user->unreadNotifications()->where('type', SystemNotification::class)->update(['read_at' => $readAt]);

        return response()->json([
            'readAt' => $readAt->toISOString(),
            'unreadCount' => $user->unreadNotifications()->where('type', SystemNotification::class)->count(),
        ]);
    }
}
