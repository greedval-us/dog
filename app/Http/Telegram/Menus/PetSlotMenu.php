<?php

namespace App\Http\Telegram\Menus;

use App\Models\User;
use App\Modules\Pets\Actions\PurchasePetSlot;
use App\Modules\Pets\DTO\PurchasePetSlotData;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\GetPetSlots;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Telegram\DTO\TelegramScreen;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-import-type Command from MenuView
 * @phpstan-import-type State from MenuView
 */
final class PetSlotMenu
{
    public function __construct(private MenuView $view, private GetPetSlots $slots, private PurchasePetSlot $purchase) {}

    /** @param Command $command */
    public function index(User $user, string $locale, array $command = []): TelegramScreen
    {
        $user->refresh();
        $slots = $this->slots->handle($user);
        $offset = max(0, (int) ($command['offset'] ?? 0));
        $active = $user->status === PlayerStatus::Active;
        $next = collect($slots)->firstWhere('purchasable', true);
        $lines = [
            $this->view->translate('slots.title', $locale),
            $this->view->translate('slots.description', $locale),
            $this->view->translate('slots.capacity', $locale, ['count' => $user->pet_slots]),
        ];
        $rows = [];
        foreach (array_slice($slots, $offset, MenuView::PAGE_SIZE) as $slot) {
            $lines[] = $this->view->translate($slot['pet'] !== null ? 'slots.occupied' : ($slot['unlocked'] ? 'slots.empty' : 'slots.locked'), $locale, [
                'slot' => $slot['number'], 'name' => $slot['pet']['name'] ?? '',
            ]);
            if (! $active || ! $slot['purchasable']) {
                continue;
            }
            $buttons = [];
            foreach (['coins', 'gems'] as $currency) {
                if ($slot[$currency] < 1) {
                    continue;
                }
                $buttons[] = $this->view->commandButton($this->view->translate('slots.unlock', $locale, [
                    'slot' => $slot['number'], 'price' => $slot[$currency], 'currency' => $this->view->translate($currency, $locale),
                ]), ['action' => 'slots.confirm', 'slot' => $slot['number'], 'currency' => $currency]);
            }
            if ($buttons !== []) {
                $rows[] = $buttons;
            }
        }
        if ($user->pet_slots >= 9) {
            $lines[] = $this->view->translate('slots.maximum', $locale);
        } elseif (! $active || $next === null || ($next['coins'] < 1 && $next['gems'] < 1)) {
            $lines[] = $this->view->translate('slots.unavailable', $locale);
        }
        $rows = [...$rows, ...$this->view->offsetNavigation(count($slots), $offset, ['action' => 'slots'], $locale)];
        $rows[] = [$this->view->button('slots.kennel', 'kennel', $locale), $this->view->button('refresh', 'slots', $locale)];

        return $this->view->screen(implode("\n\n", $lines), $rows, $locale);
    }

    /** @param Command $command */
    public function confirm(User $user, string $locale, array $command): TelegramScreen
    {
        $selection = new SavedMenuState(['selection' => $command], 'selection');
        $number = $selection->integer('slot', minimum: 2);
        $currency = $selection->string('currency');
        if ($number > 9 || ! in_array($currency, ['coins', 'gems'], true)) {
            throw new PetUnavailable('telegram.errors.stale');
        }
        $slot = collect($this->slots->handle($user->refresh()))->firstWhere('number', $number);
        if ($user->status !== PlayerStatus::Active) {
            throw new PetUnavailable('Your account is blocked.');
        }
        if ($slot === null || ! $slot['purchasable'] || $slot[$currency] < 1) {
            throw new PetUnavailable('telegram.errors.stale');
        }

        return $this->view->screen($this->view->translate('slots.confirmation', $locale, [
            'slot' => $number, 'price' => $slot[$currency], 'currency' => $this->view->translate($currency, $locale),
        ]), [[$this->view->button('confirm', 'slots.buy', $locale)]], $locale, ['action' => 'slots'], [
            'slotPurchase' => ['userId' => $user->id, 'slot' => $number, 'currency' => $currency, 'expectedPrice' => $slot[$currency]],
        ]);
    }

    /** @param State $state */
    public function buy(User $user, string $locale, array $state): TelegramScreen
    {
        $quote = new SavedMenuState($state, 'slotPurchase');
        $number = $quote->integer('slot', minimum: 2);
        $currency = $quote->string('currency');
        $price = $quote->integer('expectedPrice');
        if ($quote->integer('userId') !== $user->id || $number > 9 || ! in_array($currency, ['coins', 'gems'], true)) {
            throw new PetUnavailable('telegram.errors.stale');
        }
        DB::transaction(function () use ($user, $number, $currency, $price): void {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($owner->status !== PlayerStatus::Active) {
                throw new PetUnavailable('Your account is blocked.');
            }
            $this->purchase->handle($owner, new PurchasePetSlotData($number, $currency, $price));
        }, attempts: 3);

        return $this->view->screen($this->view->translate('slots.opened', $locale, ['slot' => $number]), [
            [$this->view->button('slots.kennel', 'kennel', $locale)],
        ], $locale, ['action' => 'slots']);
    }
}
