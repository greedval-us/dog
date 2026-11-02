<?php

namespace App\Http\Telegram\Menus;

use App\Models\User;
use App\Modules\Telegram\DTO\TelegramScreen;
use InvalidArgumentException;

/**
 * @phpstan-import-type Command from MenuView
 * @phpstan-import-type State from MenuView
 */
final class PetMenuRoutes
{
    public function __construct(
        private PetMenu $pets,
        private PetHistoryMenu $history,
    ) {}

    /**
     * @param  Command  $command
     * @param  State  $state
     */
    public function render(User $user, string $locale, array $command, array $state): TelegramScreen
    {
        return match ($command['action'] ?? 'home') {
            'pets' => $this->pets->index($user, $locale, $command),
            'pet' => $this->pets->show($user, $locale, (int) $command['petId']),
            'pet.history' => $this->history->index($user, $locale, $command),
            'pet.history.entry' => $this->history->show($user, $locale, $command),
            'pet.history.events' => $this->history->events($user, $locale, $command),
            default => throw new InvalidArgumentException('Unknown Telegram menu action.'),
        };
    }
}
