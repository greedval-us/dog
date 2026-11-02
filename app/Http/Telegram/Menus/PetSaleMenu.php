<?php

namespace App\Http\Telegram\Menus;

use App\Http\Telegram\ApplyTelegramPetSale;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Actions\PublishPetSale;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\GetPetOwnership;
use App\Modules\Telegram\DTO\TelegramScreen;
use Illuminate\Support\Str;

/**
 * @phpstan-import-type Command from MenuView
 * @phpstan-import-type State from MenuView
 * @phpstan-import-type Draft from SavedPetSaleDraft
 */
final class PetSaleMenu
{
    public function __construct(private MenuView $view, private GetPetOwnership $ownership, private ApplyTelegramPetSale $sales) {}

    /** @param Command $command */
    public function choose(User $user, string $locale, array $command): TelegramScreen
    {
        $offset = max(0, (int) ($command['offset'] ?? 0));
        $pets = $user->pets()->active()->with('dog')->orderBy('id')->offset($offset)->limit(MenuView::PAGE_SIZE + 1)->get();
        $rows = [];
        foreach ($pets->take(MenuView::PAGE_SIZE) as $pet) {
            $rows[] = [$this->view->commandButton($pet->name.' · '.$pet->dog->localizedName($locale), ['action' => 'market.sell.pet', 'petId' => $pet->id])];
        }
        $rows = [...$rows, ...$this->view->offsetNavigation($offset + $pets->count(), $offset, ['action' => 'market.sell'], $locale)];

        return $this->view->screen($this->view->translate('market.sell_title', $locale)."\n\n".$this->view->translate($pets->isEmpty() ? 'market.no_pets' : 'market.choose_pet', $locale), $rows, $locale, ['action' => 'market']);
    }

    /** @param Command $command */
    public function show(User $user, string $locale, array $command): TelegramScreen
    {
        $pet = $this->pet($user, $command);
        $ownership = $this->ownership->handle($user, $pet->id, $locale);
        $lines = [$this->summary($pet, $locale), $this->view->translate($ownership['listing'] === null ? 'market.not_listed' : 'market.current_price', $locale, ['price' => $ownership['listing']['price'] ?? 0])];
        $rows = [];
        if ($ownership['reason'] !== null) {
            $lines[] = $ownership['reason'];
        } else {
            $rows[] = [$this->view->button($ownership['listing'] === null ? 'market.sell_action' : 'market.edit_price', 'market.sell.price', $locale, ['petId' => $pet->id])];
        }
        if ($ownership['listing'] !== null) {
            $rows[] = [$this->view->button('market.withdraw_action', 'market.withdraw-confirm', $locale, ['petId' => $pet->id])];
        }

        return $this->view->screen(implode("\n\n", $lines), $rows, $locale, ['action' => 'market.sell']);
    }

    /**
     * @param  Command  $command
     * @param  State  $state
     */
    public function prompt(User $user, string $locale, array $command, array $state = [], ?string $notice = null): TelegramScreen
    {
        $pet = $this->pet($user, $command);
        $ownership = $this->ownership->handle($user, $pet->id, $locale);
        if ($ownership['reason'] !== null) {
            return $this->show($user, $locale, $command);
        }
        $draft = isset($state['marketSale']) ? (new SavedPetSaleDraft($user, $state))->data : [
            'userId' => $user->id, 'petId' => $pet->id, 'price' => null, 'token' => (string) Str::uuid(),
            'awaitingPrice' => true, 'priceRequestedAt' => now()->getTimestamp(),
        ];
        if ($draft['petId'] !== $pet->id) {
            throw new PetUnavailable('telegram.errors.stale');
        }
        $draft['awaitingPrice'] = true;
        $draft['priceRequestedAt'] = now()->getTimestamp();
        $lines = array_filter([$notice, $this->summary($pet, $locale), $this->view->translate('market.enter_price', $locale, ['maximum' => PublishPetSale::MAXIMUM_PRICE])]);

        return $this->view->screen(implode("\n\n", $lines), [], $locale, ['action' => 'market.sell.pet', 'petId' => $pet->id], ['marketSale' => $draft]);
    }

