<?php

namespace App\Http\Telegram\Menus;

use App\Models\User;
use App\Modules\Telegram\DTO\TelegramScreen;
use InvalidArgumentException;

/**
 * @phpstan-import-type Command from MenuView
 * @phpstan-import-type State from MenuView
 */
final class CatalogueMenuRoutes
{
    public function __construct(
        private CatalogueMenu $catalogue,
    ) {}

    /**
     * @param  Command  $command
     * @param  State  $state
     */
    public function render(User $user, string $locale, array $command, array $state): TelegramScreen
    {
        return match ($command['action'] ?? 'home') {
            'inventory', 'shop' => $this->catalogue->browse($user, $locale, $command),
            'inventory.item' => $this->catalogue->inventoryItem($user, $locale, $command),
            'shop.offer' => $this->catalogue->offer($user, $locale, $command),
            'shop.confirm' => $this->catalogue->purchaseConfirmation($locale, $state),
            'shop.buy' => $this->catalogue->buy($user, $locale, $state),
            default => throw new InvalidArgumentException('Unknown Telegram menu action.'),
        };
    }
}
