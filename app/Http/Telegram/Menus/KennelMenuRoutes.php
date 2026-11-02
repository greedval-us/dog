<?php

namespace App\Http\Telegram\Menus;

use App\Models\User;
use App\Modules\Telegram\DTO\TelegramScreen;
use InvalidArgumentException;

/**
 * @phpstan-import-type Command from MenuView
 * @phpstan-import-type State from MenuView
 */
final class KennelMenuRoutes
{
    public function __construct(
        private KennelMenu $kennel,
        private KennelAdoptionMenu $adoption,
        private PetSlotMenu $slots,
    ) {}

    /**
     * @param  Command  $command
     * @param  State  $state
     */
    public function render(User $user, string $locale, array $command, array $state): TelegramScreen
    {
        return match ($command['action'] ?? 'home') {
            'kennel' => $this->kennel->index($user, $locale, $command),
            'kennel.breed' => $this->kennel->breed($user, $locale, $command),
            'kennel.prepare' => $this->kennel->prepare($user, $locale, $command),
            'kennel.name' => $this->adoption->name($user, $locale, $command, $state),
            'kennel.rename' => $this->adoption->prompt($user, $locale, $state),
            'kennel.confirm' => $this->adoption->confirm($user, $locale, $state),
            'kennel.adopt' => $this->adoption->adopt($user, $locale, $state),
            'slots' => $this->slots->index($user, $locale, $command),
            'slots.confirm' => $this->slots->confirm($user, $locale, $command),
            'slots.buy' => $this->slots->buy($user, $locale, $state),
            default => throw new InvalidArgumentException('Unknown Telegram menu action.'),
        };
    }
}
