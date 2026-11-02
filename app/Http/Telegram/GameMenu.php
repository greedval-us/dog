<?php

namespace App\Http\Telegram;

use App\Http\Telegram\Menus\ActivityMenuRoutes;
use App\Http\Telegram\Menus\CatalogueMenuRoutes;
use App\Http\Telegram\Menus\EventMenuRoutes;
use App\Http\Telegram\Menus\KennelMenuRoutes;
use App\Http\Telegram\Menus\MarketMenuRoutes;
use App\Http\Telegram\Menus\MenuView;
use App\Http\Telegram\Menus\PetMenuRoutes;
use App\Http\Telegram\Menus\PlayerMenuRoutes;
use App\Models\User;
use App\Modules\Telegram\DTO\TelegramScreen;
use InvalidArgumentException;

/**
 * @phpstan-import-type Command from MenuView
 * @phpstan-import-type State from MenuView
 */
final class GameMenu
{
    public function __construct(
        private PlayerMenuRoutes $players,
        private PetMenuRoutes $pets,
        private ActivityMenuRoutes $activities,
        private CatalogueMenuRoutes $catalogue,
        private KennelMenuRoutes $kennel,
        private MarketMenuRoutes $market,
        private EventMenuRoutes $events,
    ) {}

    /**
     * @param  Command  $command
     * @param  State  $state
     */
    public function render(User $user, array $command = ['action' => 'home'], array $state = []): TelegramScreen
    {
        $action = $command['action'] ?? 'home';
        $section = is_string($action) ? explode('.', $action, 2)[0] : null;
        $routes = match ($section) {
            'home', 'profile', 'help', 'daily_work', 'notifications' => $this->players,
            'pets', 'pet' => $this->pets,
            'care', 'skills', 'work', 'clinic' => $this->activities,
            'inventory', 'shop' => $this->catalogue,
            'kennel', 'slots' => $this->kennel,
            'market' => $this->market,
            'events' => $this->events,
            default => throw new InvalidArgumentException('Unknown Telegram menu action.'),
        };

        return $routes->render($user, $user->preferredLocale(), $command, $state);
    }
}
