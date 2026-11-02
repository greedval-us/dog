<?php

namespace App\Http\Telegram\Menus;

use App\Models\User;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Players\Actions\MarkSystemNotificationRead;
use App\Modules\Players\DTO\SystemNotificationData;
use App\Modules\Players\Queries\GetSystemNotificationRecords;
use App\Modules\Players\Queries\GetUnreadSystemNotificationCount;
use App\Modules\Telegram\DTO\TelegramScreen;
use DateTimeImmutable;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Str;

/**
 * @phpstan-import-type Command from MenuView
 * @phpstan-import-type State from MenuView
 */
final class NotificationMenu
{
    public function __construct(
        private MenuView $view,
        private GetSystemNotificationRecords $records,
        private GetUnreadSystemNotificationCount $unread,
        private MarkSystemNotificationRead $markRead,
    ) {}

    /** @param Command $command */
    public function index(User $user, string $locale, array $command): TelegramScreen
    {
        $cursor = $this->cursor(new SavedMenuState(['notification' => $command], 'notification'));
        $notifications = $this->records->handle($user)->unread()->orderByDesc('id')->cursorPaginate(MenuView::PAGE_SIZE, cursor: $cursor);
        $encodedCursor = $cursor?->encode();
        $text = $this->view->translate('notifications.title', $locale)."\n".$this->view->translate('notifications.unread_count', $locale, [
            'count' => $this->unread->handle($user),
        ]);
        $rows = [];
        foreach ($notifications as $notification) {
            $data = SystemNotificationData::fromModel($notification);
            $label = ($data->createdAt === null ? '' : $this->view->dateTime($data->createdAt, $user).' · ').($data->title[$locale] ?? $data->title['en'] ?? '');
            $rows[] = [$this->view->commandButton($label, [
                'action' => 'notifications.show', 'notificationId' => $data->id, 'cursor' => $encodedCursor,
            ])];
        }
        if ($notifications->isEmpty()) {
            $text .= "\n\n".$this->view->translate('notifications.empty', $locale);
        }
        $rows = [...$rows, ...$this->view->pageNavigation($notifications->count(), 0, $encodedCursor,
            $notifications->nextCursor()?->encode(), $notifications->previousCursor()?->encode(), ['action' => 'notifications'], $locale)];
        $rows[] = [$this->view->button('refresh', 'notifications', $locale)];

        return $this->view->screen($text, $rows, $locale);
    }

    /** @param Command $command */
    public function show(User $user, string $locale, array $command): TelegramScreen
    {
        $saved = new SavedMenuState(['notification' => $command], 'notification');
        $cursor = $this->cursor($saved);
        $notification = $this->records->handle($user)->findOrFail(strtolower($saved->uuid('notificationId')));

        return $this->detail($user, $locale, SystemNotificationData::fromModel($notification), $cursor?->encode());
    }

    /** @param State $state */
    public function read(User $user, string $locale, array $state): TelegramScreen
    {
        $saved = new SavedMenuState($state, 'notification');
        $cursor = $this->cursor($saved);
        $notification = $this->markRead->handle($user, strtolower($saved->uuid('notificationId')));

        return $this->detail($user, $locale, SystemNotificationData::fromModel($notification), $cursor?->encode(),
            $this->view->translate('notifications.read_done', $locale));
    }

    private function detail(User $user, string $locale, SystemNotificationData $notification, ?string $cursor, ?string $notice = null): TelegramScreen
    {
        $text = $this->view->translate('notifications.title', $locale)."\n".Str::limit($notification->title[$locale] ?? $notification->title['en'] ?? '', 120);
        if ($notification->createdAt !== null) {
            $text .= "\n".$this->view->translate('notifications.created_at', $locale, ['time' => $this->view->dateTime($notification->createdAt, $user)]);
        }
        if ($notification->readAt !== null) {
            $text .= "\n".$this->view->translate('notifications.read_at', $locale, ['time' => $this->view->dateTime($notification->readAt, $user)]);
        }
        $text .= "\n\n".Str::limit($notification->message[$locale] ?? $notification->message['en'] ?? '', 2000);
        $rows = $notification->readAt === null ? [[$this->view->button('notifications.read', 'notifications.read', $locale)]] : [];
        $state = $notification->readAt === null ? ['notification' => ['notificationId' => $notification->id, 'cursor' => $cursor]] : [];

        return $this->view->screen(($notice === null ? '' : $notice."\n\n").$text, $rows, $locale,
            ['action' => 'notifications', 'cursor' => $cursor], $state);
    }

    private function cursor(SavedMenuState $state): ?Cursor
    {
        $cursor = $state->nullableString('cursor');
        if ($cursor === null) {
            return null;
        }
        if (strlen($cursor) > 2048) {
            throw new PetUnavailable('telegram.errors.stale');
        }
        $decoded = base64_decode(str_replace(['-', '_'], ['+', '/'], $cursor), true);
        $parameters = $decoded === false ? null : json_decode($decoded, true);
        if (! is_array($parameters) || count($parameters) !== 3 || ! is_bool($parameters['_pointsToNextItems'] ?? null)
            || ! is_string($parameters['id'] ?? null) || ! Str::isUuid($parameters['id'])
            || ! is_string($parameters['created_at'] ?? null) || ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $parameters['created_at'])) {
            throw new PetUnavailable('telegram.errors.stale');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $parameters['created_at']);
        if ($date === false || $date->format('Y-m-d H:i:s') !== $parameters['created_at']) {
            throw new PetUnavailable('telegram.errors.stale');
        }

        return new Cursor(['created_at' => $parameters['created_at'], 'id' => $parameters['id']], $parameters['_pointsToNextItems']);
    }
}