    /**
     * @param  Command  $command
     * @param  State  $state
     */
    public function price(User $user, string $locale, array $command, array $state): TelegramScreen
    {
        $draft = (new SavedPetSaleDraft($user, $state))->data;
        if (! $draft['awaitingPrice']) {
            throw new PetUnavailable('telegram.errors.stale');
        }
        $text = is_string($command['text'] ?? null) ? trim($command['text']) : '';
        $price = preg_match('/^[0-9]{1,7}$/D', $text) ? (int) $text : 0;
        if ($price < 1 || $price > PublishPetSale::MAXIMUM_PRICE) {
            return $this->prompt($user, $locale, ['petId' => $draft['petId']], $state, $this->view->translate('market.invalid_price', $locale, ['maximum' => PublishPetSale::MAXIMUM_PRICE]));
        }
        $pet = $this->pet($user, ['petId' => $draft['petId']]);
        if ($draft['price'] !== null && $draft['price'] !== $price) {
            $draft['token'] = (string) Str::uuid();
        }
        $draft['price'] = $price;
        $draft['awaitingPrice'] = false;

        return $this->view->screen($this->summary($pet, $locale)."\n\n".$this->view->translate('market.publish_confirmation', $locale, ['name' => $pet->name, 'price' => $price]), [
            [$this->view->button('market.publish', 'market.sell.publish', $locale)],
            [$this->view->button('market.edit_price', 'market.sell.price', $locale, ['petId' => $pet->id])],
        ], $locale, ['action' => 'market.sell.pet', 'petId' => $pet->id], ['marketSale' => $draft]);
    }

    /** @param State $state */
    public function publish(User $user, string $locale, array $state): TelegramScreen
    {
        $draft = (new SavedPetSaleDraft($user, $state))->data;
        if ($draft['awaitingPrice'] || $draft['price'] === null) {
            throw new PetUnavailable('telegram.errors.stale');
        }
        $name = $this->sales->handle($user, $draft['petId'], $draft['price'], $draft['token']);

        return $this->result($user, $locale, $draft['petId'], $this->view->translate('market.published', $locale, ['name' => $name, 'price' => $draft['price']]));
    }

    /** @param Command $command */
    public function withdrawConfirmation(User $user, string $locale, array $command): TelegramScreen
    {
        $pet = $this->pet($user, $command);
        $listing = $this->ownership->handle($user, $pet->id, $locale)['listing'];
        if ($listing === null) {
            throw new PetUnavailable('telegram.errors.stale');
        }

        return $this->view->screen($this->view->translate('market.withdraw_confirmation', $locale, ['name' => $pet->name, 'price' => $listing['price']]), [
            [$this->view->button('market.withdraw', 'market.withdraw', $locale)],
        ], $locale, ['action' => 'market.sell.pet', 'petId' => $pet->id], ['marketSale' => [
            'userId' => $user->id, 'petId' => $pet->id, 'price' => null, 'token' => (string) Str::uuid(),
            'awaitingPrice' => false, 'priceRequestedAt' => now()->getTimestamp(),
        ]]);
    }

    /** @param State $state */
    public function withdraw(User $user, string $locale, array $state): TelegramScreen
    {
        $draft = (new SavedPetSaleDraft($user, $state))->data;
        if ($draft['awaitingPrice'] || $draft['price'] !== null) {
            throw new PetUnavailable('telegram.errors.stale');
        }
        $name = $this->sales->handle($user, $draft['petId'], null, $draft['token']);

        return $this->result($user, $locale, $draft['petId'], $this->view->translate('market.withdrawn', $locale, ['name' => $name]));
    }

    /** @param Command $command */
    private function pet(User $user, array $command): Pet
    {
        $selection = new SavedMenuState(['selection' => $command], 'selection');

        return $user->pets()->with('dog')->findOrFail($selection->integer('petId'));
    }

    private function summary(Pet $pet, string $locale): string
    {
        return $this->view->translate('market.sell_title', $locale)."\n".$this->view->translate('market.pet_summary', $locale, ['name' => $pet->name, 'breed' => $pet->dog->localizedName($locale)]);
    }

    private function result(User $user, string $locale, int $petId, string $notice): TelegramScreen
    {
        $rows = [];
        if ($user->pets()->active()->whereKey($petId)->exists()) {
            $rows[] = [$this->view->button('market.open_pet', 'pet', $locale, ['petId' => $petId])];
        }

        return $this->view->screen($notice, $rows, $locale, ['action' => 'market.sell']);
    }
}
