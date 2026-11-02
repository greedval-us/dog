<?php

namespace App\Http\Telegram\Menus;

use App\Models\User;
use App\Modules\Pets\Actions\PublishPetSale;
use App\Modules\Pets\Actions\PurchasePetSale;
use App\Modules\Pets\Enums\PetSex;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\GetPetMarket;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Telegram\DTO\TelegramScreen;
use Illuminate\Support\Str;

/**
 * @phpstan-import-type Command from MenuView
 * @phpstan-import-type State from MenuView
 *
 * @phpstan-type Quote array{userId:int,listingId:int,petId:int,name:string,expectedPrice:int,token:string,sex:string|null,cursor:string|null,offset:int}
 */
final class PetMarketPurchaseMenu
{
    public function __construct(private MenuView $view, private GetPetMarket $market, private PurchasePetSale $purchase) {}

    /**
     * @param  Command  $command
     * @param  State  $state
     */
    public function confirm(User $user, string $locale, array $command, array $state = []): TelegramScreen
    {
        $selection = new SavedMenuState(['selection' => $command], 'selection');
        $listing = $this->market->find($user, $selection->integer('listingId'), $locale);
        if ($listing['seller']['id'] === $user->id) {
            throw new PetUnavailable('ownership.errors.unavailable');
        }
        $user->refresh();
        if ($user->status !== PlayerStatus::Active) {
            throw new PetUnavailable('Your account is blocked.');
        }
        $quote = null;
        if (array_key_exists('marketPurchase', $state)) {
            $saved = $this->quote($user, $state);
            if ($saved['listingId'] === $listing['id']) {
                if ($saved['expectedPrice'] !== $listing['price']) {
                    throw new PetUnavailable('ownership.errors.price_changed');
                }
                if ($saved['petId'] !== $listing['pet']['id'] || $saved['name'] !== $listing['pet']['name']) {
                    throw new PetUnavailable('telegram.errors.stale');
                }
                $quote = $saved;
            }
        }
        $quote ??= $this->quote($user, ['marketPurchase' => [
            'userId' => $user->id, 'listingId' => $listing['id'], 'petId' => $listing['pet']['id'],
            'name' => $listing['pet']['name'], 'expectedPrice' => $listing['price'], 'token' => (string) Str::uuid(),
            'sex' => $selection->nullableString('sex'), 'cursor' => $selection->nullableString('cursor'),
            'offset' => $selection->integer('offset', minimum: 0, default: 0),
        ]]);
        $lines = [
            $this->view->translate('market.title', $locale),
            $quote['name'],
            $this->view->translate('market.breed', $locale, ['breed' => $listing['pet']['breed']]),
            $this->view->translate('market.age', $locale, ['months' => $listing['pet']['ageMonths']]),
            $this->view->translate('market.price', $locale, ['price' => $quote['expectedPrice']]),
            $this->view->translate('market.balance', $locale, ['coins' => $user->coins]),
            $this->view->translate('market.space', $locale, ['count' => max(0, $user->pet_slots - $user->pets()->active()->count())]),
            $this->view->translate('market.confirmation', $locale, ['name' => $quote['name'], 'price' => $quote['expectedPrice']]),
        ];

        return $this->view->screen(implode("\n", $lines), [[$this->view->button('confirm', 'market.buy', $locale)]], $locale, [
            'action' => 'market.show', 'listingId' => $quote['listingId'],
            'sex' => $quote['sex'], 'cursor' => $quote['cursor'], 'offset' => $quote['offset'],
        ], ['marketPurchase' => $quote]);
    }

    /** @param State $state */
    public function buy(User $user, string $locale, array $state): TelegramScreen
    {
        $quote = $this->quote($user, $state);
        $receipt = $this->purchase->handle($user, $quote['listingId'], $quote['expectedPrice'], $quote['token']);
        $rows = [];
        if ($user->pets()->active()->whereKey($receipt->pet_id)->exists()) {
            $rows[] = [$this->view->button('market.open_pet', 'pet', $locale, ['petId' => $receipt->pet_id])];
        }
        $rows[] = [$this->view->button('market.title', 'market', $locale), $this->view->button('slots.title', 'slots', $locale)];

        return $this->view->screen($this->view->translate('market.purchased', $locale, [
            'name' => $quote['name'], 'price' => $receipt->price_paid,
        ]), $rows, $locale, ['action' => 'market']);
    }

    /**
     * @param  State  $state
     * @return Quote
     */
    private function quote(User $user, array $state): array
    {
        $saved = new SavedMenuState($state, 'marketPurchase');
        $price = $saved->integer('expectedPrice');
        $sex = $saved->nullableString('sex');
        if ($saved->integer('userId') !== $user->id || $price > PublishPetSale::MAXIMUM_PRICE
            || ($sex !== null && PetSex::tryFrom($sex) === null)) {
            throw new PetUnavailable('telegram.errors.stale');
        }

        return [
            'userId' => $user->id, 'listingId' => $saved->integer('listingId'), 'petId' => $saved->integer('petId'),
            'name' => $saved->string('name'), 'expectedPrice' => $price, 'token' => $saved->uuid(),
            'sex' => $sex, 'cursor' => $saved->nullableString('cursor'), 'offset' => $saved->integer('offset', minimum: 0),
        ];
    }
}
