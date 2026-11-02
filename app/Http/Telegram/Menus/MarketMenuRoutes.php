<?php

namespace App\Http\Telegram\Menus;

use App\Models\User;
use App\Modules\Telegram\DTO\TelegramScreen;
use InvalidArgumentException;

/**
 * @phpstan-import-type Command from MenuView
 * @phpstan-import-type State from MenuView
 */
final class MarketMenuRoutes
{
    public function __construct(
        private PetMarketMenu $market,
        private PetMarketPurchaseMenu $purchase,
        private PetSaleMenu $sales,
    ) {}

    /**
     * @param  Command  $command
     * @param  State  $state
     */
    public function render(User $user, string $locale, array $command, array $state): TelegramScreen
    {
        return match ($command['action'] ?? 'home') {
            'market' => $this->market->index($user, $locale, $command),
            'market.show' => $this->market->show($user, $locale, $command),
            'market.confirm' => $this->purchase->confirm($user, $locale, $command, $state),
            'market.buy' => $this->purchase->buy($user, $locale, $state),
            'market.sell' => $this->sales->choose($user, $locale, $command),
            'market.sell.pet' => $this->sales->show($user, $locale, $command),
            'market.sell.price' => $this->sales->prompt($user, $locale, $command, $state),
            'market.sell.value' => $this->sales->price($user, $locale, $command, $state),
            'market.sell.publish' => $this->sales->publish($user, $locale, $state),
            'market.withdraw-confirm' => $this->sales->withdrawConfirmation($user, $locale, $command),
            'market.withdraw' => $this->sales->withdraw($user, $locale, $state),
            default => throw new InvalidArgumentException('Unknown Telegram menu action.'),
        };
    }
}
