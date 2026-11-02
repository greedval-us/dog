<?php

namespace App\Http\Telegram\Menus;

use App\Models\User;
use App\Modules\Telegram\DTO\TelegramScreen;
use InvalidArgumentException;

/**
 * @phpstan-import-type Command from MenuView
 * @phpstan-import-type State from MenuView
 */
final class PlayerMenuRoutes
{
    public function __construct(
        private PlayerMenu $players,
        private DailyWorkMenu $dailyWork,
        private NotificationMenu $notifications,
    ) {}

    /**
     * @param  Command  $command
     * @param  State  $state
     */
    public function render(User $user, string $locale, array $command, array $state): TelegramScreen
    {
        return match ($command['action'] ?? 'home') {
            'home' => $this->players->home($user, $locale),
            'profile' => $this->players->profile($user, $locale),
            'help' => $this->players->help($locale),
            'daily_work' => $this->dailyWork->index($user, $locale, $command),
            'daily_work.confirm' => $this->dailyWork->confirm($user, $locale, $command),
            'daily_work.complete' => $this->dailyWork->complete($user, $locale, $state),
            'notifications' => $this->notifications->index($user, $locale, $command),
            'notifications.show' => $this->notifications->show($user, $locale, $command),
            'notifications.read' => $this->notifications->read($user, $locale, $state),
            default => throw new InvalidArgumentException('Unknown Telegram menu action.'),
        };
    }
}
